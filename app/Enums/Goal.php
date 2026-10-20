<?php

namespace App\Enums;

/**
 * Objectif principal de l'apprenant (questionnaire du programme).
 */
enum Goal: string
{
    case Work = 'work';
    case Studies = 'studies';
    case Travel = 'travel';
    case Business = 'business';
    case Exam = 'exam';
    case Conversation = 'conversation';
    case StudyAbroad = 'study_abroad';
    case Interview = 'interview';
    case Presentation = 'presentation';
    case Clients = 'clients';
    case Confidence = 'confidence';

    public function label(): string
    {
        return match ($this) {
            self::Work => 'Le travail',
            self::Studies => 'Les études',
            self::Travel => 'Les voyages',
            self::Business => 'Les affaires',
            self::Exam => 'Un examen',
            self::Conversation => 'La conversation courante',
            self::StudyAbroad => 'Étudier ou voyager à l\'étranger',
            self::Interview => 'Préparer un entretien (bourse, admission, visa, stage, embauche)',
            self::Presentation => 'Faire un exposé ou une soutenance en anglais',
            self::Clients => 'Parler avec des clients ou partenaires',
            self::Confidence => 'Avoir confiance et oser parler',
        };
    }

    /**
     * Thèmes de situations mis en avant pour cet objectif (voir resources/curriculum).
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return match ($this) {
            self::Work => ['work', 'admin'],
            self::Studies => ['studies', 'education'],
            self::Travel => ['travel', 'transport'],
            self::Business => ['business', 'market'],
            self::Exam => ['exam', 'studies'],
            self::Conversation => ['conversation', 'family'],
            self::StudyAbroad => ['studies', 'travel'],
            self::Interview => ['exam', 'work'],
            self::Presentation => ['studies', 'education'],
            self::Clients => ['business', 'phone'],
            self::Confidence => ['conversation', 'family'],
        };
    }
}
