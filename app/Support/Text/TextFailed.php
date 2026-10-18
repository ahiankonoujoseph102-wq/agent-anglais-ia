<?php

namespace App\Support\Text;

use RuntimeException;

/**
 * Échec d'une demande chez un fournisseur. Le message ne contient jamais la clé ni la requête : seulement
 * la cause, le code HTTP et le message d'erreur renvoyé par le fournisseur.
 */
final class TextFailed extends RuntimeException
{
    public const NO_KEY = 'no_key';

    public const RATE_LIMIT = 'rate_limit';

    public const QUOTA = 'quota';

    public const AUTH = 'auth';

    public const TIMEOUT = 'timeout';

    public const CONNECTION = 'connection';

    public const HTTP = 'http';

    public const EMPTY = 'empty';

    public const REFUSAL = 'refusal';

    public function __construct(
        public readonly string $kind,
        string $message = '',
        public readonly ?int $status = null,
        public readonly ?int $durationMs = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Cause en français, pour l'administration.
     */
    public function reason(): string
    {
        return match ($this->kind) {
            self::NO_KEY => 'Clé absente',
            self::RATE_LIMIT => 'Limite atteinte (trop de demandes)',
            self::QUOTA => 'Limite du jour ou crédit épuisé',
            self::AUTH => 'Clé refusée',
            self::TIMEOUT => 'Trop lent (délai dépassé)',
            self::CONNECTION => 'Connexion impossible',
            self::EMPTY => 'Réponse vide ou illisible',
            self::REFUSAL => 'Le modèle a refusé de répondre',
            default => 'Erreur du fournisseur',
        };
    }

    /**
     * Échec qui rend le fournisseur inutilisable pendant un moment (limite, crédit, clé refusée) :
     * les demandes suivantes vont directement au fournisseur suivant.
     */
    public function pausesProvider(): bool
    {
        return in_array($this->kind, [self::RATE_LIMIT, self::QUOTA, self::AUTH], true);
    }
}
