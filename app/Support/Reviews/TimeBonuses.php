<?php

namespace App\Support\Reviews;

use App\Models\TimeBonus;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * Minutes offertes (merci pour un avis) : une seule fois par compte et par raison. Elles s'ajoutent au temps du jour
 * (User::remainingSecondsToday), sans le remplacer : pas encore attachées à un jour, elles comptent dans le temps du
 * jour d'aujourd'hui ; la première séance ouverte avec un accès les attache à ce jour-là (pin). Un élève sans accès en
 * cours (évaluation gratuite) les garde pour le jour de sa première séance.
 */
final class TimeBonuses
{
    public static function seconds(): int
    {
        return max(0, (int) config('platform.reviews.bonus_minutes')) * 60;
    }

    /** Offre les minutes une seule fois (l'index unique l'assure, même en cas de double envoi). */
    public static function grant(User $user, string $reason = TimeBonus::REVIEW): ?TimeBonus
    {
        if (self::seconds() === 0 || $user->timeBonuses()->where('reason', $reason)->exists()) {
            return null;
        }

        try {
            return $user->timeBonuses()->create(['reason' => $reason, 'seconds' => self::seconds()]);
        } catch (QueryException) {
            return null;
        }
    }

    /** Une NOUVELLE séance s'ouvre : les minutes pas encore utilisées sont attachées à aujourd'hui. */
    public static function pin(User $user): void
    {
        // Séance d'essai gratuite : les minutes offertes restent pour la première séance avec une vraie formule.
        if ($user->activeSubscription?->isTrial()) {
            return;
        }

        $user->timeBonuses()->whereNull('applied_on')->update(['applied_on' => today()]);
    }

    /** Minutes offertes pas encore attachées à un jour (affichage : « + 5 minutes offertes »). */
    public static function pendingSeconds(User $user): int
    {
        return (int) $user->timeBonuses()->whereNull('applied_on')->sum('seconds');
    }
}
