<?php

namespace App\Enums;

/**
 * État d'une candidature au programme d'ambassadeurs.
 */
enum ApplicationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À examiner',
            self::Accepted => 'Acceptée',
            self::Rejected => 'Refusée',
        };
    }
}
