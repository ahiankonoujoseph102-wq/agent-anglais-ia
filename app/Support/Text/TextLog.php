<?php

namespace App\Support\Text;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Journal des échecs du modèle texte : chaque échec est écrit dans les journaux (fournisseur, usage, cause,
 * code HTTP, durée) et les derniers sont gardés pour la page « Diagnostic des fournisseurs ». Jamais de clé
 * ni de contenu de la demande : seulement la cause et le message d'erreur du fournisseur.
 */
final class TextLog
{
    public const CACHE_KEY = 'text-failures';

    public const KEEP = 20;

    public static function failure(TextFailed $e, TextRequest $request, TextProvider $provider): void
    {
        $entry = [
            'at' => now()->toIso8601String(),
            'provider' => $provider->name(),
            'provider_label' => $provider->label(),
            'kind' => $request->kind,
            'label' => $request->label(),
            'cause' => $e->kind,
            'reason' => $e->reason(),
            'status' => $e->status,
            'duration_ms' => $e->durationMs,
            'message' => $e->getMessage(),
        ];

        Log::warning($provider->label().' : modèle texte en échec.', $entry + ['model' => $provider->model()]);

        try {
            $recent = (array) Cache::get(self::CACHE_KEY, []);
            array_unshift($recent, $entry);
            Cache::put(self::CACHE_KEY, array_slice($recent, 0, self::KEEP), now()->addDays(14));
        } catch (\Throwable) {
            // Le journal ne doit jamais faire échouer la demande.
        }
    }

    /**
     * @return list<array<string, mixed>>
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
