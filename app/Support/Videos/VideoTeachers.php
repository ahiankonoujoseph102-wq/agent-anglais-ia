<?php

namespace App\Support\Videos;

use App\Support\Teachers\Teacher;
use App\Support\Teachers\Teachers;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Professeur des vidéos de leçon d'un jour (promotion ET Club) : chaque professeur proposé à tour de rôle, un jour
 * chacun (Teacher Joe un jour, Teacher Rose le lendemain). Toujours le même nombre de vidéos : aucune minute de
 * GitHub en plus. Un seul professeur proposé (retour à Teacher Joe seul) : toujours lui.
 */
class VideoTeachers
{
    /** Jour de référence : Teacher Joe (premier de la liste). */
    private const REFERENCE = '2026-10-30';

    public static function forDay(CarbonInterface $day): Teacher
    {
        $teachers = array_values(Teachers::choosable());

        if (count($teachers) < 2) {
            return Teachers::default();
        }

        $days = (int) Carbon::parse(self::REFERENCE)->startOfDay()->diffInDays($day->copy()->startOfDay(), false);

        return $teachers[(($days % count($teachers)) + count($teachers)) % count($teachers)];
    }

    /**
     * Consignes d'écriture d'un script, accordées au professeur : une professeure parle d'elle au féminin (« je suis
     * ravie »). Teacher Joe : consignes inchangées.
     */
    public static function instructions(string $instructions, Teacher $teacher): string
    {
        if ($teacher->pronoun() !== 'elle') {
            return $instructions;
        }

        return strtr($instructions, [' he follows' => ' she follows', 'He speaks as himself' => 'She speaks as herself'])
            ."\n\nTHE TEACHER IS A WOMAN: ".$teacher->name().' always uses the feminine for herself in French (« je suis ravie », « je suis contente », « votre professeure »).';
    }
}
