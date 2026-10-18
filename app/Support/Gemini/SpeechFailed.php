<?php

namespace App\Support\Gemini;

/**
 * Échec d'une synthèse vocale (ou de l'enregistrement de son fichier), avec sa cause : de quoi la journaliser,
 * l'afficher dans « Diagnostic de la voix » et décider s'il faut réessayer. Ne contient jamais la clé.
 */
class SpeechFailed extends GeminiUnavailable
{
    public const MISSING_KEY = 'missing_key';

    /** Erreur 429 : limite de demandes par minute. Un nouvel essai un peu plus tard peut réussir. */
    public const QUOTA = 'quota';

    /** Erreur 429 sur la limite du jour : inutile de réessayer avant le lendemain. */
    public const QUOTA_DAILY = 'quota_daily';

    public const TIMEOUT = 'timeout';

    public const CONNECTION = 'connection';

    public const HTTP = 'http';

    public const NO_AUDIO = 'no_audio';

    public const UNREADABLE = 'unreadable';

    public const STORAGE = 'storage';

    public function __construct(
        public readonly string $kind,
        string $message,
        public readonly ?int $status = null,
        public readonly ?int $durationMs = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, quotaExceeded: in_array($kind, [self::QUOTA, self::QUOTA_DAILY], true));
    }

    public function isRetryable(): bool
    {
        return $this->kind === self::QUOTA;
    }

    /**
     * Inutile de redemander à ce fournisseur aujourd'hui : limite du jour ou crédit épuisé, clé absente,
     * clé refusée (401, 403). La préparation passe alors au fournisseur suivant.
     */
    public function exhaustsProvider(): bool
    {
        return in_array($this->kind, [self::QUOTA_DAILY, self::MISSING_KEY], true)
            || ($this->kind === self::HTTP && in_array($this->status, [401, 403], true));
    }

    /**
     * Cause en français, pour l'administration.
     */
    public function reason(): string
    {
        return match ($this->kind) {
            self::MISSING_KEY => 'Clé absente',
            self::QUOTA => 'Limite de demandes par minute atteinte (erreur 429)',
            self::QUOTA_DAILY => 'Limite de demandes du jour atteinte ou crédit épuisé (erreur 429)',
            self::TIMEOUT => 'Délai d\'attente dépassé',
            self::CONNECTION => 'Connexion au service impossible',
            self::HTTP => 'Demande refusée par le service',
            self::NO_AUDIO => 'Réponse sans audio',
            self::UNREADABLE => 'Audio illisible',
            self::STORAGE => 'Écriture impossible dans le stockage privé',
            default => 'Erreur inconnue',
        };
    }
}
