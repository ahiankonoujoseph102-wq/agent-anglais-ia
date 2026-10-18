<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Avatar;
use App\Support\Gemini\WelcomeMessage;
use App\Support\Greeting;
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

        $pieces = [];

        for ($index = 0, $count = WelcomeMessage::count($texts); $index < $count; $index++) {
            $pieces[] = [
                'text' => WelcomeMessage::piece($texts, $index),
                'url' => route('welcome.audio', ['index' => $index, 'salut' => $texts['period']]),
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
     * Un morceau du message (0 : ouverture, 1 et suivants : paragraphes), en WAV. Généré à la demande
     * la première fois, puis gardé : les paragraphes sont communs à tous, seule l'ouverture est propre au prénom.
     */
    public function audio(Request $request, int $index, WelcomeMessage $messages): Response
    {
        /** @var User $user */
        $user = $request->user();
        $period = (string) $request->query('salut');
        $texts = WelcomeMessage::texts($user, Greeting::isPeriod($period) ? $period : null);
        $text = $texts !== null ? WelcomeMessage::piece($texts, $index) : null;

        abort_if($text === null, 404);

        $wav = $messages->get($text, $index === 0);

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
