<?php

namespace App\Enums;

enum ErrorStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'À revoir',
            self::Resolved => 'Corrigée',
        };
    }
}
