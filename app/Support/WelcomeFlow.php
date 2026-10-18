<?php

namespace App\Support;

use App\Models\User;
use App\Support\Gemini\WelcomeMessage;
use Illuminate\Http\RedirectResponse;

/**
 * Quand montrer automatiquement la page de bienvenue de Teacher Joe : une seule fois après l'inscription,
 * puis une seule fois quand un accès devient actif (accès provisoire de 24 heures, paiement validé
 * ou accès offert). Les pages concernées appellent redirect() en arrivant.
 */
final class WelcomeFlow
{
    /**
     * Le message à montrer maintenant (WelcomeMessage::SIGNUP ou ::PLAN), ou null.
     */
    public static function due(User $user): ?string
    {
        // L'administration et les comptes qui doivent d'abord changer leur mot de passe sont laissés tranquilles.
        if ($user->isAdmin() || $user->must_change_password) {
            return null;
        }

        if ($user->welcomed_at === null) {
            return WelcomeMessage::SIGNUP;
        }

        if ($user->access_welcomed_at === null && $user->activeSubscription()->exists()) {
            return WelcomeMessage::PLAN;
        }

        return null;
    }

    public static function redirect(User $user): ?RedirectResponse
    {
        return self::due($user) !== null ? to_route('welcome.show') : null;
    }

    /**
     * Noté « vu » : la page ne se rouvre plus toute seule (elle reste réécoutable depuis « Mon espace »).
     */
    public static function markSeen(User $user, string $kind): void
    {
        $column = $kind === WelcomeMessage::PLAN ? 'access_welcomed_at' : 'welcomed_at';

        if ($user->{$column} === null) {
            $user->forceFill([$column => now()])->save();
        }
    }
}
