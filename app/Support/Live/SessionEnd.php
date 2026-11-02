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
     * @param  string  $note  ajouté à l'indication de conclusion (invitation à laisser un avis), vide le plus souvent
     * @return array<string, mixed>
     */
    public static function client(string $kind, int $allowedSeconds, string $note = ''): array
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
            'conclude' => TeacherSetup::cue($kind === 'lesson' ? 'lesson_conclude' : 'course_conclude', ['seconds' => $conclude]).$note,
            // Séance du jour : rappel de télécharger la vidéo si l'élève enregistre (ajouté par le navigateur), et où
            // retrouver le devoir et la révision (écrit sous le devoir sur l'écran de fin).
            'recording' => $kind === 'lesson' ? TeacherSetup::cue('recording_conclude') : '',
            'find_note' => $kind === 'lesson' ? (string) config('platform.lessons.find_note') : '',
        ];
    }
}
