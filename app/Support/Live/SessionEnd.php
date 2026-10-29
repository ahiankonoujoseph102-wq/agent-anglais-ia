<?php

namespace App\Support\Live;

use App\Support\Gemini\TeacherSetup;

/**
 * Fin de la séance du jour et de « Mes cours », réglages donnés au navigateur (public/js/session-closing.js) :
 * vers une minute de la fin, Teacher Joe l'annonce ; vers 40 secondes, UNE conclusion qui finit par « À demain ! » ;
 * la séance se ferme quand elle est dite. Tout part du TEMPS RESTANT calculé par le site, jamais du micro.
 */
final class SessionEnd
{
    /**
     * @param  string  $kind  lesson ou course
     * @param  int  $allowedSeconds  durée accordée à la séance
     * @return array<string, mixed>
     */
    public static function client(string $kind, int $allowedSeconds): array
    {
        $end = (array) config('platform.live.session_end');
        $conclude = (int) $end['conclude_seconds'];

        return [
            'total' => $allowedSeconds,
            'announce_seconds' => (int) $end['announce_seconds'],
            'conclude_seconds' => $conclude,
            'latest_seconds' => (int) $end['latest_seconds'],
            'grace_seconds' => (int) $end['grace_seconds'],
            'overtime_seconds' => (int) $end['overtime_seconds'],
            'silent_end_seconds' => (int) $end['silent_end_seconds'],
            'closing_phrase' => (string) $end['closing_phrase'],
            'announce' => TeacherSetup::cue('session_announce'),
            'conclude' => TeacherSetup::cue($kind === 'lesson' ? 'lesson_conclude' : 'course_conclude', ['seconds' => $conclude]),
        ];
    }
}
