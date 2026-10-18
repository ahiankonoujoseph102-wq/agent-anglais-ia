<?php

namespace App\Support\Live;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Journal des échecs des séances en direct (création du jeton ou de l'appel, connexion du navigateur avant la
 * première parole) : écrit dans les journaux et gardé pour la page « Diagnostic des fournisseurs ». Jamais de
 * clé ni de jeton : seulement la cause, le code HTTP et le message du fournisseur.
 */
final class LiveLog
{
    public const CACHE_KEY = 'live-failures';

    public const KEEP = 20;

    public static function failure(string $provider, string $step, string $message, ?int $status = null, bool $quota = false): void
    {
        $entry = [
            'at' => now()->toIso8601String(),
            'provider' => $provider,
            'provider_label' => LiveProviders::label($provider),
            'step' => $step,
            'reason' => $quota ? 'Limite atteinte (limite du jour ou trop de demandes)' : (in_array($status, [401, 403], true) ? 'Clé refusée' : 'Indisponible'),
            'status' => $status,
            'message' => mb_substr($message, 0, 300),
        ];

        Log::warning(LiveProviders::label($provider).' : séance en direct impossible.', $entry + ['model' => LiveProviders::model($provider)]);

        try {
            $recent = (array) Cache::get(self::CACHE_KEY, []);
            array_unshift($recent, $entry);
            Cache::put(self::CACHE_KEY, array_slice($recent, 0, self::KEEP), now()->addDays(14));
        } catch (\Throwable) {
            // Le journal ne doit jamais faire échouer la séance.
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
