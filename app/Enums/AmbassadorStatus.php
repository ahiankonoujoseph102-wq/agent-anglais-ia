<?php

namespace App\Enums;

enum AmbassadorStatus: string
{
    case Active = 'active';
    // Retiré du programme : sa place se libère, son accès gratuit s'arrête.
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Removed => 'Retiré du programme',
        };
    }
}
