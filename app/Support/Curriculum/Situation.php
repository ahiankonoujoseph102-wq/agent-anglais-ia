<?php

namespace App\Support\Curriculum;

/**
 * Une situation de conversation d'une unité (jeu de rôle pratiqué à l'oral).
 */
final readonly class Situation
{
    /**
     * @param  list<string>  $tags  thèmes (work, market, travel…) servant à la personnalisation
     */
    public function __construct(
        public string $title,
        public string $description,
        public array $tags,
    ) {}
}
