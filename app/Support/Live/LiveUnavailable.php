<?php

namespace App\Support\Live;

use App\Support\Gemini\GeminiUnavailable;

/**
 * Aucun fournisseur n'a pu ouvrir la séance en direct (clé absente, limite, panne, délai dépassé). Le
 * message ne contient jamais la clé : seulement la cause, le code HTTP et le message du fournisseur.
 */
class LiveUnavailable extends GeminiUnavailable
{
    public function __construct(string $message, bool $quotaExceeded = false, ?int $httpStatus = null, public readonly string $provider = '')
    {
        parent::__construct($message, $quotaExceeded, $httpStatus);
    }
}
