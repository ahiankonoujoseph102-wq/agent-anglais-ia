<?php

namespace App\Support\Admin;

use App\Models\TimeBonus;
use App\Models\User;

/**
 * Minutes rendues par l'administrateur à un élève qui n'a pas pu faire sa séance (panne, coupure) : ajoutées au
 * temps du JOUR MÊME, sans le remplacer (même mécanisme que les minutes offertes : TimeBonus attaché à aujourd'hui,
 * compté par User::remainingSecondsToday). Plusieurs dons le même jour s'additionnent ; le lendemain, plus rien
 * (le temps du jour revient de lui-même à minuit). Jamais pour une séance d'essai gratuite (son temps est à part).
 */
final class RestoredMinutes
{
    public const MAX_MINUTES = 60;

    public static function reason(): string
    {
        return 'restored:'.today()->toDateString();
    }

    /** Raison pour laquelle on ne peut pas rendre de minutes, ou null. */
    public static function refusal(User $user): ?string
    {
        $subscription = $user->activeSubscription;

        if ($subscription === null) {
            return 'Cet élève n\'a aucun accès en cours : il n\'a pas de temps du jour.';
        }

        if ($subscription->isTrial()) {
            return 'Pendant une séance d\'essai gratuite, le temps est compté à part : il ne peut pas être rendu ici.';
        }

        return null;
    }

    /** Ajoute des minutes au temps d'aujourd'hui (s'ajoutent à celles déjà rendues aujourd'hui). */
    public static function give(User $user, int $minutes): TimeBonus
    {
        $minutes = max(1, min(self::MAX_MINUTES, $minutes));
        $bonus = $user->timeBonuses()->firstOrNew(['reason' => self::reason()]);
        $bonus->seconds = (int) $bonus->seconds + $minutes * 60;
        $bonus->applied_on = today();
        $bonus->save();

        return $bonus;
    }

    /** Minutes déjà rendues aujourd'hui. */
    public static function today(User $user): int
    {
        return intdiv((int) $user->timeBonuses()->where('reason', self::reason())->value('seconds'), 60);
    }
}
