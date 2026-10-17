<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Disque PRIVÉ du site : captures d'écran des paiements et attestations en PDF.
 *
 * Réglé par la variable PRIVATE_FILES_DISK (config « filesystems.private_disk »).
 * Vide = disque par défaut de Laravel (FILESYSTEM_DISK) : « local » (storage/app/private)
 * en développement, le bucket privé attaché comme disque par défaut sur Laravel Cloud.
 * Ces fichiers ne sont jamais servis par une URL publique : seules des routes protégées les lisent.
 */
class PrivateStorage
{
    /**
     * Nom du disque privé.
     */
    public static function name(): string
    {
        $name = config('filesystems.private_disk') ?: config('filesystems.default');

        $disk = (array) config("filesystems.disks.{$name}");

        // Refuse un disque public : un fichier privé n'y serait plus privé.
        if ($name === 'public' || ($disk['visibility'] ?? null) === 'public') {
            throw new RuntimeException("Le disque « {$name} » est public : il ne peut pas recevoir de fichiers privés.");
        }

        return $name;
    }

    public static function disk(): FilesystemAdapter
    {
        return Storage::disk(self::name());
    }
}
