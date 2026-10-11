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
     * Crée un jeton à usage unique, valable jusqu'à $expiresAt.
     *
     * @param  array<string, mixed>  $setup  configuration « BidiGenerateContentSetup »
     * @return array{token: string, websocket_url: string}
     *
     * @throws GeminiUnavailable
     */
    public function createToken(array $setup, Carbon $expiresAt): array
    {
        $key = config('services.gemini.key');

        if (blank($key)) {
            throw new GeminiUnavailable('GEMINI_API_KEY manquante dans .env.');
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
            throw new GeminiUnavailable('Connexion à l\'API Gemini impossible : '.$e->getMessage());
        }

        if ($response->status() === 429 || $response->json('error.status') === 'RESOURCE_EXHAUSTED') {
            Log::warning('Gemini : quota atteint lors de la création d\'un jeton.', ['body' => $response->json()]);

            throw new GeminiUnavailable('Quota Gemini atteint.', quotaExceeded: true);
        }

        $token = $response->json('name');

        if ($response->failed() || ! is_string($token) || $token === '') {
            Log::error('Gemini : création de jeton refusée.', ['status' => $response->status(), 'body' => $response->json()]);

            throw new GeminiUnavailable('Création du jeton refusée (HTTP '.$response->status().').');
        }

        return [
            'token' => $token,
            'websocket_url' => rtrim(config('services.gemini.websocket_url'), '/')
                ."/ws/google.ai.generativelanguage.{$version}.GenerativeService.BidiGenerateContentConstrained"
                .'?access_token='.rawurlencode($token),
        ];
    }
}
