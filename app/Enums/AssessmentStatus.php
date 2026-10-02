<?php

namespace App\Enums;

enum AssessmentStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'En cours',
            self::Completed => 'Terminée',
            self::Abandoned => 'Interrompue',
        };
    }
}
