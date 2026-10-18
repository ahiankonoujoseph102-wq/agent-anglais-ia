<?php

namespace App\Support\Live;

use App\Support\Gemini\TeacherVoice;
use Illuminate\Support\Facades\Cache;

/**
 * Fournisseurs des séances orales en direct, dans l'ordre de config/services.php → live.providers
 * (LIVE_PROVIDERS, « gemini,openai » par défaut). Un nom inconnu est ignoré ; un fournisseur sans clé n'est
 * jamais utilisé (OpenAI sans OPENAI_API_KEY : simplement ignoré). Après une limite ou une clé refusée, un
 * fournisseur est mis de côté quelques minutes (live.pause_seconds) : les séances suivantes commencent
 * directement chez le suivant, sans attendre un nouveau refus.
 */
final class LiveProviders
{
    public const GEMINI = 'gemini';

    public const OPENAI = 'openai';

    public const LABELS = [self::GEMINI => 'Gemini', self::OPENAI => 'OpenAI'];

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
            default => false,
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
