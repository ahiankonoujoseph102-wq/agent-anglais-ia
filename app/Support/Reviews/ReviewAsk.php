<?php

namespace App\Support\Reviews;

use App\Models\Lesson;
use App\Models\User;
use App\Support\Gemini\TeacherSetup;
use App\Support\Live\WorkingTogether;

/**
 * Le professeur invite à laisser un avis UNE seule fois : dans la conclusion de la 3e séance du jour tenue
 * (platform.reviews.teacher_ask_after_lessons), seulement si l'élève n'a pas encore laissé d'avis. Jamais pendant la
 * leçon, jamais à l'évaluation gratuite (la page de résultat l'invite), jamais une demande de bonne note.
 */
final class ReviewAsk
{
    public static function note(User $user, Lesson $lesson): string
    {
        $after = (int) config('platform.reviews.teacher_ask_after_lessons');

        if ($after < 1 || $user->isStaff() || $user->review()->exists()) {
            return '';
        }

        $held = $user->lessons()
            ->whereKeyNot($lesson->id)
            ->where('used_seconds', '>=', WorkingTogether::heldSeconds())
            ->count();

        return $held === $after - 1
            ? TeacherSetup::cue('review_ask', ['minutes' => (string) intdiv(TimeBonuses::seconds(), 60)])
            : '';
    }
}
