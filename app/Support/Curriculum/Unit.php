<?php

namespace App\Support\Curriculum;

use App\Enums\Level;

/**
 * Une unité du programme officiel (resources/curriculum/{niveau}.php).
 */
final readonly class Unit
{
    /**
     * Nombre de leçons (séances) par unité : une par situation de conversation.
     */
    public const LESSONS = 3;

    /**
     * @param  list<string>  $objectives  suites de « À la fin, vous saurez… »
     * @param  array<string, string>  $vocabulary  anglais => français
     * @param  list<string>  $grammar
     * @param  list<string>  $functions  fonctions orales à pratiquer
     * @param  list<string>  $pronunciation
     * @param  list<string>  $spoken  anglais parlé naturel (expressions, réductions)
     * @param  list<string>  $pitfalls  difficultés typiques des francophones
     * @param  list<Situation>  $situations
     */
    public function __construct(
        public Level $level,
        public int $number,
        public string $title,
        public array $objectives,
        public array $vocabulary,
        public array $grammar,
        public array $functions,
        public array $pronunciation,
        public array $spoken,
        public array $pitfalls,
        public array $situations,
    ) {}

    public function code(): string
    {
        return $this->level->value.'-'.str_pad((string) $this->number, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Ce que travaille chaque leçon de l'unité.
     */
    public static function lessonFocusLabel(int $lessonNumber): string
    {
        return match ($lessonNumber) {
            1 => 'Vocabulaire et expressions',
            2 => 'Grammaire en situation',
            default => 'Prononciation et révision de l\'unité',
        };
    }
}
