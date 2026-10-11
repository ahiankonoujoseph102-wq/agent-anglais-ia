<?php

namespace App\Http\Requests\Live;

use App\Support\Curriculum\Curriculum;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Résultat du bilan de fin de niveau (fonction « record_level_test_result »).
 */
class LevelTestResultRequest extends FormRequest
{
    /**
     * En cas de réussite, la liste des unités à reprendre n'a pas de sens.
     */
    protected function prepareForValidation(): void
    {
        if (filter_var($this->input('passed'), FILTER_VALIDATE_BOOLEAN)) {
            $this->merge(['units_to_review' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'passed' => ['required', 'boolean'],
            'feedback' => ['required', 'string', 'max:2000'],
            // En cas d'échec, le professeur doit indiquer au moins une unité à reprendre.
            'units_to_review' => ['nullable', 'array', 'required_if:passed,false,0', 'min:1'],
            'units_to_review.*' => ['integer', 'between:1,'.Curriculum::UNITS_PER_LEVEL],
        ];
    }
}
