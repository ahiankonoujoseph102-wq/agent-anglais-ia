<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Sommaire d'un guide (refonte, point 4) : chaque titre de partie (h2) du texte reçoit une ancre (« partie-… »),
 * et la liste des parties est rendue en haut du guide. Le texte du guide n'est pas modifié dans le dépôt.
 */
final class GuideToc
{
    /**
     * Texte du guide avec une ancre sur chaque h2, et les parties (ancre => titre), dans l'ordre.
     *
     * @return array{html: string, items: array<string, string>}
     */
    public static function build(string $html): array
    {
        $items = [];

        $html = preg_replace_callback('/<h2(\s[^>]*)?>(.*?)<\/h2>/su', function (array $match) use (&$items) {
            $attributes = $match[1] ?? '';
            $title = trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5));

            if (preg_match('/\sid="([^"]+)"/', $attributes, $id)) {
                $anchor = $id[1];
            } else {
                $base = 'partie-'.(Str::slug(preg_replace('/^\d+\.\s*/u', '', $title) ?? $title) ?: (string) (count($items) + 1));
                $anchor = $base;
                $n = 2;

                while (isset($items[$anchor])) {
                    $anchor = $base.'-'.$n++;
                }

                $attributes .= ' id="'.$anchor.'"';
            }

            $items[$anchor] = $title;

            return '<h2'.$attributes.'>'.$match[2].'</h2>';
        }, $html) ?? $html;

        return ['html' => $html, 'items' => $items];
    }
}
