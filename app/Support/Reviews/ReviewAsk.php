<?php

namespace App\Support\Reviews;

use App\Models\User;
use App\Support\Gemini\TeacherSetup;

/**
 * Le professeur invite à laisser un avis dans la conclusion de CHAQUE séance du jour et de chaque séance « Mes cours »
 * (séance d'essai comprise : platform.reviews.teacher_asks), tant que l'élève n'a pas laissé d'avis. Une seule phrase,
 * en français, qui dit où le laisser (« Mon espace », « Donner mon avis ») et les minutes offertes. Jamais pendant la
 * leçon, jamais à l'évaluation gratuite (la page de résultat l'invite), jamais une demande de bonne note.
 */
final class ReviewAsk
{
    public static function note(User $user): string
    {
        if (! config('platform.reviews.teacher_asks') || $user->isStaff() || $user->review()->exists()) {
            return '';
        }

        return TeacherSetup::cue('review_ask', ['minutes' => (string) intdiv(TimeBonuses::seconds(), 60)]);
    }
}
