<?php

namespace App\Http\Requests\Live;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Signal régulier du navigateur, avec les jetons consommés depuis le précédent.
 */
class SignalRequest extends FormRequest
{
    /** Détail des jetons envoyé par le navigateur (public/js/token-usage.js), mêmes noms que les colonnes. */
    public const DETAILS = [
        'input_text_tokens', 'input_audio_tokens', 'cached_text_tokens', 'cached_audio_tokens',
        'output_text_tokens', 'output_audio_tokens', 'turns', 'max_context_tokens',
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'input_tokens' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'output_tokens' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            // Détail des jetons par nature (texte, audio, cache), nombre de tours et plus grand contexte d'un tour.
            ...array_fill_keys(self::DETAILS, ['nullable', 'integer', 'min:0', 'max:10000000']),
            // Sous-titres de l'évaluation depuis le signal précédent (repli si le temps finit sans verdict).
            'transcript' => ['nullable', 'string', 'max:8000'],
            // Connexion avec le professeur : false = coupée (le temps de séance est mis en pause), true = revenue.
            'connected' => ['nullable', 'boolean'],
        ];
    }

    public function transcript(): ?string
    {
        return $this->validated('transcript');
    }

    public function connected(): ?bool
    {
        $connected = $this->validated('connected');

        return $connected === null ? null : (bool) $connected;
    }

    public function inputTokens(): int
    {
        return (int) $this->validated('input_tokens', 0);
    }

    public function outputTokens(): int
    {
        return (int) $this->validated('output_tokens', 0);
    }

    /**
     * Détail des jetons depuis le signal précédent (vide pour un navigateur d'avant ce détail).
     *
     * @return array<string, int>
     */
    public function tokenDetails(): array
    {
        $details = [];

        foreach (self::DETAILS as $field) {
            if ($this->validated($field) !== null) {
                $details[$field] = (int) $this->validated($field);
            }
        }

        return $details;
    }
}
