<?php

namespace App\Support\Text;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Modèle texte d'OpenAI (config services.openai.text_model), secours de Gemini : API Responses, images
 * (input_image) et PDF (input_file, lu directement par le modèle : aucune conversion) en entrée, réponse
 * JSON imposée par un schéma strict (text.format json_schema), rien de conservé chez OpenAI (store: false).
 *
 * Documentation : https://developers.openai.com/api/docs/guides/structured-outputs,
 * /docs/guides/images-vision et /docs/guides/pdf-files.
 */
class OpenAiText implements TextProvider
{
    public const NAME = 'openai';

    /** Marge de jetons de sortie pour la réflexion du modèle (comptée dans max_output_tokens). */
    private const REASONING_MARGIN = 2000;

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return 'OpenAI';
    }

    public function hasKey(): bool
    {
        return filled(config('services.openai.key'));
    }

    public function model(): string
    {
        return (string) config('services.openai.text_model');
    }

    public function inputPrice(): float
    {
        return (float) config('services.text.pricing.openai.input');
    }

    public function outputPrice(): float
    {
        return (float) config('services.text.pricing.openai.output');
    }

    public function generate(TextRequest $request): TextResult
    {
        $key = config('services.openai.key');

        if (blank($key)) {
            throw new TextFailed(TextFailed::NO_KEY, 'OPENAI_API_KEY manquante dans les variables d\'environnement.');
        }

        $started = hrtime(true);
        $elapsed = fn () => (int) round((hrtime(true) - $started) / 1e6);

        try {
            $response = Http::withToken((string) $key)
                ->acceptJson()
                ->timeout(max(1, $request->timeout))
                ->post(rtrim((string) config('services.openai.base_url'), '/').'/responses', $this->body($request));
        } catch (ConnectionException $e) {
            throw GeminiText::connectionFailure($e, 'OpenAI', $request->timeout, $elapsed());
        }

        if ($response->failed()) {
            throw $this->httpFailure($response, $elapsed());
        }

        $text = '';

        foreach ((array) $response->json('output', []) as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ((array) ($item['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    throw new TextFailed(TextFailed::REFUSAL, Str::limit(trim((string) ($content['refusal'] ?? '')), 240), $response->status(), $elapsed());
                }

                if (($content['type'] ?? null) === 'output_text') {
                    $text .= (string) ($content['text'] ?? '');
                }
            }
        }

        $text = trim($text);

        if ($text === '' || ! in_array($response->json('status', 'completed'), ['completed', null], true)) {
            $reason = (string) $response->json('incomplete_details.reason', '');

            throw new TextFailed(TextFailed::EMPTY, 'OpenAI a répondu sans texte complet'.($reason !== '' ? " ({$reason})" : '').'.', $response->status(), $elapsed());
        }

        return new TextResult(
            $text,
            self::NAME,
            $this->model(),
            (int) $response->json('usage.input_tokens', 0),
            (int) $response->json('usage.output_tokens', 0),
            $elapsed(),
        );
    }

    /**
     * Corps de la requête Responses : mêmes consignes, mêmes fichiers, même schéma que pour Gemini.
     *
     * @return array<string, mixed>
     */
    public function body(TextRequest $request): array
    {
        $content = array_map(fn (array $part) => match ($part['type']) {
            'text' => ['type' => 'input_text', 'text' => $part['text']],
            'image' => ['type' => 'input_image', 'image_url' => 'data:'.$part['mime'].';base64,'.base64_encode($part['data']), 'detail' => 'high'],
            default => ['type' => 'input_file', 'filename' => $part['filename'] ?? 'document.pdf', 'file_data' => 'data:'.$part['mime'].';base64,'.base64_encode($part['data'])],
        }, $request->parts);

        $body = [
            'model' => $this->model(),
            'instructions' => $request->instructions,
            'input' => [['role' => 'user', 'content' => $content]],
            'max_output_tokens' => $request->maxTokens + self::REASONING_MARGIN,
            // Rédaction simple : sans réflexion ; lecture : réflexion réglée (OPENAI_TEXT_REASONING).
            'reasoning' => ['effort' => $request->effort === 'minimal' ? 'none' : (string) config('services.openai.text_reasoning')],
            // Données personnelles : rien à conserver chez OpenAI.
            'store' => false,
        ];

        if ($request->schema !== null) {
            $body['text'] = ['format' => [
                'type' => 'json_schema',
                'name' => $request->schemaName,
                'strict' => true,
                'schema' => self::strict($request->schema),
            ]];
        }

        return $body;
    }

    /**
     * Schéma en mode strict (exigé par OpenAI) : chaque objet refuse les champs en plus et rend tous ses
     * champs obligatoires (les valeurs facultatives sont déjà « null » permis dans nos schémas).
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function strict(array $schema): array
    {
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $schema['properties'] = array_map(fn ($property) => is_array($property) ? self::strict($property) : $property, $schema['properties']);
            $schema['required'] = array_keys($schema['properties']);
            $schema['additionalProperties'] = false;
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = self::strict($schema['items']);
        }

        return $schema;
    }

    /**
     * Cause d'un refus d'OpenAI : crédit épuisé, limite de demandes, clé refusée, sinon le code HTTP et le
     * message d'OpenAI (jamais la requête ni la clé).
     */
    private function httpFailure(Response $response, int $durationMs): TextFailed
    {
        $error = (array) $response->json('error', []);
        $message = Str::limit(trim((string) ($error['message'] ?? '')), 240);
        $code = (string) ($error['code'] ?? $error['type'] ?? '');
        $status = $response->status();

        if ($status === 429) {
            $exhausted = in_array($code, ['insufficient_quota', 'billing_hard_limit_reached'], true);

            return new TextFailed(
                $exhausted ? TextFailed::QUOTA : TextFailed::RATE_LIMIT,
                $message !== '' ? $message : ($exhausted ? 'Crédit OpenAI épuisé.' : 'Limite de demandes OpenAI atteinte.'),
                429,
                $durationMs,
            );
        }

        $kind = in_array($status, [401, 403], true) ? TextFailed::AUTH : TextFailed::HTTP;

        return new TextFailed($kind, 'HTTP '.$status.($message !== '' ? ' : '.$message : '').' (modèle « '.$this->model().' »).', $status, $durationMs);
    }
}
