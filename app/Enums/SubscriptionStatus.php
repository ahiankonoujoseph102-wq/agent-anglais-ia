<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    // Accès provisoire de 24 heures (capture lue et vérifiée), en attendant la validation.
    case Provisional = 'provisional';
    // Accès provisoire terminé sans décision : réactivé si le paiement est validé.
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente de paiement',
            self::Active => 'Active',
            self::Provisional => 'Accès provisoire',
            self::Suspended => 'Accès provisoire terminé',
            self::Expired => 'Expirée',
            self::Cancelled => 'Annulée',
        };
    }
}
