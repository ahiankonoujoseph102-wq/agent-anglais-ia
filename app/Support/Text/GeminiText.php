<?php

namespace App\Support\Text;

use App\Support\Gemini\TextClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Modèle texte de Gemini (config services.gemini.text_model), API Interactions : images et PDF en entrée,
 * réponse JSON imposée par un schéma (response_format), rien de conservé chez Google (store: false).
 *
 * Documentation : https://ai.google.dev/gemini-api/docs/interactions,
 * https://ai.google.dev/gemini-api/docs/structured-output et /docs/document-processing.
 */
class GeminiText implements TextProvider
{
    public const NAME = 'gemini';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return 'Gemini';
    }

    public function hasKey(): bool
    {
        return filled(config('services.gemini.key'));
    }

    public function model(): string
    {
        return (string) config('services.gemini.text_model');
    }

    public function inputPrice(): float
    {
        return (float) config('services.text.pricing.gemini.input');
    }

    public function outputPrice(): float
    {
        return (float) config('services.text.pricing.gemini.output');
    }

    public function generate(TextRequest $request): TextResult
    {
        $key = config('services.gemini.key');

        if (blank($key)) {
            throw new TextFailed(TextFailed::NO_KEY, 'GEMINI_API_KEY manquante dans les variables d\'environnement.');
        }

        $started = hrtime(true);
        $elapsed = fn () => (int) round((hrtime(true) - $started) / 1e6);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->acceptJson()
                ->timeout(max(1, $request->timeout))
                ->post(rtrim((string) config('services.gemini.base_url'), '/').'/'.config('services.gemini.text_api_version').'/interactions', $this->body($request));
        } catch (ConnectionException $e) {
            throw self::connectionFailure($e, 'Gemini', $request->timeout, $elapsed());
        }

        if ($response->failed()) {
            throw $this->httpFailure($response, $elapsed());
        }

        $text = TextClient::outputText((array) $response->json('steps', []));

        if ($text === '') {
            throw new TextFailed(TextFailed::EMPTY, 'Gemini a répondu sans texte.', $response->status(), $elapsed());
        }

        return new TextResult(
            $text,
            self::NAME,
            $this->model(),
            (int) $response->json('usage.total_input_tokens', 0),
            (int) $response->json('usage.total_output_tokens', 0) + (int) $response->json('usage.total_thought_tokens', 0),
            $elapsed(),
        );
    }

    /**
     * Corps de la requête Interactions.
     *
     * @return array<string, mixed>
     */
    public function body(TextRequest $request): array
    {
        $parts = $request->parts;

        // Un seul texte sans schéma : simple chaîne, comme une rédaction.
        if (count($parts) === 1 && $parts[0]['type'] === 'text' && $request->schema === null) {
            $input = $parts[0]['text'];
        } else {
            $input = array_map(fn (array $part) => $part['type'] === 'text'
                ? ['type' => 'text', 'text' => $part['text']]
                : ['type' => $part['type'], 'data' => base64_encode($part['data']), 'mime_type' => $part['mime']], $parts);
        }

        $body = [
            'model' => $this->model(),
            'system_instruction' => $request->instructions,
            'input' => $input,
        ];

        if ($request->schema !== null) {
            $body['response_format'] = [
                'type' => 'text',
                'mime_type' => 'application/json',
                'schema' => $request->schema,
            ];
        }

        $body['generation_config'] = [
            'max_output_tokens' => $request->maxTokens,
            'thinking_level' => $request->effort,
        ];
        // Données personnelles : rien à conserver chez Google.
        $body['store'] = false;

        return $body;
    }

    public static function connectionFailure(ConnectionException $e, string $label, int $timeout, int $durationMs): TextFailed
    {
        $timedOut = str_contains($e->getMessage(), 'cURL error 28') || stripos($e->getMessage(), 'timed out') !== false;

        return new TextFailed(
            $timedOut ? TextFailed::TIMEOUT : TextFailed::CONNECTION,
            $timedOut ? "Pas de réponse de {$label} en {$timeout} secondes." : "Connexion à l'API {$label} impossible.",
            durationMs: $durationMs,
        );
    }

    /**
     * Cause d'un refus de Google : limite du jour, limite par minute, clé refusée, sinon le code et le message
     * d'erreur de Google (jamais la requête ni la clé).
     */
    private function httpFailure(Response $response, int $durationMs): TextFailed
    {
        $message = Str::limit(trim((string) $response->json('error.message', '')), 240);
        $status = $response->status();

        if ($status === 429) {
            // Google écrit toujours « quota » : la limite du jour se reconnaît à l'identifiant du quota (« …PerDay… »).
            $daily = preg_match('/per ?day|daily/i', $message.' '.json_encode($response->json('error.details', []))) === 1;

            return new TextFailed(
                $daily ? TextFailed::QUOTA : TextFailed::RATE_LIMIT,
                $message !== '' ? $message : 'Limite de demandes Gemini atteinte.',
                429,
                $durationMs,
            );
        }

        $kind = in_array($status, [401, 403], true) ? TextFailed::AUTH : TextFailed::HTTP;

        return new TextFailed($kind, 'HTTP '.$status.($message !== '' ? ' : '.$message : '').' (modèle « '.$this->model().' »).', $status, $durationMs);
    }
}
