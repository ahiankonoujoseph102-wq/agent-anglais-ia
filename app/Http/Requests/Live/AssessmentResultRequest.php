<?php

namespace App\Http\Requests\Live;

use App\Enums\Level;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssessmentResultRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'level' => ['required', Rule::enum(Level::class)],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function level(): Level
    {
        return Level::from($this->validated('level'));
    }

    public function feedback(): string
    {
        return trim((string) $this->validated('feedback', ''));
    }
}
