<?php

namespace App\Support\Gemini;

use App\Models\Assessment;
use App\Models\Lesson;
use App\Models\LevelTest;
use Illuminate\Support\Carbon;

/**
 * Délivre un jeton pour une conversation et prépare la réponse envoyée au navigateur.
 */
class LiveConnection
{
    public function __construct(private LiveClient $client) {}

    /**
     * Durée de validité du prochain jeton : jamais au-delà de la fin autorisée.
     */
    public static function tokenExpiry(Carbon $deadline): Carbon
    {
        return now()->addMinutes((int) config('services.gemini.token_minutes'))->min($deadline);
    }

    /**
     * Demande un jeton à Google (avant toute écriture en base).
     *
     * @param  array<string, mixed>  $setup
     * @return array{token: string, websocket_url: string, expires_at: Carbon}
     *
     * @throws GeminiUnavailable
     */
    public function request(array $setup, Carbon $deadline): array
    {
        $expiresAt = self::tokenExpiry($deadline);

        return $this->client->createToken($setup, $expiresAt) + ['expires_at' => $expiresAt];
    }

    /**
     * Note le jeton délivré sur la conversation et construit la réponse JSON.
     *
     * @param  array{token: string, websocket_url: string, expires_at: Carbon}  $token
     * @param  array<string, mixed>  $setup
     * @param  array<string, string>  $cues
     * @return array<string, mixed>
     */
    public function attach(Assessment|Lesson|LevelTest $conversation, array $token, array $setup, array $cues, int $warningSeconds, bool $resumed): array
    {
        $conversation->token_expires_at = $token['expires_at'];
        $conversation->connections++;
        $conversation->last_seen_at = now();
        $conversation->save();

        return [
            'websocket_url' => $token['websocket_url'],
            'setup' => $setup,
            'remaining_seconds' => $conversation->remainingSeconds(),
            'token_expires_in' => max(0, (int) now()->diffInSeconds($token['expires_at'], false)),
            'warning_seconds' => $warningSeconds,
            'heartbeat_seconds' => (int) config('platform.live.heartbeat_seconds'),
            'cues' => $cues,
            'resumed' => $resumed,
        ];
    }
}
