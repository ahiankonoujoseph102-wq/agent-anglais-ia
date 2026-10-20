<?php

namespace App\Support\Curriculum;

use App\Enums\ProgramLessonStatus;
use App\Enums\ProgramStatus;
use App\Models\Attestation;
use App\Models\LevelTest;
use App\Models\Program;
use Illuminate\Support\Facades\DB;

/**
 * Conséquences du bilan oral de fin de niveau.
 * Réussi : attestation, niveau suivant, nouveau programme. Échoué : unités à reprendre.
 */
class LevelProgression
{
    public function __construct(private ProgramBuilder $builder) {}

    /**
     * @param  list<int>  $unitsToReview
     */
    public function record(LevelTest $test, bool $passed, string $feedback, array $unitsToReview = []): ?Attestation
    {
        return DB::transaction(function () use ($test, $passed, $feedback, $unitsToReview) {
            $test->passed = $passed;
            $test->feedback = $feedback;
            $test->units_to_review = $passed ? null : array_values(array_unique(array_map('intval', $unitsToReview)));
            $test->save();

            $program = $test->program;
            $user = $test->user;

            if (! $passed) {
                $this->reopenUnits($program, $test->units_to_review);

                return null;
            }

            $attestation = $user->attestations()->firstOrCreate(
                ['level' => $test->level],
                ['level_test_id' => $test->id, 'code' => Attestation::newCode($test->level), 'issued_at' => now()],
            );

            $program->update(['status' => ProgramStatus::Completed, 'is_active' => false]);

            $next = $test->level->next();

            if ($next !== null) {
                $user->level = $next;
                $user->save();

                // Le programme du niveau suivant reprend les réponses au questionnaire.
                $this->builder->build($user, $next, [
                    'goal' => $program->goal->value,
                    'profile' => $program->profile?->value,
                    'occupation' => $program->occupation,
                    'interests' => $program->interests,
                    'study_days' => $program->study_days,
                    'study_time' => $program->study_time,
                    'difficulties' => $program->difficulties,
                ]);
            }

            return $attestation;
        });
    }

    /**
     * Les leçons des unités à reprendre redeviennent « à faire » et le calendrier repart.
     *
     * @param  list<int>  $units
     */
    private function reopenUnits(Program $program, array $units): void
    {
        $program->programLessons()
            ->whereIn('unit_number', $units)
            ->update(['status' => ProgramLessonStatus::Pending, 'completed_at' => null]);

        $program->update(['status' => ProgramStatus::Active]);
        $program->reschedule(today()->addDay());
    }
}
