<?php

namespace App\Support;

use Carbon\CarbonInterface;

class Duration
{
    /**
     * Date et heure sans abréviation : « 2 novembre 2026 à 11 heures 15 », « 3 novembre à 9 heures » (sans l'année).
     */
    public static function at(CarbonInterface $at, bool $year = true): string
    {
        return $at->translatedFormat($year ? 'j F Y' : 'j F').' à '.Reminders\LessonReminders::timeLabel($at->format('H:i'));
    }

    /**
     * Formate une durée en secondes pour l'affichage, en toutes lettres :
     * « 12 minutes », « 1 heure 5 minutes ».
     */
    public static function format(int $seconds): string
    {
        $minutes = intdiv(max(0, $seconds), 60);

        if ($minutes < 60) {
            return self::unit($minutes, 'minute');
        }

        $hours = self::unit(intdiv($minutes, 60), 'heure');

        return $minutes % 60 === 0 ? $hours : $hours.' '.self::unit($minutes % 60, 'minute');
    }

    /**
     * Durée à la seconde près : « 45 secondes », « 4 minutes 12 secondes », « 1 heure 5 minutes ».
     */
    public static function formatPrecise(int $seconds): string
    {
        $seconds = max(0, $seconds);

        if ($seconds < 60) {
            return self::unit($seconds, 'seconde');
        }

        if ($seconds < 3600) {
            $rest = $seconds % 60;

            return self::unit(intdiv($seconds, 60), 'minute').($rest ? ' '.self::unit($rest, 'seconde') : '');
        }

        return self::format($seconds);
    }

    // Accord en français : « 0 minute », « 1 minute », « 2 minutes ».
    private static function unit(int $count, string $word): string
    {
        return $count.' '.$word.($count >= 2 ? 's' : '');
    }
}
