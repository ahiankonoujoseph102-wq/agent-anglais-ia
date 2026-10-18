<?php

namespace App\Support\Speech;

use App\Support\PrivateStorage;

/**
 * Fichiers audio gardés sur le disque privé : une seule liste par dossier au lieu d'une question par fichier
 * (le bucket répond à chaque question par une requête).
 */
final class SpeechFiles
{
    /**
     * @param  list<string>  $directories
     * @return array<string, true>
     */
    public static function index(array $directories): array
    {
        $files = [];

        foreach (array_unique($directories) as $directory) {
            try {
                foreach (PrivateStorage::disk()->files($directory) as $file) {
                    $files[$file] = true;
                }
            } catch (\Throwable) {
                // Disque indisponible : rien n'est prêt, la page reste en texte.
            }
        }

        return $files;
    }
}
