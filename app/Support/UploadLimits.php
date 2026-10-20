<?php

namespace App\Support;

/**
 * Limites du serveur qui comptent pour « Mes cours » (PDF de 20 mégaoctets, lecture jusqu'à une minute) :
 * taille d'un fichier et d'un envoi (upload_max_filesize, post_max_size) et durée d'une page
 * (max_execution_time). Lues dans PHP tel qu'il tourne (site ou ligne de commande, qui peuvent différer).
 * Affichées par app:check-deployment et dans « Diagnostic des fournisseurs » (essais réels de l'envoi et du délai).
 */
class UploadLimits
{
    /** Marge pour le reste du formulaire (titre, jeton) au-delà du fichier. */
    private const FORM_MARGIN = 1024 * 1024;

    /**
     * @return array{file: array{value: int, needed: int, ok: bool}, post: array{value: int, needed: int, ok: bool}, time: array{value: int, needed: int, ok: bool}}
     */
    public static function report(): array
    {
        $file = self::bytes((string) ini_get('upload_max_filesize'));
        $post = self::bytes((string) ini_get('post_max_size'));
        $time = (int) ini_get('max_execution_time');
        $neededFile = max((int) config('platform.courses.pdf_max_kilobytes'), (int) config('platform.courses.photo_max_kilobytes')) * 1024;
        $neededPost = max($neededFile, (int) config('platform.courses.photos_total_max_kilobytes') * 1024) + self::FORM_MARGIN;
        $neededTime = self::readingSeconds();

        return [
            // 0 : aucune limite.
            'file' => ['value' => $file, 'needed' => $neededFile, 'ok' => $file === 0 || $file >= $neededFile],
            'post' => ['value' => $post, 'needed' => $neededPost, 'ok' => $post === 0 || $post >= $neededPost],
            // Le code demande lui-même plus de temps (set_time_limit) quand PHP le permet.
            'time' => ['value' => $time, 'needed' => $neededTime, 'ok' => $time === 0 || $time >= $neededTime || self::canExtendTime()],
        ];
    }

    /**
     * Durée la plus longue d'une lecture : deux fournisseurs, chacun avec son délai, plus une marge.
     */
    public static function readingSeconds(): int
    {
        return 2 * (int) config('platform.courses.read_timeout_seconds') + 30;
    }

    public static function canExtendTime(): bool
    {
        return function_exists('set_time_limit') && ! in_array('set_time_limit', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);
    }

    /**
     * « 20M » → 20 971 520 ; « 0 » ou vide → 0 (aucune limite pour post_max_size).
     */
    public static function bytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || ! preg_match('/^(\d+)\s*([kmg]?)/i', $value, $match)) {
            return 0;
        }

        return (int) $match[1] * match (strtolower($match[2])) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };
    }

    /**
     * « 20 mégaoctets », « 512 kilooctets », « aucune limite ».
     */
    public static function label(int $bytes): string
    {
        return match (true) {
            $bytes === 0 => 'aucune limite',
            $bytes >= 1024 ** 2 => rtrim(rtrim(number_format($bytes / 1024 ** 2, 1, ',', ' '), '0'), ',').' mégaoctets',
            default => (int) ceil($bytes / 1024).' kilooctets',
        };
    }
}
