<?php

namespace App\Support\Live;

use App\Support\Gemini\TeacherVoice;
use Illuminate\Support\Facades\Cache;

/**
 * Fournisseurs des séances orales en direct, dans l'ordre de config/services.php → live.providers
 * (LIVE_PROVIDERS, « gemini,gemini2,gemini3,openai » par défaut : « gemini2 » et « gemini3 » = Gemini avec la
 * deuxième et la troisième clé, GEMINI_API_KEY_2 et GEMINI_API_KEY_3, même modèle et même voix). Décision du fondateur
 * (2 novembre 2026) : les clés Gemini d'abord, OpenAI TOUJOURS en dernier, même avec une ancienne liste
 * LIVE_PROVIDERS ; « gemini3 » est ajouté quand sa clé est réglée mais que la liste ne le nomme pas. Un nom inconnu est ignoré ; un fournisseur sans clé n'est
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

    /** Gemini avec la troisième clé (encore un autre compte Google, autre limite). */
    public const GEMINI3 = 'gemini3';

    public const LABELS = [self::GEMINI => 'Gemini', self::OPENAI => 'OpenAI', self::GEMINI2 => 'Gemini (deuxième clé)', self::GEMINI3 => 'Gemini (troisième clé)'];

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
            self::GEMINI3 => filled(config('services.gemini.key_3')),
            default => false,
        };
    }

    /** Gemini Live, quelle que soit la clé (même modèle, même voix, même chemin dans le navigateur). */
    public static function isGemini(string $name): bool
    {
        return in_array($name, [self::GEMINI, self::GEMINI2, self::GEMINI3], true);
    }

    /** Clé Gemini de ce fournisseur (jamais affichée), ou null. */
    public static function geminiKey(string $name): ?string
    {
        $key = match ($name) {
            self::GEMINI => config('services.gemini.key'),
            self::GEMINI2 => config('services.gemini.key_2'),
            self::GEMINI3 => config('services.gemini.key_3'),
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
            self::GEMINI3 => 'GEMINI_API_KEY_3',
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
        $names = array_values(array_unique(array_map('strtolower', (array) config('services.live.providers', []))));

        // Troisième clé réglée mais absente d'une liste LIVE_PROVIDERS écrite avant elle : ajoutée après les autres clés.
        if (self::hasKey(self::GEMINI3) && ! in_array(self::GEMINI3, $names, true)) {
            $names[] = self::GEMINI3;
        }

        $names = array_values(array_filter($names, fn (string $name) => isset(self::LABELS[$name])));

        // OpenAI (payant) toujours après toutes les clés Gemini (gratuites).
        $gemini = array_values(array_filter($names, fn (string $name) => self::isGemini($name)));

        return [...$gemini, ...array_values(array_diff($names, $gemini))];
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

    /**
     * Fournisseurs à écarter quand le NAVIGATEUR n'a pas pu joindre $failed : toutes les clés Gemini passent par le
     * même service de Google, une autre clé n'y changerait rien.
     *
     * @return list<string>
     */
    public static function unreachable(string $failed): array
    {
        return self::isGemini($failed) ? [self::GEMINI, self::GEMINI2, self::GEMINI3] : [$failed];
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
