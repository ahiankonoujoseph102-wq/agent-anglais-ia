<?php

namespace Tests\Concerns;

use App\Enums\Level;
use App\Models\Program;
use App\Models\User;
use App\Support\Curriculum\ProgramBuilder;

trait BuildsPrograms
{
    /**
     * Construit un programme comme après le questionnaire (sans appel au modèle texte).
     *
     * @param  array<string, mixed>  $answers
     */
    protected function buildProgram(User $user, ?Level $level = null, array $answers = []): Program
    {
        $key = config('services.gemini.key');
        config(['services.gemini.key' => null]);

        $program = app(ProgramBuilder::class)->build($user, $level ?? $user->level ?? Level::A1, $answers + [
            'goal' => 'work',
            'occupation' => 'Comptable',
            'interests' => 'football',
            'study_days' => [1, 2, 3, 4, 5, 6, 7],
            'study_time' => '18:00',
            'difficulties' => ['speaking', 'pronunciation'],
        ]);

        config(['services.gemini.key' => $key]);

        return $program;
    }
}
