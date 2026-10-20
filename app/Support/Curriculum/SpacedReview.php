<?php

namespace App\Support\Curriculum;

use App\Models\Lesson;
use App\Models\Program;
use App\Models\ProgramLesson;
use App\Models\User;

/**
 * Révision espacée (docs/methode-teacher-joe.md) : quelques phrases clés des leçons faites il y a environ 1, 3 et
 * 7 jours (program.review_days), que Teacher Joe fait RETROUVER pendant la pratique guidée. Les phrases viennent des
 * tableaux déjà gardés (LessonBoards::for, tableau de secours sinon) : aucun appel au modèle texte.
 */
class SpacedReview
{
    /**
     * @return list<array{en: string, fr: ?string, days: int}>
     */
    public static function phrases(User $user, Program $program, ProgramLesson $current): array
    {
        $phrases = [];
        $seen = [$current->id];

        foreach ((array) config('program.review_days') as $days) {
            $lesson = Lesson::query()
                ->whereBelongsTo($user)
                ->where('program_id', $program->id)
                ->where('outcome', 'completed')
                ->whereNotNull('reported_at')
                ->whereNotNull('program_lesson_id')
                ->whereNotIn('program_lesson_id', $seen)
                ->whereDate('date', '<=', today()->subDays((int) $days))
                ->with('programLesson')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->first();

            if ($lesson?->programLesson === null) {
                continue;
            }

            $seen[] = $lesson->program_lesson_id;
            $lesson->programLesson->setRelation('program', $program);
            $board = LessonBoards::for($lesson->programLesson, $program);
            $age = (int) $lesson->date->diffInDays(today());
            // La veille : pas la première phrase, souvent déjà la « phrase à retenir » redemandée au début de la séance.
            $phrase = $board['phrases'][$age <= 1 ? 1 : 0] ?? $board['phrases'][0] ?? null;

            if ($phrase !== null) {
                $phrases[] = ['en' => $phrase['en'], 'fr' => $phrase['fr'] ?? null, 'days' => $age];
            }
        }

        return $phrases;
    }
}
