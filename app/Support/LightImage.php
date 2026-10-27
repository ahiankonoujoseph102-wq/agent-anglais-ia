<?php

namespace App\Support;

use Throwable;

/**
 * Version allégée d'une image (WebP, extension GD) : par exemple les aperçus des vidéos de « Comment ça marche »,
 * fabriqués en JPEG par GitHub. Renvoie null si GD, le WebP ou l'image manquent : l'image d'origine est alors servie.
 */
final class LightImage
{
    public static function webp(string $bytes, int $quality = 62): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return null;
        }

        try {
            $image = @imagecreatefromstring($bytes);

            if ($image === false) {
                return null;
            }

            ob_start();
            $written = imagewebp($image, null, $quality);
            $webp = (string) ob_get_clean();
            imagedestroy($image);

            // Jamais plus lourd que l'original.
            return $written && $webp !== '' && strlen($webp) < strlen($bytes) ? $webp : null;
        } catch (Throwable) {
            return null;
        }
    }
}
