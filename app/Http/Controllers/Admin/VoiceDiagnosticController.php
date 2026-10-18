<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Gemini\LevelMessage;
use App\Support\Gemini\SpeechFailed;
use App\Support\Gemini\SpeechLog;
use App\Support\Gemini\SpeechPreparer;
use App\Support\Gemini\TeacherVoice;
use App\Support\Gemini\VoiceSampler;
use App\Support\Gemini\WelcomeMessage;
use App\Support\PrivateStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * « Diagnostic de la voix » : essai de la synthèse vocale, état des messages communs, préparation à l'avance
 * et derniers échecs. Jamais de clé affichée : seulement si elle est présente.
 */
class VoiceDiagnosticController extends Controller
{
    public function index(): View
    {
        return view('admin.voice-diagnostic.index', [
            'model' => (string) config('services.gemini.tts_model'),
            'voice' => (string) TeacherVoice::current(),
            'hasKey' => filled(config('services.gemini.key')),
            'disk' => PrivateStorage::name(),
            'welcome' => WelcomeMessage::status(),
            'levels' => LevelMessage::status(),
            'failures' => SpeechLog::recent(),
        ]);
    }

    /**
     * « Tester la synthèse vocale » : une phrase courte, puis l'écriture d'un petit fichier dans le stockage privé.
     */
    public function test(VoiceSampler $sampler): RedirectResponse
    {
        $started = hrtime(true);

        try {
            $wav = $sampler->synthesize(
                (string) TeacherVoice::current(),
                'Bonjour, je suis '.config('avatar.model.name').'.',
                (string) config('teacher.welcome.style'),
                'Diagnostic : phrase d\'essai',
                (int) config('services.gemini.tts_listen_timeout'),
            );
            $seconds = round((hrtime(true) - $started) / 1e9, 1);
            $path = 'voice-diagnostic/essai.wav';
            VoiceSampler::store($path, $wav, 'Diagnostic : écriture');
            PrivateStorage::disk()->delete($path);
        } catch (SpeechFailed $e) {
            return back()->with('diagnostic', [
                'ok' => false,
                'text' => 'Échec : '.$e->reason().($e->status ? ' (code HTTP '.$e->status.')' : '').'. '.$e->getMessage(),
            ]);
        }

        return back()->with('diagnostic', [
            'ok' => true,
            'text' => 'Réussi : la phrase d\'essai a été générée en '.number_format($seconds, 1, ',', ' ').($seconds >= 2 ? ' secondes (' : ' seconde (').number_format(strlen($wav) / 1024, 0, ',', ' ').' Ko) et l\'écriture dans le stockage privé fonctionne.',
        ]);
    }

    /**
     * « Préparer le message de bienvenue » : les morceaux communs manquants, dans le temps d'une requête.
     */
    public function prepare(SpeechPreparer $preparer): RedirectResponse
    {
        $budget = (int) config('services.gemini.tts_web_budget');
        @set_time_limit($budget + (int) config('services.gemini.tts_timeout') + 15);

        $result = $preparer->run($budget);
        $complete = $result['welcome']['ready'] === $result['welcome']['total'] && $result['levels']['ready'] === $result['levels']['total'];

        return back()->with('diagnostic', ['ok' => $complete, 'text' => SpeechPreparer::summary($result)]);
    }
}
