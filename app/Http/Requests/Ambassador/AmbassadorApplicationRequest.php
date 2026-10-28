<?php

namespace App\Http\Requests\Ambassador;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Candidature de la page « Devenir ambassadeur » (compte Talkami obligatoire : route « auth »).
 */
class AmbassadorApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivation' => ['required', 'string', 'min:20', 'max:1000'],
            'channels' => ['required', 'string', 'max:300'],
            'minor' => ['nullable', 'boolean'],
            'rules' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivation.required' => 'Dites-nous en quelques phrases pourquoi vous voulez être ambassadeur.',
            'motivation.min' => 'Écrivez au moins une ou deux phrases (20 caractères au moins).',
            'motivation.max' => 'Votre message ne peut pas dépasser 1 000 caractères.',
            'channels.required' => 'Dites-nous à qui vous parlerez de la plateforme.',
            'channels.max' => 'Cette réponse ne peut pas dépasser 300 caractères.',
            'rules.accepted' => 'Cochez la case pour accepter les règles du programme.',
        ];
    }

    /**
     * @return array{motivation: string, channels: string, minor: bool}
     */
    public function application(): array
    {
        return [
            'motivation' => trim((string) $this->validated('motivation')),
            'channels' => trim((string) $this->validated('channels')),
            'minor' => $this->boolean('minor'),
        ];
    }
}
