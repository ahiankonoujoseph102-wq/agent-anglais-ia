<?php

namespace App\Support\Videos;

/**
 * Anciennes adresses du site (app.redirect_from_hosts : talkami.laravel.cloud) dans les vidéos : jamais plus dites ni
 * affichées. Le réglage « Adresse du site » des vidéos y est ramené à l'adresse officielle (APP_URL : talkami.net) ;
 * une vidéo déjà préparée qui la porte dans son script (sous-titres, voix) est signalée « à refaire », jamais refaite
 * toute seule.
 */
final class OldAddress
{
    /** @return list<string> anciennes adresses (sans l'adresse officielle). */
    public static function hosts(): array
    {
        $primary = self::primary();

        return array_values(array_filter(
            array_map(fn ($host) => mb_strtolower(trim((string) $host)), (array) config('app.redirect_from_hosts', [])),
            fn (string $host) => $host !== '' && $host !== $primary,
        ));
    }

    /** Adresse officielle du site (« talkami.net »), tirée de APP_URL. */
    public static function primary(): ?string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? mb_strtolower(preg_replace('/^www\./', '', $host)) : null;
    }

    public static function isOld(?string $host): bool
    {
        return $host !== null && in_array(mb_strtolower($host), self::hosts(), true);
    }

    /** Le texte contient-il une ancienne adresse, écrite (« talkami.laravel.cloud ») ou dite (« … point … ») ? */
    public static function in(?string $text): bool
    {
        $text = mb_strtolower((string) $text);

        foreach (self::hosts() as $host) {
            if (str_contains($text, $host) || str_contains($text, str_replace('.', ' point ', $host))) {
                return true;
            }
        }

        return false;
    }

    /** Remplace les anciennes adresses écrites par l'adresse officielle (texte à copier). */
    public static function replace(?string $text): ?string
    {
        $primary = self::primary();

        if ($text === null || $primary === null) {
            return $text;
        }

        foreach (self::hosts() as $host) {
            $text = str_ireplace(['https://'.$host, 'http://'.$host, $host], ['https://'.$primary, 'https://'.$primary, $primary], $text);
        }

        return $text;
    }
}
