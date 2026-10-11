<?php

namespace App\Enums;

/**
 * Les cinq compétences notées de 1 à 5 à chaque séance.
 */
enum Skill: string
{
    case Fluency = 'fluency';
    case Pronunciation = 'pronunciation';
    case Grammar = 'grammar';
    case Vocabulary = 'vocabulary';
    case Comprehension = 'comprehension';

    public function label(): string
    {
        return match ($this) {
            self::Fluency => 'Aisance',
            self::Pronunciation => 'Prononciation',
            self::Grammar => 'Grammaire',
            self::Vocabulary => 'Vocabulaire',
            self::Comprehension => 'Compréhension',
        };
    }

    /**
     * Colonne de la table lessons qui contient la note.
     */
    public function column(): string
    {
        return 'score_'.$this->value;
    }
}
