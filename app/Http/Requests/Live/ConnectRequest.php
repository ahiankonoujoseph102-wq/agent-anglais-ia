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
            // Fournisseur auquel le navigateur n'a pas pu se connecter avant la première parole.
            'failed' => ['nullable', 'string', 'in:gemini,openai'],
        ];
    }

    public function failedProvider(): ?string
    {
        return $this->validated('failed');
    }

    public function handle(): ?string
    {
        return $this->validated('handle');
    }
}
