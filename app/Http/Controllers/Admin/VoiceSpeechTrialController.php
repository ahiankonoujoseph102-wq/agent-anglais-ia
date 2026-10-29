<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Gemini\GeminiUnavailable;
use App\Support\Gemini\SpeechFailed;
use App\Support\Gemini\VoiceSampler;
use App\Support\Speech\VoiceClarity;
use App\Support\Teachers\Teacher;
use App\Support\Teachers\Teachers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

/**
 * « Essai des voix », test en FRANÇAIS ET en anglais de la voix des vidéos et des messages d'un professeur qui en
 * propose (config teachers.list.*.speech_trials : Teacher Rose). Écouter (extrait gardé après la première écoute),
 * « Préparer les extraits manquants » (dans un temps limité, Gemini sur la deuxième clé d'abord), « Choisir pour les
 * vidéos et les messages ». Sa voix de séance ne change pas.
 */
class VoiceSpeechTrialController extends Controller
{
    public function sample(Request $request, string $provider, string $voice, VoiceSampler $sampler): Response|JsonResponse
    {
        $teacher = $this->teacher($request->query('professeur'));

        abort_unless(isset($teacher->speechTrials()[$provider][$voice]), 404);

        $wav = $sampler->cachedBilingual($provider, $voice, $teacher);

        if ($wav === null) {
            $key = 'voice-trial:'.$request->user()->id;

            if (RateLimiter::tooManyAttempts($key, (int) config('services.gemini.voice_trial_per_hour'))) {
                return response()->json(['message' => 'Trop d\'essais pour cette heure. Touchez plutôt « Préparer les extraits manquants », ou réessayez dans '.RateLimiter::availableIn($key).' secondes.'], 429);
            }

            RateLimiter::hit($key, 3600);

            try {
                $wav = $sampler->generateBilingual($provider, $voice, $teacher);
            } catch (GeminiUnavailable $e) {
                return response()->json(['message' => self::failure($e, $provider)], 503);
            }
        }

        return response($wav, 200, ['Content-Type' => 'audio/wav', 'Cache-Control' => 'private, max-age=86400']);
    }

    /** Prépare les extraits qui manquent, tant qu'il reste du temps (retoucher le bouton s'il en reste). */
    public function prepare(Request $request, VoiceSampler $sampler): RedirectResponse
    {
        $teacher = $this->teacher($request->input('teacher'));
        $deadline = microtime(true) + max(5, (int) config('services.gemini.tts_web_budget'));
        $made = 0;
        $missing = 0;
        $problems = [];
        $blocked = [];

        foreach ($teacher->speechTrials() as $provider => $voices) {
            foreach (array_keys($voices) as $voice) {
                if ($sampler->cachedBilingual($provider, $voice, $teacher) !== null) {
                    continue;
                }

                // Fournisseur sans clé ou à sa limite : les autres voix de ce fournisseur attendent.
                if (isset($blocked[$provider]) || microtime(true) > $deadline - 10) {
                    $missing++;

                    continue;
                }

                try {
                    $sampler->generateBilingual($provider, $voice, $teacher);
                    $made++;
                } catch (GeminiUnavailable $e) {
                    $missing++;
                    $problems[$provider] = self::failure($e, $provider);

                    if ($e->quotaExceeded || ($e instanceof SpeechFailed && $e->kind === SpeechFailed::MISSING_KEY)) {
                        $blocked[$provider] = true;
                    }
                }
            }
        }

        $status = $made === 0 && $missing === 0
            ? 'Tous les extraits du test en français et en anglais sont prêts.'
            : "Extraits préparés : {$made}. Encore à préparer : {$missing}.".($missing > 0 && $problems === [] ? ' Touchez de nouveau « Préparer les extraits manquants ».' : '');

        return redirect()->to(route('admin.voices.index', ['professeur' => $teacher->key]).'#voix-francais')
            ->with('status', trim($status.' '.implode(' ', array_unique($problems))));
    }

    /**
     * Mesure la clarté des extraits prêts qui n'ont pas encore de résultat (retranscription comparée au texte), tant
     * qu'il reste du temps (retoucher le bouton s'il en reste).
     */
    public function measure(Request $request, VoiceSampler $sampler, VoiceClarity $clarity): RedirectResponse
    {
        $teacher = $this->teacher($request->input('teacher'));
        $deadline = microtime(true) + max(5, (int) config('services.gemini.tts_web_budget'));
        $measured = 0;
        $left = 0;
        $failed = 0;

        foreach ($teacher->speechTrials() as $provider => $voices) {
            foreach (array_keys($voices) as $voice) {
                if ($sampler->cachedBilingual($provider, $voice, $teacher) === null || VoiceClarity::result($provider, $voice, $teacher) !== null) {
                    continue;
                }

                if (microtime(true) > $deadline - 10) {
                    $left++;

                    continue;
                }

                $clarity->measure($provider, $voice, $teacher) !== null ? $measured++ : $failed++;
            }
        }

        $status = "Clarté mesurée : {$measured} extrait".($measured > 1 ? 's' : '').'.'
            .($left > 0 ? " Encore {$left} : touchez de nouveau « Mesurer la clarté »." : '')
            .($failed > 0 ? " {$failed} n'ont pas pu être retranscrits (fournisseur indisponible) : réessayez plus tard." : '');

        return redirect()->to(route('admin.voices.index', ['professeur' => $teacher->key]).'#voix-francais')->with('status', $status);
    }

    public function choose(Request $request): RedirectResponse
    {
        $teacher = $this->teacher($request->input('teacher'));
        $provider = (string) $request->input('provider');
        $voice = (string) $request->input('voice');

        abort_unless(isset($teacher->speechTrials()[$provider][$voice]), 422);

        $teacher->chooseSpeechVoice($provider, $voice);
        $label = $provider === 'openai' ? 'OpenAI' : 'Gemini';

        return redirect()->to(route('admin.voices.index', ['professeur' => $teacher->key]).'#voix-francais')
            ->with('status', "La voix « {$voice} » ({$label}) est choisie pour les vidéos et les messages de {$teacher->name()}. Sa voix de séance ne change pas. Ses messages vocaux se préparent tout seuls dans l'heure (« Diagnostic de la voix » pour aller plus vite) ; ses vidéos déjà faites ne sont refaites que si vous touchez « Refaire ».");
    }

    private function teacher(mixed $key): Teacher
    {
        $teacher = Teachers::find(is_string($key) ? $key : null);

        abort_if($teacher === null || $teacher->speechTrials() === [], 404);

        return $teacher;
    }

    private static function failure(GeminiUnavailable $e, string $provider): string
    {
        $cause = $e instanceof SpeechFailed ? ' Cause : '.$e->reason().($e->status ? ' (code '.$e->status.')' : '').'.' : '';

        return match (true) {
            $e instanceof SpeechFailed && $e->kind === SpeechFailed::MISSING_KEY => 'Clé '.($provider === 'openai' ? 'OPENAI_API_KEY' : 'GEMINI_API_KEY').' absente : ces voix ne peuvent pas être essayées.',
            $e->quotaExceeded => 'Limite du fournisseur atteinte ('.($provider === 'openai' ? 'OpenAI' : 'Gemini, deux clés').') : réessayez plus tard.'.$cause,
            default => 'Un extrait n\'a pas pu être créé.'.$cause,
        };
    }
}
