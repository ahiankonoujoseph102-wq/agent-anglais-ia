<?php

namespace App\Support\Ambassadors;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\ByteMatrix;
use BaconQrCode\Encoder\Encoder;

/**
 * Code QR d'un lien (bibliothèque bacon/bacon-qr-code, BSD-2-Clause, seulement pour calculer les modules) :
 * - svg() : noir sur blanc, marge de 4 modules (norme), modules en surfaces pleines jointives, pour la page ;
 * - png() : image à télécharger et à envoyer sur WhatsApp ou à imprimer, avec le lien et le code écrits
 *   dessous (police Sora du dépôt) ; null sans l'extension GD.
 * Correction d'erreur « Q » (25 %), comme les prospectus : lisible malgré un pli ou une tache.
 */
final class QrCode
{
    private const BORDER = 4;

    public static function svg(string $text, string $label = 'Code QR'): string
    {
        $matrix = self::matrix($text);
        $size = $matrix->getWidth() + 2 * self::BORDER;
        $path = '';

        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            $x = 0;

            while ($x < $matrix->getWidth()) {
                if ($matrix->get($x, $y) !== 1) {
                    $x++;

                    continue;
                }

                $start = $x;

                while ($x < $matrix->getWidth() && $matrix->get($x, $y) === 1) {
                    $x++;
                }

                $path .= sprintf('M%d %dh%dv1h-%dz', $start + self::BORDER, $y + self::BORDER, $x - $start, $x - $start);
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" role="img" aria-label="%2$s" shape-rendering="crispEdges"><rect width="%1$d" height="%1$d" fill="#fff"/><path fill="#000" d="%3$s"/></svg>',
            $size, e($label), $path,
        );
    }

    /**
     * Image PNG : le code QR (12 pixels par module), puis jusqu'à deux lignes de texte centrées dessous.
     *
     * @param  array<int, string>  $lines
     */
    public static function png(string $text, array $lines = []): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $matrix = self::matrix($text);
        $module = 12;
        $side = ($matrix->getWidth() + 2 * self::BORDER) * $module;
        $font = resource_path('pdf/sora-bold.ttf');
        $withText = $lines !== [] && function_exists('imagettftext') && is_file($font);
        $lineHeight = 64;
        $height = $side + ($withText ? count($lines) * $lineHeight + 24 : 0);

        $image = imagecreatetruecolor($side, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $side - 1, $height - 1, $white);

        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            for ($x = 0; $x < $matrix->getWidth(); $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $left = ($x + self::BORDER) * $module;
                    $top = ($y + self::BORDER) * $module;
                    imagefilledrectangle($image, $left, $top, $left + $module - 1, $top + $module - 1, $black);
                }
            }
        }

        if ($withText) {
            foreach (array_values($lines) as $index => $line) {
                // Taille la plus grande (jusqu'à 30 points) qui tient dans la largeur, marges comprises.
                $points = 30;

                do {
                    $box = imagettfbbox($points, 0, $font, $line);
                    $width = $box[2] - $box[0];
                    $points--;
                } while ($width > $side - 2 * $module * self::BORDER && $points > 10);

                $baseline = $side + ($index + 1) * $lineHeight - 8;
                imagettftext($image, $points + 1, 0, (int) (($side - $width) / 2), $baseline, $black, $font, $line);
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    private static function matrix(string $text): ByteMatrix
    {
        return Encoder::encode($text, ErrorCorrectionLevel::Q(), 'UTF-8', null, false)->getMatrix();
    }
}
