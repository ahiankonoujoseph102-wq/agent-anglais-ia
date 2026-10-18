<?php

namespace App\Support\Gemini;

use App\Support\Text\TextProviders;
use App\Support\Text\TextRequest;

/**
 * Tâches en texte avec le modèle texte léger (Gemini, services.gemini.text_model ; OpenAI en secours,
 * App\Support\Text\TextProviders), appelé depuis le serveur.
 *
 * Ces rédactions sont un « plus » : si aucun fournisseur ne répond (clé absente, quota, panne),
 * la méthode renvoie null et l'appelant utilise un texte type.
 */
class TextClient
{
    /**
     * Rédige un texte court. null si aucun fournisseur ne répond : l'appelant utilise son texte type.
     *
     * @param  string  $kind  usage (TextRequest::KINDS), pour la page « Consommation »
     */
    public function write(string $instructions, string $prompt, int $maxTokens = 400, string $kind = 'program'): ?string
    {
        $result = TextProviders::generate(new TextRequest(
            kind: $kind,
            instructions: $instructions,
            parts: [TextRequest::text($prompt)],
            maxTokens: $maxTokens,
            timeout: 15,
            // Rédaction simple : réflexion minimale, réponse rapide et peu coûteuse.
            effort: 'minimal',
        ));

        return $result?->text;
    }

    /**
     * Texte de la dernière réponse du modèle (même règle que le SDK officiel « output_text »).
     *
     * @param  array<int, array<string, mixed>>  $steps
     */
    public static function outputText(array $steps): string
    {
        for ($i = count($steps) - 1; $i >= 0; $i--) {
            $step = $steps[$i];

            if (($step['type'] ?? null) !== 'model_output') {
                continue;
            }

            $parts = array_filter($step['content'] ?? [], fn ($item) => ($item['type'] ?? null) === 'text');

            if ($parts !== []) {
                return trim(implode('', array_column($parts, 'text')));
            }
        }

        return '';
    }
}
