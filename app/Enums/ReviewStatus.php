<?php

namespace App\Enums;

/**
 * État d'un avis d'élève : publié tout de suite, sauf une note très basse ou un texte à vérifier.
 */
enum ReviewStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À vérifier',
            self::Published => 'Publié',
            self::Hidden => 'Retiré',
        };
    }
}
