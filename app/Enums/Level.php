<?php

namespace App\Enums;

/**
 * Niveaux du Cadre européen commun de référence pour les langues (CECRL).
 */
enum Level: string
{
    case A1 = 'A1';
    case A2 = 'A2';
    case B1 = 'B1';
    case B2 = 'B2';
    case C1 = 'C1';
    case C2 = 'C2';

    public function label(): string
    {
        return match ($this) {
            self::A1 => 'Débutant',
            self::A2 => 'Élémentaire',
            self::B1 => 'Intermédiaire',
            self::B2 => 'Intermédiaire avancé',
            self::C1 => 'Avancé',
            self::C2 => 'Maîtrise',
        };
    }
}
