<?php

namespace App\Support\Alerts;

/**
 * Résultat de l'envoi d'une notification : réussi, échoué (raison courte) ou appareil disparu (à supprimer).
 */
final class PushResult
{
    private function __construct(
        public readonly bool $sent,
        public readonly bool $gone,
        public readonly ?string $reason,
    ) {}

    public static function sent(): self
    {
        return new self(true, false, null);
    }

    public static function gone(): self
    {
        return new self(false, true, 'appareil désabonné');
    }

    public static function failed(string $reason): self
    {
        return new self(false, false, $reason);
    }
}
