<?php

namespace App\Support\Alerts;

/**
 * Raison d'échec courte et sans secret : les clés, mots de passe et adresses de notification sont masqués.
 */
final class Redact
{
    public static function reason(?string $text): string
    {
        $text = trim((string) $text);

        foreach ([
            config('alerts.vapid.private_key'),
            config('alerts.vapid.public_key'),
            config('mail.mailers.smtp.password'),
            config('mail.mailers.smtp.username'),
        ] as $secret) {
            if (is_string($secret) && strlen($secret) >= 4) {
                $text = str_replace($secret, '…', $text);
            }
        }

        $text = preg_replace('~https?://\S+~', '(adresse masquée)', $text) ?? $text;
        $text = preg_replace('/[A-Za-z0-9_\-]{32,}/', '…', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return $text === '' ? 'erreur inconnue' : mb_strimwidth($text, 0, 190, '…');
    }
}
