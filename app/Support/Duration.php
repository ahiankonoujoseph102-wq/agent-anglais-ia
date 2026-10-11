<?php

namespace App\Support;

class Duration
{
    /**
     * Formate une durée en secondes pour l'affichage : « 12 min », « 1 h 05 min ».
     */
    public static function format(int $seconds): string
    {
        $minutes = intdiv(max(0, $seconds), 60);

        if ($minutes < 60) {
            return $minutes.' min';
        }

        return sprintf('%d h %02d min', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * Durée à la seconde près : « 45 s », « 4 min 12 s », « 1 h 05 min ».
     */
    public static function formatPrecise(int $seconds): string
    {
        $seconds = max(0, $seconds);

        if ($seconds < 60) {
            return $seconds.' s';
        }

        if ($seconds < 3600) {
            return sprintf('%d min %02d s', intdiv($seconds, 60), $seconds % 60);
        }

        return self::format($seconds);
    }
}
