<?php

namespace App\Support\Gemini;

use RuntimeException;

/**
 * L'API Gemini n'a pas pu délivrer de jeton (clé absente, quota atteint, panne).
 */
class GeminiUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly bool $quotaExceeded = false)
    {
        parent::__construct($message);
    }

    /**
     * Message à afficher à l'apprenant.
     */
    public function userMessage(): string
    {
        return $this->quotaExceeded
            ? 'Le professeur a atteint sa limite de conversations pour le moment. Réessayez un peu plus tard.'
            : 'Le professeur est momentanément indisponible. Réessayez dans quelques instants.';
    }
}
