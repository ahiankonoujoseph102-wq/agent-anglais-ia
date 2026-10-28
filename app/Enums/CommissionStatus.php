<?php

namespace App\Enums;

enum CommissionStatus: string
{
    case Due = 'due';
    case Paid = 'paid';
    // Annulée par l'administrateur (triche), avec un motif.
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Due => 'À payer',
            self::Paid => 'Payée',
            self::Cancelled => 'Annulée',
        };
    }
}
