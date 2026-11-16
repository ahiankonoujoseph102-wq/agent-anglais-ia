<?php

namespace App\Http\Requests\Live;

use App\Support\Live\LiveProviders;
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
            'failed' => ['nullable', 'string', 'in:'.implode(',', LiveProviders::all())],
            // Fournisseur qui a fermé la connexion à sa LIMITE (Google ne le dit qu'au navigateur, jamais au serveur).
            'exhausted' => ['nullable', 'string', 'in:'.implode(',', LiveProviders::all())],
        ];
    }

    public function failedProvider(): ?string
    {
        return $this->validated('failed');
    }

    public function exhaustedProvider(): ?string
    {
        return $this->validated('exhausted');
    }

    public function handle(): ?string
    {
        return $this->validated('handle');
    }
}
