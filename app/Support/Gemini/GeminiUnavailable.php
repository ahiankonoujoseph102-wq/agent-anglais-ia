<?php

namespace App\Support\Gemini;

use RuntimeException;

/**
 * L'API n'a pas pu délivrer de jeton (clé absente, quota atteint, clé refusée, panne, délai dépassé).
 */
class GeminiUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly bool $quotaExceeded = false, public readonly ?int $httpStatus = null, public readonly bool $invalidKey = false)
    {
        parent::__construct($message);
    }

    /**
     * Le fournisseur est inutilisable pendant un moment (limite, clé refusée) : les séances suivantes
     * commencent directement chez le suivant.
     */
    public function pausesProvider(): bool
    {
        return $this->quotaExceeded || $this->invalidKey || in_array($this->httpStatus, [401, 403], true);
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
