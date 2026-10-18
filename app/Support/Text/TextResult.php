<?php

namespace App\Support\Text;

/**
 * Réponse d'un fournisseur : le texte (ou le JSON, en texte), qui l'a produit et ce qu'il a consommé.
 */
final class TextResult
{
    public function __construct(
        public readonly string $text,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $durationMs = 0,
    ) {}

    /**
     * Le JSON de la réponse, ou null s'il est illisible.
     *
     * @return array<string, mixed>|null
     */
    public function json(): ?array
    {
        $text = trim($this->text);

        // Certains modèles entourent le JSON de ```json … ``` malgré la consigne.
        if (str_starts_with($text, '```')) {
            $text = trim((string) preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $text));
        }

        $data = json_decode($text, true);

        return is_array($data) ? $data : null;
    }
}
