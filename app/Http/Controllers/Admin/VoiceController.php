<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Gemini\GeminiUnavailable;
use App\Support\Gemini\SpeechFailed;
use App\Support\Gemini\VoiceSampler;
use App\Support\Teachers\Teacher;
use App\Support\Teachers\Teachers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * « Essai des voix » : pour chaque professeur (config/teachers.php, ?professeur=rose), écouter les voix proposées
 * chez Gemini (séances en direct) et chez OpenAI (secours), puis choisir la sienne. Teacher Joe : voix Gemini
 * seulement, comme avant (adresses sans paramètre).
 */
class VoiceController extends Controller
{
    private const PROVIDERS = ['gemini', 'openai'];

    public function index(Request $request): View
    {
        $teacher = $this->teacher($request->query('professeur'));

        return view('admin.voices.index', [
            'teachers' => Teachers::all(),
            'teacher' => $teacher,
            'trials' => $teacher->geminiTrials(),
            'current' => $teacher->geminiVoice(),
            'chosen' => $teacher->isGeminiVoiceChosen(),
            'default' => $teacher->defaultGeminiVoice(),
            'openaiTrials' => $teacher->openaiTrials(),
            'openaiCurrent' => $teacher->realtimeVoice(),
            'openaiChosen' => $teacher->isRealtimeVoiceChosen(),
            'openaiDefault' => $teacher->defaultRealtimeVoice(),
            'script' => VoiceSampler::script($teacher)['text'],
        ]);
    }

    /**
     * Extrait WAV d'une voix : gardé après la première écoute, limité par heure sinon.
     */
    public function sample(Request $request, string $voice, VoiceSampler $sampler): Response|JsonResponse
    {
        $teacher = $this->teacher($request->query('professeur'));
        $provider = $this->provider($request->query('fournisseur'));

        abort_unless($this->isProposed($teacher, $provider, $voice), 404);

        $wav = $sampler->cached($voice, $teacher, $provider);

        if ($wav === null) {
            $key = 'voice-trial:'.$request->user()->id;

            if (RateLimiter::tooManyAttempts($key, (int) config('services.gemini.voice_trial_per_hour'))) {
                return response()->json(['message' => 'Trop d\'essais pour cette heure. Réessayez dans '.RateLimiter::availableIn($key).' secondes : les voix déjà écoutées restent disponibles.'], 429);
            }

            RateLimiter::hit($key, 3600);

            try {
                $wav = $provider === 'openai' ? $sampler->generateOpenAi($voice, $teacher) : $sampler->generate($voice, $teacher);
            } catch (GeminiUnavailable $e) {
                // Cause déjà journalisée par la synthèse vocale (SpeechLog).
                // Cause courte (jamais la clé) : la même que dans « Diagnostic de la voix », dernières erreurs.
                $cause = $e instanceof SpeechFailed ? ' Cause : '.$e->reason().($e->status ? ' (code '.$e->status.')' : '').'.' : '';

                return response()->json(['message' => match (true) {
                    $e instanceof SpeechFailed && $e->kind === SpeechFailed::MISSING_KEY => 'Clé '.($provider === 'openai' ? 'OPENAI_API_KEY' : 'GEMINI_API_KEY').' absente dans les variables de Laravel Cloud : ces voix ne peuvent pas être essayées.',
                    $e->quotaExceeded => 'La limite du fournisseur est atteinte pour le moment. Réessayez plus tard.'.$cause,
                    default => 'L\'extrait n\'a pas pu être créé. Réessayez dans quelques instants.'.$cause,
                }], 503);
            }
        }

        return response($wav, 200, [
            'Content-Type' => 'audio/wav',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function choose(Request $request): RedirectResponse
    {
        $voice = (string) $request->input('voice');
        $teacher = $this->teacher($request->input('teacher'));
        $provider = $this->provider($request->input('provider'));

        abort_unless($this->isProposed($teacher, $provider, $voice), 422);

        $teacher->chooseVoice($provider, $voice);

        $isDefault = $teacher->key === Teachers::default()->key && $provider === 'gemini';

        return redirect()->to(route('admin.voices.index', $isDefault ? [] : ['professeur' => $teacher->key]))
            ->with('status', $isDefault
                ? "La voix « {$voice} » est enregistrée : elle sera utilisée pour toutes les prochaines conversations."
                : "La voix « {$voice} » ({$this->label($provider)}) est enregistrée pour {$teacher->name()} : elle sera utilisée pour ses prochaines conversations.");
    }

    private function teacher(mixed $key): Teacher
    {
        return Teachers::find(is_string($key) ? $key : null) ?? Teachers::default();
    }

    private function provider(mixed $provider): string
    {
        return in_array($provider, self::PROVIDERS, true) ? $provider : 'gemini';
    }

    private function isProposed(Teacher $teacher, string $provider, string $voice): bool
    {
        return array_key_exists($voice, $provider === 'openai' ? $teacher->openaiTrials() : $teacher->geminiTrials());
    }

    private function label(string $provider): string
    {
        return $provider === 'openai' ? 'OpenAI' : 'Gemini';
    }
}
