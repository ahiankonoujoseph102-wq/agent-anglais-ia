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
 * Page de bienvenue de Teacher Joe : message vocal après l'inscription, puis court message
 * à la première ouverture d'un accès. Montrée automatiquement une seule fois, réécoutable depuis « Mon espace ».
 */
class WelcomeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $requested = (string) $request->query('message');

        if (WelcomeMessage::isKind($requested)) {
            // Réécoute à la demande : rien n'est marqué « vu ». Le message de la formule demande un accès actif.
            if ($requested === WelcomeMessage::PLAN && ! $user->activeSubscription()->exists()) {
                return to_route('dashboard');
            }

            $kind = $requested;
        } elseif (($due = WelcomeFlow::due($user)) !== null) {
            $kind = $due;
            WelcomeFlow::markSeen($user, $kind);
        } else {
            return to_route('dashboard');
        }

        $texts = WelcomeMessage::texts($user, $kind);
        $pieces = [];

        for ($index = 0, $count = WelcomeMessage::count($texts); $index < $count; $index++) {
            $pieces[] = [
                'text' => WelcomeMessage::piece($texts, $index),
                'url' => route('welcome.audio', ['kind' => $kind, 'index' => $index, 'salut' => $texts['period']]),
            ];
        }

        // Bouton de fin : la prochaine étape selon l'état du compte.
        if ($kind === WelcomeMessage::PLAN) {
            $next = $user->activeProgram()->exists()
                ? ['label' => 'Parler avec '.config('avatar.model.name'), 'url' => route('lesson.show'), 'icon' => 'mic']
                : ['label' => 'Construire mon programme', 'url' => route('program.questionnaire', 1), 'icon' => 'arrow-right'];
        } else {
            $next = $user->assessment()->exists()
                ? ['label' => 'Aller à mon espace', 'url' => route('dashboard'), 'icon' => 'home']
                : ['label' => 'Passer mon évaluation gratuite', 'url' => route('assessment.show'), 'icon' => 'mic'];
        }

        return view('welcome.show', [
            'kind' => $kind,
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
    public function audio(Request $request, string $kind, int $index, WelcomeMessage $messages): Response
    {
        abort_unless(WelcomeMessage::isKind($kind), 404);

        /** @var User $user */
        $user = $request->user();
        $period = (string) $request->query('salut');
        $texts = WelcomeMessage::texts($user, $kind, Greeting::isPeriod($period) ? $period : null);
        $text = WelcomeMessage::piece($texts, $index);

        abort_if($text === null || ($kind === WelcomeMessage::PLAN && $texts['plan'] === null), 404);

        $wav = $messages->get($text);

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
