<?php

namespace App\Http\Requests\Live;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Signal régulier du navigateur, avec les jetons consommés depuis le précédent.
 */
class SignalRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'input_tokens' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'output_tokens' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ];
    }

    public function inputTokens(): int
    {
        return (int) $this->validated('input_tokens', 0);
    }

    public function outputTokens(): int
    {
        return (int) $this->validated('output_tokens', 0);
    }
}
