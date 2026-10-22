<?php

namespace App\Support\Videos;

use App\Models\Setting;
use Throwable;

/**
 * Réglages des vidéos choisis dans la page « Vidéos » (table settings, sans redéploiement) : adresse du site dite et
 * affichée à la fin de chaque vidéo, voix des vidéos ; nom du professeur en filigrane.
 */
class VideoSettings
{
    public const SITE = 'videos.site';

    public const VOICE = 'videos.voice';

    /** Adresse du site des vidéos (« talkami.laravel.cloud »), ou null : la fabrication est alors bloquée. */
    public static function site(): ?string
    {
        return self::read(self::SITE);
    }

    /** Message de blocage quand l'adresse du site manque, sinon null. */
    public static function blocked(): ?string
    {
        return self::site() === null
            ? 'L\'adresse du site n\'est pas renseignée dans la page « Vidéos » de l\'administration. Elle est dite et affichée à la fin de chaque vidéo : sans elle, aucune vidéo n\'est préparée ni fabriquée.'
            : null;
    }

    /**
     * Adresse saisie, ramenée à un nom de domaine (sans « https:// », sans chemin, en minuscules), ou null si elle
     * n'en est pas un.
     */
    public static function normalizeSite(?string $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = preg_replace('#^[a-z]+://#', '', $value) ?? '';
        $value = rtrim(explode('/', $value)[0], '.');

        return preg_match('/^(?=.{3,100}$)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $value) ? $value : null;
    }

    public static function saveSite(?string $site): void
    {
        Setting::put(self::SITE, $site);
    }

    /**
     * Voix choisie dans l'essai comparatif.
     *
     * @return array{provider: string, voice: string}|null
     */
    public static function voice(): ?array
    {
        $value = self::read(self::VOICE);

        if ($value === null || ! str_contains($value, ':')) {
            return null;
        }

        [$provider, $voice] = explode(':', $value, 2);

        return ['provider' => $provider, 'voice' => $voice];
    }

    public static function saveVoice(string $provider, string $voice): void
    {
        Setting::put(self::VOICE, $provider.':'.$voice);
    }

    /** Nom du professeur en filigrane (VIDEO_WATERMARK, sinon le nom du professeur de la configuration). */
    public static function watermark(): string
    {
        return (string) (config('videos.render.watermark') ?: config('avatar.model.name'));
    }

    private static function read(string $key): ?string
    {
        try {
            $value = Setting::get($key);
        } catch (Throwable) {
            $value = null;
        }

        return filled($value) ? (string) $value : null;
    }
}
