<?php

namespace App\Enums;

/**
 * Ce que l'apprenant trouve le plus difficile (questionnaire du programme).
 */
enum Difficulty: string
{
    case Speaking = 'speaking';
    case Listening = 'listening';
    case Pronunciation = 'pronunciation';
    case Grammar = 'grammar';
    case Vocabulary = 'vocabulary';

    public function label(): string
    {
        return match ($this) {
            self::Speaking => 'Parler',
            self::Listening => 'Comprendre',
            self::Pronunciation => 'La prononciation',
            self::Grammar => 'La grammaire',
            self::Vocabulary => 'Le vocabulaire',
        };
    }
}
