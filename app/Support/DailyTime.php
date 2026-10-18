<?php

namespace App\Support;

/**
 * Temps du jour d'une formule, partagé entre la séance orale quotidienne et « Mes cours »
 * (config/platform.php → plans.*.daily_minutes et daily_time). Calcul : User::remainingSecondsToday().
 */
final class DailyTime
{
    /** Message quand il reste moins que le seuil pour ouvrir une séance. */
    public const EXHAUSTED = 'Votre temps du jour est épuisé : il est partagé entre la séance orale et « Mes cours », et repart à zéro à minuit. À demain !';

    /**
     * En dessous de ce temps restant aujourd'hui, aucune nouvelle séance n'est ouverte.
     */
    public static function minStartSeconds(): int
    {
        return (int) config('platform.daily_time.min_start_seconds');
    }

    /**
     * « Il vous reste 9 minutes aujourd'hui. » (minutes entières, jamais plus que le temps réel).
     */
    public static function remainingSentence(int $seconds): string
    {
        return match (true) {
            $seconds <= 0 => 'Il ne vous reste plus de temps aujourd\'hui.',
            $seconds < 60 => 'Il vous reste moins d\'une minute aujourd\'hui.',
            default => 'Il vous reste '.Duration::format($seconds).' aujourd\'hui.',
        };
    }
}
