<?php

namespace App\Support;

use App\Models\User;
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
        if ($user->isAdmin() || $user->must_change_password || $user->welcomed_at !== null) {
            return false;
        }

        return $user->activeSubscription()->exists();
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
        if ($user->welcomed_at === null) {
            $user->forceFill(['welcomed_at' => now()])->save();
        }
    }
}
