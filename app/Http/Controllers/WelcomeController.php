<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Avatar;
use App\Support\Gemini\WelcomeMessage;
use App\Support\Greeting;
use App\Support\Speech\SpeechProvider;
use App\Support\Speech\SpeechProviders;
use App\Support\WelcomeFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Page de bienvenue de Teacher Joe, à la première ouverture d'un accès : message vocal qui explique la
 * formule, le questionnaire, la séance orale et « Mes cours ». Montrée automatiquement une seule fois,
 * réécoutable depuis « Mon espace » (?reecouter=1) par un apprenant qui a eu un accès.
 */
class WelcomeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (WelcomeFlow::due($user)) {
            WelcomeFlow::markSeen($user);
        } elseif (! $request->boolean('reecouter')) {
            return to_route('dashboard');
        }

        // Réécoute : rien n'est marqué « vu » ; sans aucun accès, rien à réécouter.
        $texts = WelcomeMessage::texts($user);

        if ($texts === null) {
            return to_route('dashboard');
        }

        // UNE seule voix pour tout le message : le fournisseur chez qui il est prêt dit aussi la salutation.
        $message = WelcomeMessage::message($texts);
        $files = $message->files();
        $provider = $message->provider($files);
        $pieces = [];

        for ($index = 0, $count = WelcomeMessage::count($texts); $index < $count; $index++) {
            $text = WelcomeMessage::piece($texts, $index);
            $pieces[] = [
                'text' => $text,
                'url' => route('welcome.audio', ['index' => $index, 'salut' => $texts['period'], 'voix' => $provider?->name()]),
                // La salutation est générée à l'écoute ; un paragraphe pas encore préparé chez ce fournisseur reste en texte.
                'ready' => $provider !== null && ($index === 0 || isset($files[WelcomeMessage::path($text, $provider)])),
            ];
        }

        // Bouton de fin : le questionnaire, ou la séance si le programme est déjà construit.
        $next = $user->activeProgram()->exists()
            ? ['label' => 'Parler avec '.config('avatar.model.name'), 'url' => route('lesson.show'), 'icon' => 'mic']
            : ['label' => 'Construire mon programme', 'url' => route('program.questionnaire', 1), 'icon' => 'arrow-right'];

        return view('welcome.show', [
            'firstName' => $texts['first_name'],
            'planName' => $texts['plan'],
            'pieces' => $pieces,
            'next' => $next,
            'teacher3d' => Avatar::enabled() ? Avatar::clientConfig() + ['scene' => ['mode' => 'brand']] : null,
        ]);
    }

    /**
     * Un morceau du message (0 : ouverture, 1 et suivants : paragraphes), en WAV, chez le fournisseur du message.
     * Seule l'ouverture (propre au prénom) est générée à l'écoute ; les paragraphes communs sont préparés à l'avance.
     */
    public function audio(Request $request, int $index, WelcomeMessage $messages): Response
    {
        /** @var User $user */
        $user = $request->user();
        $period = (string) $request->query('salut');
        $texts = WelcomeMessage::texts($user, Greeting::isPeriod($period) ? $period : null);
        $text = $texts !== null ? WelcomeMessage::piece($texts, $index) : null;

        abort_if($text === null, 404);

        // Le fournisseur choisi par la page (toute la page joue la même voix), sinon celui du message.
        $provider = in_array($request->query('voix'), array_map(fn (SpeechProvider $provider) => $provider->name(), SpeechProviders::configured()), true)
            ? SpeechProviders::find((string) $request->query('voix'))
            : WelcomeMessage::providerFor($texts);

        $wav = $provider !== null ? $messages->get($text, $provider, $index === 0) : null;

        if ($wav === null) {
            return response('Message vocal indisponible.', 503, ['Retry-After' => WelcomeMessage::RETRY_AFTER]);
        }

        return response($wav, 200, [
            'Content-Type' => 'audio/wav',
            'Content-Length' => (string) strlen($wav),
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
