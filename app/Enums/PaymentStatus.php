<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente de validation',
            self::Validated => 'Validé',
            self::Rejected => 'Refusé',
        };
    }
}
