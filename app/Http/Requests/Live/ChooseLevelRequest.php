<?php

namespace App\Http\Requests\Live;

use App\Enums\Level;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChooseLevelRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'level' => ['required', Rule::enum(Level::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['level.required' => 'Choisissez un niveau.'];
    }

    public function level(): Level
    {
        return Level::from($this->validated('level'));
    }
}
