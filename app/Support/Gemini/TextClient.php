<?php

namespace App\Support\Gemini;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tâches en texte avec un modèle Gemini léger (config services.gemini.text_model),
 * appelé depuis le serveur via l'API Interactions.
 *
 * Ces rédactions sont un « plus » : en cas d'échec (clé absente, quota, panne),
 * la méthode renvoie null et l'appelant utilise un texte type.
 *
 * Documentation : https://ai.google.dev/gemini-api/docs/interactions
 */
class TextClient
{
    /**
     * Rédige un texte court.
     */
    public function write(string $instructions, string $prompt, int $maxTokens = 400): ?string
    {
        $key = config('services.gemini.key');

        if (blank($key)) {
            return null;
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->acceptJson()
                ->timeout(15)
                ->post(rtrim(config('services.gemini.base_url'), '/').'/'.config('services.gemini.text_api_version').'/interactions', [
                    'model' => config('services.gemini.text_model'),
                    'system_instruction' => $instructions,
                    'input' => $prompt,
                    'generation_config' => [
                        'max_output_tokens' => $maxTokens,
                        // Rédaction simple : réflexion minimale, réponse rapide et peu coûteuse.
                        'thinking_level' => 'minimal',
                    ],
                    // Rien à conserver chez Google.
                    'store' => false,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Gemini texte : connexion impossible.', ['message' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('Gemini texte : requête refusée.', ['status' => $response->status(), 'body' => $response->json()]);

            return null;
        }

        $text = self::outputText($response->json('steps', []));

        return $text === '' ? null : $text;
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
