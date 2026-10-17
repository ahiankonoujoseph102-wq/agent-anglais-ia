<?php

namespace App\Support;

/**
 * URL d'un fichier de public/ avec l'empreinte de son contenu (?v=…).
 *
 * Le navigateur et le service worker gardent ces fichiers en cache : ils ne
 * sont retéléchargés que si leur contenu change (pas à chaque déploiement).
 */
class Asset
{
    /** @var array<string, string> */
    private static array $versions = [];

    public static function url(string $path): string
    {
        if (! isset(self::$versions[$path])) {
            $file = public_path($path);
            self::$versions[$path] = is_file($file) ? substr(hash_file('xxh128', $file), 0, 12) : '0';
        }

        return asset($path).'?v='.self::$versions[$path];
    }
}
