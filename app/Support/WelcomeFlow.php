<?php

namespace App\Support;

use App\Models\User;
use App\Support\Admin\ViewAs;
use Illuminate\Http\RedirectResponse;

/**
 * Quand montrer automatiquement la page de bienvenue de Teacher Joe : une seule fois, à la première
 * ouverture d'un accès (accès provisoire de 24 heures, paiement validé ou accès offert ; paiement en
 * attente : à la première visite après la validation). Les pages concernées appellent redirect() en arrivant.
 */
final class WelcomeFlow
{
    public static function due(User $user): bool
    {
        // L'administration et les comptes qui doivent d'abord changer leur mot de passe sont laissés tranquilles.
        // « Voir son espace » de l'administration (lecture seule) : la bienvenue n'est jamais ouverte ni marquée « vue ».
        if ($user->isAdmin() || $user->must_change_password || ViewAs::active()) {
            return false;
        }

        // Séance d'essai gratuite : son propre message, vu une fois (trial_welcomed_at) ; celui de la première formule
        // payée reste à voir ensuite (welcomed_at).
        $subscription = $user->activeSubscription()->first();

        if ($subscription === null) {
            return false;
        }

        return ($subscription->isTrial() ? $user->trial_welcomed_at : $user->welcomed_at) === null;
    }

    public static function redirect(User $user): ?RedirectResponse
    {
        if (! self::due($user)) {
            return null;
        }

        // Le message de la page d'origine (« Accès provisoire ouvert… ») reste pour la page suivante.
        session()->reflash();

        return to_route('welcome.show');
    }

    /**
     * Noté « vu » : la page ne se rouvre plus toute seule (elle reste réécoutable depuis « Mon espace »).
     */
    public static function markSeen(User $user): void
    {
        if (ViewAs::active()) {
            return;
        }

        $column = $user->activeSubscription()->first()?->isTrial() ? 'trial_welcomed_at' : 'welcomed_at';

        if ($user->{$column} === null) {
            $user->forceFill([$column => now()])->save();
        }
    }
}
