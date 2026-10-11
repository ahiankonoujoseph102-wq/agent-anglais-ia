<?php

namespace App\Http\Requests\Live;

use Illuminate\Foundation\Http\FormRequest;

class ConnectRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Identifiant de reprise fourni par Gemini, pour continuer la même conversation.
            'handle' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function handle(): ?string
    {
        return $this->validated('handle');
    }
}
