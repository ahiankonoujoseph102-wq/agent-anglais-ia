<?php

namespace App\Http\Requests\Program;

use App\Enums\Difficulty;
use App\Enums\Goal;
use App\Enums\Profile;
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
            1 => [
                'goal' => ['required', Rule::enum(Goal::class)],
                // Autres objectifs : deux au plus, jamais l'objectif principal.
                'other_goals' => ['nullable', 'array', 'max:2'],
                'other_goals.*' => ['distinct', Rule::enum(Goal::class), Rule::notIn([(string) $this->input('goal')])],
            ],
            2 => [
                'profile' => ['nullable', Rule::enum(Profile::class)],
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
            'other_goals.max' => 'Choisissez deux autres objectifs au plus.',
            'other_goals.*.not_in' => 'Votre objectif principal est déjà choisi : cochez seulement d\'autres objectifs.',
            'occupation.required' => 'Indiquez votre métier ou votre situation (par exemple : commerçante, étudiant, infirmier).',
            'study_days.required' => 'Choisissez au moins un jour de travail.',
            'study_time.date_format' => 'Indiquez une heure valide, par exemple 18:30.',
            'difficulties.required' => 'Choisissez au moins une difficulté.',
        ];
    }
}
