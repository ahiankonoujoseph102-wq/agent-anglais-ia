<?php

namespace App\Support\Live;

use Illuminate\Support\Str;

/**
 * Retire d'une réplique ce qui identifie quelqu'un, avant un export pour l'analyse (TranscriptExport) :
 * adresses e-mail, liens, numéros (téléphone, transaction : 6 chiffres ou plus) et les mots du nom de
 * l'apprenant (avec ou sans accents, en majuscules ou non). Limite assumée : un autre nom propre dit
 * pendant la séance (un ami, une école, un employeur) n'est pas reconnu : relire l'export avant de le partager.
 */
final class TranscriptAnonymizer
{
    public const NAME = '[prénom]';

    public const NUMBER = '[numéro]';

    public const EMAIL = '[e-mail]';

    public const LINK = '[lien]';

    /** Mots trop courants pour être pris pour un morceau de nom (« Afi de la Croix »). */
    private const COMMON_WORDS = ['de', 'du', 'la', 'le', 'les', 'des', 'et', 'da', 'di', 'el', 'van', 'von'];

    /**
     * @param  array<int, string>  $names  nom de l'apprenant (et ses variantes connues)
     */
    public static function clean(string $text, array $names = []): string
    {
        $text = (string) preg_replace('/[\p{L}0-9._%+\-]+@[\p{L}0-9.\-]+\.\p{L}{2,}/u', self::EMAIL, $text);
        $text = (string) preg_replace('~\b(?:https?://|www\.)\S+~iu', self::LINK, $text);
        // Suites de chiffres (avec espaces, points, tirets ou « + ») d'au moins 6 chiffres : téléphone, transaction.
        $text = (string) preg_replace_callback('/\+?\d[\d\s.\-]{4,}\d/u', function (array $match) {
            return preg_match_all('/\d/', $match[0]) >= 6 ? self::NUMBER : $match[0];
        }, $text);

        foreach (self::nameWords($names) as $word) {
            $text = (string) preg_replace('/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'(?![\p{L}\p{N}])/iu', self::NAME, $text);
        }

        return $text;
    }

    /**
     * Mots des noms (deux lettres au moins, hors mots courants), avec leur version sans accents, les plus longs d'abord.
     *
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    private static function nameWords(array $names): array
    {
        $words = [];

        foreach ($names as $name) {
            foreach (preg_split('/[\s\'’\-]+/u', (string) $name) ?: [] as $word) {
                $word = trim($word, ' .,;:!?"«»()');

                if (mb_strlen($word) < 2 || in_array(mb_strtolower($word), self::COMMON_WORDS, true)) {
                    continue;
                }

                $words[] = $word;
                $words[] = Str::ascii($word);
            }
        }

        $words = array_values(array_unique(array_filter($words, fn ($word) => mb_strlen($word) >= 2)));
        usort($words, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return $words;
    }
}
