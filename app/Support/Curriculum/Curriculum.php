<?php

namespace App\Support\Curriculum;

use App\Enums\Level;
use RuntimeException;

/**
 * Le programme officiel de A1 à C2, lu dans resources/curriculum/{niveau}.php.
 *
 * Chaque fichier renvoie ['title' => …, 'units' => [12 unités]]. Le contenu est
 * modifiable sans toucher au code ; le test CurriculumTest vérifie sa structure.
 */
class Curriculum
{
    public const UNITS_PER_LEVEL = 12;

    /**
     * Thèmes autorisés pour les situations (personnalisation du programme).
     */
    public const TAGS = [
        'work', 'studies', 'travel', 'business', 'exam', 'conversation',
        'market', 'family', 'transport', 'health', 'admin', 'education', 'tech', 'phone',
    ];

    /**
     * @var array<string, array<int, Unit>>
     */
    private static array $cache = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private static array $raw = [];

    /**
     * Titre du niveau, en français.
     */
    public static function title(Level $level): string
    {
        return (string) self::raw($level)['title'];
    }

    /**
     * @return array<int, Unit> indexées de 1 à 12
     */
    public static function units(Level $level): array
    {
        return self::$cache[$level->value] ??= self::load($level);
    }

    public static function unit(Level $level, int $number): Unit
    {
        return self::units($level)[$number] ?? throw new RuntimeException("Unité {$number} introuvable pour {$level->value}.");
    }

    /**
     * Chemin du fichier de données d'un niveau.
     */
    public static function path(Level $level): string
    {
        return resource_path('curriculum/'.strtolower($level->value).'.php');
    }

    /**
     * @return array<string, mixed>
     */
    private static function raw(Level $level): array
    {
        return self::$raw[$level->value] ??= require self::path($level);
    }

    /**
     * @return array<int, Unit>
     */
    private static function load(Level $level): array
    {
        $units = [];

        foreach (array_values(self::raw($level)['units']) as $index => $unit) {
            $units[$index + 1] = new Unit(
                level: $level,
                number: $index + 1,
                title: $unit['title'],
                objectives: $unit['objectives'],
                vocabulary: $unit['vocabulary'],
                grammar: $unit['grammar'],
                functions: $unit['functions'],
                pronunciation: $unit['pronunciation'],
                spoken: $unit['spoken'] ?? [],
                pitfalls: $unit['pitfalls'] ?? [],
                situations: array_map(
                    fn (array $situation) => new Situation($situation['title'], $situation['description'], $situation['tags']),
                    $unit['situations'],
                ),
            );
        }

        return $units;
    }
}
