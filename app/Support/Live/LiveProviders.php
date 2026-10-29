<?php

namespace App\Support\Live;

use App\Support\Gemini\TeacherVoice;
use Illuminate\Support\Facades\Cache;

/**
 * Fournisseurs des séances orales en direct, dans l'ordre de config/services.php → live.providers
 * (LIVE_PROVIDERS, « gemini,openai,gemini2 » par défaut : « gemini2 » = Gemini avec la deuxième clé, GEMINI_API_KEY_2,
 * même modèle et même voix). Un nom inconnu est ignoré ; un fournisseur sans clé n'est
 * jamais utilisé (OpenAI sans OPENAI_API_KEY : simplement ignoré). Après une limite ou une clé refusée, un
 * fournisseur est mis de côté quelques minutes (live.pause_seconds) : les séances suivantes commencent
 * directement chez le suivant, sans attendre un nouveau refus.
 */
final class LiveProviders
{
    public const GEMINI = 'gemini';

    public const OPENAI = 'openai';

    /** Gemini avec la deuxième clé (autre compte Google, donc autre limite). */
    public const GEMINI2 = 'gemini2';

    public const LABELS = [self::GEMINI => 'Gemini', self::OPENAI => 'OpenAI', self::GEMINI2 => 'Gemini (deuxième clé)'];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(?string $name): string
    {
        return self::LABELS[$name] ?? (string) $name;
    }

    public static function hasKey(string $name): bool
    {
        return match ($name) {
            self::GEMINI => filled(config('services.gemini.key')),
            self::OPENAI => filled(config('services.openai.key')),
            self::GEMINI2 => filled(config('services.gemini.key_2')),
            default => false,
        };
    }

    /** Gemini Live, quelle que soit la clé (même modèle, même voix, même chemin dans le navigateur). */
    public static function isGemini(string $name): bool
    {
        return in_array($name, [self::GEMINI, self::GEMINI2], true);
    }

    /** Clé Gemini de ce fournisseur (jamais affichée), ou null. */
    public static function geminiKey(string $name): ?string
    {
        $key = match ($name) {
            self::GEMINI => config('services.gemini.key'),
            self::GEMINI2 => config('services.gemini.key_2'),
            default => null,
        };

        return filled($key) ? (string) $key : null;
    }

    /** Nom de la variable de la clé, pour l'administration. */
    public static function keyVariable(string $name): string
    {
        return match ($name) {
            self::OPENAI => 'OPENAI_API_KEY',
            self::GEMINI2 => 'GEMINI_API_KEY_2',
            default => 'GEMINI_API_KEY',
        };
    }

    public static function model(string $name): string
    {
        return $name === self::OPENAI ? (string) config('services.openai.realtime_model') : (string) config('services.gemini.live_model');
    }

    public static function voice(string $name): string
    {
        return $name === self::OPENAI ? (string) config('services.openai.realtime_voice') : (string) TeacherVoice::current();
    }

    /**
     * Fournisseurs de l'ordre réglé, sans doublon ni nom inconnu.
     *
     * @return list<string>
     */
    public static function configured(): array
    {
        $names = array_unique(array_map('strtolower', (array) config('services.live.providers', [])));

        return array_values(array_filter($names, fn (string $name) => isset(self::LABELS[$name])));
    }

    /**
     * @return list<string>
     */
    public static function available(): array
    {
        return array_values(array_filter(self::configured(), fn (string $name) => self::hasKey($name)));
    }

    /**
     * Ordre d'essai pour une nouvelle séance : ceux qui ont une clé, ceux mis de côté à la fin (essayés
     * quand même en dernier recours).
     *
     * @param  list<string>  $except
     * @return list<string>
     */
    public static function ordered(array $except = []): array
    {
        $ready = [];
        $paused = [];

        foreach (array_diff(self::available(), $except) as $name) {
            if (self::isPaused($name)) {
                $paused[] = $name;
            } else {
                $ready[] = $name;
            }
        }

        return [...$ready, ...$paused];
    }

    public static function isPaused(string $name): bool
    {
        try {
            return Cache::has('live-pause:'.$name);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function pause(string $name): void
    {
        $seconds = (int) config('services.live.pause_seconds', 300);

        if ($seconds <= 0) {
            return;
        }

        try {
            Cache::put('live-pause:'.$name, true, now()->addSeconds($seconds));
        } catch (\Throwable) {
            // Sans cache, chaque séance essaie simplement le premier fournisseur.
        }
    }
}
