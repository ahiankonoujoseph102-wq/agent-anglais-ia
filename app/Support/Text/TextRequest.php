<?php

namespace App\Support\Text;

/**
 * Une demande au modèle texte (rédaction, lecture d'une image ou d'un PDF, réponse JSON imposée), décrite
 * une seule fois pour tous les fournisseurs : chacun la traduit dans son API avec EXACTEMENT les mêmes
 * consignes, les mêmes fichiers et le même format de réponse attendu.
 */
final class TextRequest
{
    /** Usages, avec leur nom pour l'administration. */
    public const KINDS = [
        'proof' => 'Lecture des captures de paiement',
        'course' => 'Lecture des leçons (Mes cours)',
        'estimate' => 'Estimation du niveau sans verdict',
        'program' => 'Présentation du programme',
        'encouragement' => 'Message aux apprenants inactifs',
        'board' => 'Tableaux des séances',
        'review' => 'Documents de révision (Mes cours)',
        'video' => 'Scripts des vidéos de leçon',
        'diagnostic' => 'Essai du diagnostic',
        'rehearsal' => 'Banc d\'essai du professeur',
    ];

    /**
     * @param  list<array{type: string, text?: string, data?: string, mime?: string, filename?: string}>  $parts  morceaux d'entrée, dans l'ordre (voir text(), image(), document())
     * @param  array<string, mixed>|null  $schema  schéma JSON de la réponse attendue (null : texte libre)
     * @param  string  $effort  réflexion demandée : « minimal » (rédaction simple) ou « low » (lecture)
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $instructions,
        public readonly array $parts,
        public readonly ?array $schema = null,
        public readonly string $schemaName = 'answer',
        public readonly int $maxTokens = 400,
        public readonly int $timeout = 30,
        public readonly string $effort = 'low',
    ) {}

    /**
     * @return array{type: string, text: string}
     */
    public static function text(string $text): array
    {
        return ['type' => 'text', 'text' => $text];
    }

    /**
     * @return array{type: string, data: string, mime: string}
     */
    public static function image(string $bytes, string $mime): array
    {
        return ['type' => 'image', 'data' => $bytes, 'mime' => $mime];
    }

    /**
     * @return array{type: string, data: string, mime: string, filename: string}
     */
    public static function document(string $bytes, string $mime = 'application/pdf', string $filename = 'lecon.pdf'): array
    {
        return ['type' => 'document', 'data' => $bytes, 'mime' => $mime, 'filename' => $filename];
    }

    public function label(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
