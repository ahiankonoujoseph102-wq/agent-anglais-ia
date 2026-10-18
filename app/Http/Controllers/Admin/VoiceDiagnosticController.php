<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Gemini\SpeechFailed;
use App\Support\Gemini\SpeechLog;
use App\Support\Gemini\SpeechPreparer;
use App\Support\Gemini\VoiceSampler;
use App\Support\PrivateStorage;
use App\Support\Speech\SpeechProvider;
use App\Support\Speech\SpeechProviders;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * « Diagnostic de la voix » : chaque fournisseur de synthèse vocale (clé présente ou non, modèle, voix, essai),
 * état des messages communs chez chacun et fournisseur qui jouera chaque message, préparation à l'avance et
 * derniers échecs. Jamais de clé affichée : seulement si elle est présente.
 */
class VoiceDiagnosticController extends Controller
{
    public function index(): View
    {
        $configured = array_map(fn (SpeechProvider $provider) => $provider->name(), SpeechProviders::configured());

        return view('admin.voice-diagnostic.index', [
            'providers' => SpeechProviders::all(),
            'order' => $configured,
            'disk' => PrivateStorage::name(),
            'status' => SpeechPreparer::status(),
            'failures' => SpeechLog::recent(),
        ]);
    }

    /**
     * « Tester » un fournisseur : une phrase courte, puis l'écriture d'un petit fichier dans le stockage privé.
     */
    public function test(string $provider): RedirectResponse
    {
        $provider = SpeechProviders::find($provider);
        abort_if($provider === null, 404);
        $started = hrtime(true);

        try {
            $wav = $provider->synthesize(
                'Bonjour, je suis '.config('avatar.model.name').'.',
                (string) config('teacher.welcome.style'),
                'Diagnostic : phrase d\'essai',
                (int) config('services.gemini.tts_listen_timeout'),
            );
            $seconds = round((hrtime(true) - $started) / 1e9, 1);
            $path = 'voice-diagnostic/essai.wav';
            VoiceSampler::store($path, $wav, 'Diagnostic : écriture', $provider);
            PrivateStorage::disk()->delete($path);
        } catch (SpeechFailed $e) {
            return back()->with('diagnostic', [
                'ok' => false,
                'text' => $provider->label().' : échec : '.$e->reason().($e->status ? ' (code HTTP '.$e->status.')' : '').'. '.$e->getMessage(),
            ]);
        }

        return back()->with('diagnostic', [
            'ok' => true,
            'text' => $provider->label().' : réussi : la phrase d\'essai a été générée en '.number_format($seconds, 1, ',', ' ').($seconds >= 2 ? ' secondes (' : ' seconde (').number_format(strlen($wav) / 1024, 0, ',', ' ').' Ko) avec la voix « '.$provider->voice().' », et l\'écriture dans le stockage privé fonctionne.',
        ]);
    }

    /**
     * « Préparer le message de bienvenue » : les messages communs incomplets, dans le temps d'une requête.
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
