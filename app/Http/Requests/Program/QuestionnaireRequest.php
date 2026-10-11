<?php

namespace App\Http\Requests\Program;

use App\Enums\Difficulty;
use App\Enums\Goal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Une étape du questionnaire du programme personnel (1 à 4).
 */
class QuestionnaireRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return match ((int) $this->route('step')) {
            1 => ['goal' => ['required', Rule::enum(Goal::class)]],
            2 => [
                'occupation' => ['required', 'string', 'min:2', 'max:150'],
                'interests' => ['nullable', 'string', 'max:255'],
            ],
            3 => [
                'study_days' => ['required', 'array', 'min:1'],
                'study_days.*' => ['integer', 'between:1,7'],
                'study_time' => ['nullable', 'date_format:H:i'],
            ],
            default => [
                'difficulties' => ['required', 'array', 'min:1'],
                'difficulties.*' => [Rule::enum(Difficulty::class)],
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'goal.required' => 'Choisissez votre objectif principal.',
            'occupation.required' => 'Indiquez votre métier ou votre situation (par exemple : commerçante, étudiant, infirmier).',
            'study_days.required' => 'Choisissez au moins un jour de travail.',
            'study_time.date_format' => 'Indiquez une heure valide, par exemple 18:30.',
            'difficulties.required' => 'Choisissez au moins une difficulté.',
        ];
    }
}
