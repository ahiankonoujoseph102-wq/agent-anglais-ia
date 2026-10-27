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
     * L'histoire de la plateforme (founder.story), un paragraphe par ligne, marqueurs remplis :
     * nom de la plateforme, nom du fondateur, prix de la formule la moins chère.
     *
     * @return list<string>
     */
    public static function story(): array
    {
        $cheapest = collect(Plan::all())->sortBy('price')->first();
        $markers = [
            ':platform' => (string) config('platform.name'),
            ':founder' => (string) config('platform.founder.name'),
            ':price_from' => $cheapest ? $cheapest->formattedPrice().' '.$cheapest->periodLabel() : '',
        ];

        // Montants jamais coupés en fin de ligne : « 350 000 FCFA » comme Money::format.
        $amounts = fn (string $text) => (string) preg_replace(['/(\d) (?=\d{3}\b)/u', '/(\d) (?=FCFA)/u'], ["$1\u{202F}", "$1\u{00A0}"], $text);

        return array_values(array_filter(array_map(
            fn ($paragraph) => trim($amounts(strtr((string) $paragraph, $markers))),
            (array) config('platform.founder.story', []),
        )));
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
