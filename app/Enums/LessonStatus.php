<?php

namespace App\Enums;

enum LessonStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Interrupted = 'interrupted';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'En cours',
            self::Completed => 'Terminée',
            self::Interrupted => 'Interrompue',
        };
    }
}
