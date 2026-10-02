<?php

namespace App\Enums;

enum Role: string
{
    case Learner = 'learner';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Learner => 'Apprenant',
            self::Admin => 'Administrateur',
        };
    }
}
