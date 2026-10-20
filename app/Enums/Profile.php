<?php

namespace App\Enums;

/**
 * Profil de l'apprenant (questionnaire du programme, écran 2, facultatif).
 */
enum Profile: string
{
    case Student = 'student';
    case Pupil = 'pupil';
    case Teacher = 'teacher';
    case Trader = 'trader';
    case Parent = 'parent';
    case Employee = 'employee';
    case JobSeeker = 'job_seeker';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Étudiant(e)',
            self::Pupil => 'Élève',
            self::Teacher => 'Enseignant(e)',
            self::Trader => 'Commerçant(e)',
            self::Parent => 'Parent',
            self::Employee => 'Salarié(e)',
            self::JobSeeker => 'En recherche d\'emploi',
            self::Other => 'Autre',
        };
    }

    /**
     * Thèmes de situations mis en avant pour ce profil, comme le métier (voir resources/curriculum).
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return match ($this) {
            self::Student, self::Pupil => ['studies'],
            self::Teacher => ['education'],
            self::Trader => ['business', 'market'],
            self::Parent => ['family'],
            self::Employee, self::JobSeeker => ['work'],
            self::Other => [],
        };
    }
}
