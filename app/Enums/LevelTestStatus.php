<?php

namespace App\Enums;

enum LevelTestStatus: string
{
    case InProgress = 'in_progress';
    case Passed = 'passed';
    case Failed = 'failed';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'En cours',
            self::Passed => 'Réussi',
            self::Failed => 'À repasser',
            self::Abandoned => 'Interrompu',
        };
    }
}
