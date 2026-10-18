<?php

namespace App\Support\Gemini;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Journal des échecs de synthèse vocale : chaque échec est écrit dans les journaux (cause, code HTTP, durée,
 * morceau concerné) et les derniers sont gardés pour la page « Diagnostic de la voix ». Jamais de clé ni de secret :
 * seuls le code HTTP et le message d'erreur de Google (sans la requête) sont gardés.
 */
final class SpeechLog
{
    public const CACHE_KEY = 'speech-failures';

    /** Échecs gardés pour l'administration. */
    public const KEEP = 20;

    public static function failure(SpeechFailed $e, string $label): void
    {
        $entry = [
            'at' => now()->toIso8601String(),
            'label' => $label,
            'kind' => $e->kind,
            'reason' => $e->reason(),
            'status' => $e->status,
            'duration_ms' => $e->durationMs,
            'message' => $e->getMessage(),
        ];

        Log::warning('Gemini : synthèse vocale en échec.', $entry + [
            'model' => config('services.gemini.tts_model'),
        ]);

        try {
            $recent = (array) Cache::get(self::CACHE_KEY, []);
            array_unshift($recent, $entry);
            Cache::put(self::CACHE_KEY, array_slice($recent, 0, self::KEEP), now()->addDays(14));
        } catch (\Throwable) {
            // Le journal ne doit jamais faire échouer la page.
        }
    }

    /**
     * @return list<array{at: string, label: string, kind: string, reason: string, status: ?int, duration_ms: ?int, message: string}>
     */
    public static function recent(): array
    {
        try {
            return array_values((array) Cache::get(self::CACHE_KEY, []));
        } catch (\Throwable) {
            return [];
        }
    }
}
