<?php

namespace App\Enums;

enum ProgramStatus: string
{
    // Les unités du niveau sont en cours.
    case Active = 'active';
    // Les 12 unités sont terminées : bilan oral de fin de niveau à passer.
    case LevelTestDue = 'level_test_due';
    // Bilan réussi : le niveau est validé.
    case Completed = 'completed';
    // Remplacé par un autre programme.
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'En cours',
            self::LevelTestDue => 'Bilan de fin de niveau à passer',
            self::Completed => 'Niveau validé',
            self::Archived => 'Archivé',
        };
    }
}
