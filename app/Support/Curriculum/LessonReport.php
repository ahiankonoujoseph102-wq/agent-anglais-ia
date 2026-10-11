<?php

namespace App\Support\Curriculum;

use App\Enums\ErrorStatus;
use App\Enums\ProgramLessonStatus;
use App\Enums\ProgramStatus;
use App\Enums\Skill;
use App\Models\LearnerError;
use App\Models\Lesson;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre le bilan structuré transmis par le professeur en fin de séance
 * (fonction « end_lesson ») : leçon terminée ou à reprendre, notes, erreurs, devoir.
 */
class LessonReport
{
    /**
     * @param  array{lesson_status: string, summary: string, homework?: string|null, scores: array<string, int>, errors?: list<array{wrong: string, correct: string, explanation?: string|null}>, reviewed_errors?: list<array{id: int, corrected: bool}>}  $report
     */
    public static function record(Lesson $lesson, array $report): void
    {
        DB::transaction(function () use ($lesson, $report) {
            $user = $lesson->user;

            $lesson->summary = $report['summary'];
            $lesson->homework = $report['homework'] ?? null;
            $lesson->outcome = $report['lesson_status'];
            $lesson->reported_at = now();

            foreach (Skill::cases() as $skill) {
                $lesson->{$skill->column()} = $report['scores'][$skill->value] ?? null;
            }

            $lesson->save();

            // Révision des erreurs : deux corrections de suite la retirent de la liste.
            foreach ($report['reviewed_errors'] ?? [] as $review) {
                $error = $user->learnerErrors()
                    ->where('status', ErrorStatus::Open)
                    ->find($review['id']);

                $error?->review((bool) $review['corrected']);
            }

            // Nouvelles erreurs : une erreur déjà connue revient au début de sa série.
            foreach ($report['errors'] ?? [] as $new) {
                $known = $user->openErrors()->get()
                    ->first(fn (LearnerError $error) => LearnerError::normalize($error->wrong) === LearnerError::normalize($new['wrong']));

                if ($known) {
                    $known->update(['correct_streak' => 0, 'times_seen' => $known->times_seen + 1, 'correct' => $new['correct']]);

                    continue;
                }

                $user->learnerErrors()->create([
                    'lesson_id' => $lesson->id,
                    'wrong' => $new['wrong'],
                    'correct' => $new['correct'],
                    'explanation' => $new['explanation'] ?? null,
                ]);
            }

            self::advanceProgram($lesson);
        });
    }

    /**
     * Leçon terminée : on passe à la suivante. Toutes terminées : bilan de fin de niveau.
     */
    private static function advanceProgram(Lesson $lesson): void
    {
        $programLesson = $lesson->programLesson;

        if ($programLesson === null || $programLesson->isCompleted()) {
            return;
        }

        $programLesson->attempts++;

        if ($lesson->outcome === 'completed') {
            $programLesson->status = ProgramLessonStatus::Completed;
            $programLesson->completed_at = now();
        }

        $programLesson->save();

        $program = $programLesson->program;

        if (! $program->programLessons()->where('status', ProgramLessonStatus::Pending)->exists()) {
            $program->update(['status' => ProgramStatus::LevelTestDue]);

            return;
        }

        // Le calendrier suit le rythme réel : les leçons restantes repartent du prochain jour de travail.
        $program->reschedule(today()->addDay());
    }
}
