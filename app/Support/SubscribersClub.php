<?php

namespace App\Support;

use App\Enums\SubscriptionStatus;
use App\Models\User;

/**
 * Club des abonnés : lien du groupe WhatsApp réservé aux abonnés PAYANTS ACTIFS, réglé dans « Réglages »
 * (clé club.whatsapp_link de la table settings, valeur de départ posée par une migration). Champ vide : rien
 * n'est montré (aucune valeur par défaut dans la configuration).
 *
 * Abonné payant actif = formule payée ET validée par l'équipe, en cours : jamais un accès offert, un mois
 * d'ambassadeur, un accès provisoire (capture pas encore vérifiée) ni un abonnement terminé.
 */
final class SubscribersClub
{
    public const SETTING = 'club.whatsapp_link';

    public static function link(): ?string
    {
        return SiteSettings::saved(self::SETTING);
    }

    public static function isMember(?User $user): bool
    {
        if ($user === null || $user->isAdmin()) {
            return false;
        }

        $subscription = $user->activeSubscription;

        return $subscription !== null
            && $subscription->status === SubscriptionStatus::Active
            && $subscription->granted_days === null
            && $subscription->ambassador_id === null;
    }

    /** Lien à montrer à ce compte, ou null (pas abonné payant actif, ou champ vide). */
    public static function linkFor(?User $user): ?string
    {
        return self::isMember($user) ? self::link() : null;
    }
}
