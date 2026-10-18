<?php

namespace App\Support\Gemini;

use App\Support\Speech\GeminiSpeech;
use App\Support\Speech\SpeechProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Journal des échecs de synthèse vocale : chaque échec est écrit dans les journaux (cause, code HTTP, durée,
 * morceau concerné, fournisseur) et les derniers sont gardés pour la page « Diagnostic de la voix ». Jamais de clé ni de secret :
 * seuls le code HTTP et le message d'erreur du fournisseur (sans la requête) sont gardés.
 */
final class SpeechLog
{
    public const CACHE_KEY = 'speech-failures';

    /** Échecs gardés pour l'administration. */
    public const KEEP = 20;

    /**
     * @param  SpeechProvider|null  $provider  fournisseur concerné (Gemini par défaut)
     */
    public static function failure(SpeechFailed $e, string $label, ?SpeechProvider $provider = null): void
    {
        $provider ??= app(GeminiSpeech::class);
        $entry = [
            'at' => now()->toIso8601String(),
            'provider' => $provider->name(),
            'provider_label' => $provider->label(),
            'label' => $label,
            'kind' => $e->kind,
            'reason' => $e->reason(),
            'status' => $e->status,
            'duration_ms' => $e->durationMs,
            'message' => $e->getMessage(),
        ];

        Log::warning(explode(' (', $provider->label())[0].' : synthèse vocale en échec.', $entry + [
            'model' => $provider->model(),
            'voice' => $provider->voice(),
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
     * @return list<array{at: string, provider?: string, provider_label?: string, label: string, kind: string, reason: string, status: ?int, duration_ms: ?int, message: string}>
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
