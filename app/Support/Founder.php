<?php

namespace App\Support;

/**
 * Fondateur (config/platform.php → founder) : présentation et photo, pour l'accueil, « À propos »
 * et les données structurées. Uniquement des faits donnés par le fondateur.
 */
class Founder
{
    /**
     * Sa phrase de présentation (marqueur :platform remplacé), ou null tant qu'elle n'est pas écrite.
     */
    public static function bio(): ?string
    {
        $bio = trim((string) config('platform.founder.bio'));

        return $bio === '' ? null : str_replace(':platform', (string) config('platform.name'), $bio);
    }

    /**
     * Adresse versionnée de la photo, ou null si elle n'existe pas.
     */
    public static function photoUrl(): ?string
    {
        $photo = (string) config('platform.founder.photo');

        return $photo !== '' && is_file(public_path($photo)) ? Asset::url($photo) : null;
    }

    public static function photoAlt(): string
    {
        return config('platform.founder.name').', fondateur de '.config('platform.name');
    }
}
