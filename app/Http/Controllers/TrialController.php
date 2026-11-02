<?php

namespace App\Http\Controllers;

use App\Support\Admin\ViewAs;
use App\Support\Trial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ouvre la séance d'essai gratuite (une fois par compte, après l'évaluation), puis mène au questionnaire : quatre
 * petites questions, et la séance d'essai avec le professeur.
 */
class TrialController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (ViewAs::active()) {
            return to_route('dashboard');
        }

        if (Trial::claim($user) === null) {
            return to_route('dashboard')->with('status', $user->activeSubscription()->exists()
                ? 'Votre accès est déjà ouvert : continuez depuis « Mon espace ».'
                : 'La séance d\'essai gratuite n\'est plus disponible pour ce compte. Choisissez une formule pour continuer.');
        }

        // Message de bienvenue propre à l'essai (son professeur explique tout), puis questionnaire et séance.
        return to_route('welcome.show');
    }
}
