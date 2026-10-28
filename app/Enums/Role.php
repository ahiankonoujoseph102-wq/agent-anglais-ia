<?php

namespace App\Enums;

enum Role: string
{
    case Learner = 'learner';
    case Admin = 'admin';
    // Aide l'administrateur principal, seulement sur les pages de App\Support\Admin\AdminAccess::ASSISTANT_ROUTES.
    case Assistant = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::Learner => 'Apprenant',
            self::Admin => 'Administrateur',
            self::Assistant => 'Assistant',
        };
    }
}
