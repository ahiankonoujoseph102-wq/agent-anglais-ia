<?php

namespace App\Http\Requests\Live;

use App\Enums\Skill;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bilan structuré transmis par le professeur en fin de séance (fonction « end_lesson »).
 */
class LessonReportRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'lesson_status' => ['required', 'in:completed,repeat'],
            'summary' => ['required', 'string', 'max:4000'],
            'homework' => ['nullable', 'string', 'max:2000'],
            'errors' => ['nullable', 'array', 'max:20'],
            'errors.*.wrong' => ['required', 'string', 'max:255'],
            'errors.*.correct' => ['required', 'string', 'max:255'],
            'errors.*.explanation' => ['nullable', 'string', 'max:500'],
            'reviewed_errors' => ['nullable', 'array', 'max:20'],
            'reviewed_errors.*.id' => ['required', 'integer'],
            'reviewed_errors.*.corrected' => ['required', 'boolean'],
            'scores' => ['required', 'array'],
        ];

        foreach (Skill::cases() as $skill) {
            $rules['scores.'.$skill->value] = ['required', 'integer', 'between:1,5'];
        }

        return $rules;
    }
}
