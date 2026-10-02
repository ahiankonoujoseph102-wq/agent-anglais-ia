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
}
