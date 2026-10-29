<?php

namespace App\Support\Gemini;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Accès à l'API Gemini Live depuis le serveur.
 *
 * Le serveur ne fait qu'une chose : demander un jeton éphémère. Le navigateur
 * s'en sert pour se connecter directement à Google par WebSocket ; la clé API
 * reste dans .env. Le jeton fige la configuration de la conversation (modèle,
 * consignes, fonctions) : le navigateur ne peut pas la modifier.
 *
 * Documentation : https://ai.google.dev/gemini-api/docs/ephemeral-tokens
 */
class LiveClient
{
    /**
     * Crée un jeton à usage unique, valable jusqu'à $expiresAt, avec la clé donnée (sinon la clé principale).
     *
     * @param  array<string, mixed>  $setup  configuration « BidiGenerateContentSetup »
     * @return array{token: string, websocket_url: string}
     *
     * @throws GeminiUnavailable
     */
    public function createToken(array $setup, Carbon $expiresAt, ?string $key = null): array
    {
        $key ??= config('services.gemini.key');

        if (blank($key)) {
            throw new GeminiUnavailable('Clé Gemini manquante dans les variables d\'environnement.');
        }

        $version = config('services.gemini.api_version');
        $connectBefore = now()->addSeconds((int) config('services.gemini.connect_window_seconds'))->min($expiresAt);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->acceptJson()
                ->timeout(15)
                ->post(rtrim(config('services.gemini.base_url'), '/')."/{$version}/auth_tokens", [
                    'uses' => 1,
                    'expireTime' => $expiresAt->copy()->utc()->toIso8601ZuluString(),
                    'newSessionExpireTime' => $connectBefore->copy()->utc()->toIso8601ZuluString(),
                    'bidiGenerateContentSetup' => $setup,
                ]);
        } catch (ConnectionException $e) {
            $timedOut = str_contains($e->getMessage(), 'cURL error 28') || stripos($e->getMessage(), 'timed out') !== false;

            throw new GeminiUnavailable($timedOut ? 'Pas de réponse de Gemini en 15 secondes.' : 'Connexion à l\'API Gemini impossible.');
        }

        if ($response->status() === 429 || $response->json('error.status') === 'RESOURCE_EXHAUSTED') {
            Log::warning('Gemini : quota atteint lors de la création d\'un jeton.', ['body' => $response->json()]);

            throw new GeminiUnavailable('Quota Gemini atteint.'.self::detail($response->json('error.message')), quotaExceeded: true, httpStatus: $response->status());
        }

        $token = $response->json('name');

        if ($response->failed() || ! is_string($token) || $token === '') {
            Log::error('Gemini : création de jeton refusée.', ['status' => $response->status(), 'body' => $response->json()]);

            // Clé invalide (Google répond 400 « API key not valid ») : traitée comme une clé refusée.
            $invalidKey = $response->status() === 400 && (str_contains(json_encode($response->json()) ?: '', 'API_KEY_INVALID') || stripos((string) $response->json('error.message'), 'API key not valid') !== false);

            throw new GeminiUnavailable('Création du jeton refusée (HTTP '.$response->status().')'.self::detail($response->json('error.message')).'.', httpStatus: $response->status(), invalidKey: $invalidKey);
        }

        return [
            'token' => $token,
            'websocket_url' => rtrim(config('services.gemini.websocket_url'), '/')
                ."/ws/google.ai.generativelanguage.{$version}.GenerativeService.BidiGenerateContentConstrained"
                .'?access_token='.rawurlencode($token),
        ];
    }

    private static function detail(mixed $message): string
    {
        $message = trim(mb_substr(is_string($message) ? $message : '', 0, 200));

        return $message === '' ? '' : ' : '.$message;
    }
}
