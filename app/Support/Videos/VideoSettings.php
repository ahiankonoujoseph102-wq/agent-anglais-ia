<?php

namespace App\Support\Videos;

use App\Models\Setting;
use Throwable;

/**
 * Réglages des vidéos choisis dans la page « Vidéos » (table settings, sans redéploiement) : adresse du site dite et
 * affichée à la fin de chaque vidéo, voix des vidéos, vidéos de promotion par jour ; nom du professeur en filigrane.
 */
class VideoSettings
{
    public const SITE = 'videos.site';

    public const VOICE = 'videos.voice';

    public const PER_DAY = 'videos.per_day';

    /** Adresse du site des vidéos (« talkami.net »), ou null : la fabrication est alors bloquée. */
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

    /**
     * Vidéos de promotion préparées chaque jour : le réglage de la page « Vidéos » (1 à 3), sinon la configuration.
     */
    public static function perDay(): int
    {
        $saved = (int) self::read(self::PER_DAY);

        return in_array($saved, self::perDayChoices(), true) ? $saved : (int) config('videos.per_day');
    }

    /** @return list<int> */
    public static function perDayChoices(): array
    {
        return array_map('intval', (array) config('videos.per_day_choices', [1, 2, 3]));
    }

    public static function savePerDay(int $count): void
    {
        Setting::put(self::PER_DAY, (string) $count);
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
