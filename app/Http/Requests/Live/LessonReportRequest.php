<?php

namespace App\Http\Requests\Live;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bilan de séance rédigé par le professeur (fonction « end_lesson »).
 */
class LessonReportRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'summary' => ['required', 'string', 'max:4000'],
            'homework' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
