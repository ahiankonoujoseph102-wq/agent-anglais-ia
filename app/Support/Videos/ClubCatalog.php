<?php

namespace App\Support\Videos;

use App\Enums\Level;
use App\Support\Curriculum\Curriculum;

/**
 * Catalogue des vidéos du Club (resources/videos/club.php) et pièges des francophones du programme officiel (repli
 * du lundi). Choix du prochain élément : le premier jamais présenté depuis « repeat_weeks » semaines, sinon le
 * moins récemment présenté.
 */
class ClubCatalog
{
    /** @return list<array<string, mixed>> */
    public static function all(string $type): array
    {
        $catalog = require resource_path('videos/club.php');

        return array_values($catalog[$type] ?? []);
    }

    /**
     * Pièges des francophones (A1 à B1), avec une clé stable tirée du texte.
     *
     * @return list<array{key: string, text: string}>
     */
    public static function pitfalls(): array
    {
        $pitfalls = [];

        foreach ([Level::A1, Level::A2, Level::B1] as $level) {
            foreach (Curriculum::units($level) as $unit) {
                foreach ($unit->pitfalls as $text) {
                    $pitfalls[] = ['key' => substr(md5($text), 0, 12), 'text' => $text];
                }
            }
        }

        return $pitfalls;
    }

    /**
     * Prochain élément : jamais présenté récemment, sinon le moins récent.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, string>  $used  clé => date de la dernière présentation (Y-m-d)
     * @return array<string, mixed>|null
     */
    public static function next(array $items, array $used): ?array
    {
        if ($items === []) {
            return null;
        }

        foreach ($items as $item) {
            if (! isset($used[$item['key']])) {
                return $item;
            }
        }

        usort($items, fn (array $a, array $b) => strcmp($used[$a['key']], $used[$b['key']]));

        return $items[0];
    }
}
