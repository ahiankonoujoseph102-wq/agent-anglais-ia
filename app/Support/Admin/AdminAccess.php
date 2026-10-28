<?php

namespace App\Support\Admin;

use App\Enums\Role;
use App\Models\User;

/**
 * Qui peut ouvrir quoi dans l'administration. Deux rôles d'équipe (colonne users.role, App\Enums\Role) :
 * - Administrateur (principal) : tout ;
 * - Assistant : SEULEMENT les routes de ASSISTANT_ROUTES. Tout le reste lui est refusé par défaut (une page
 *   ajoutée plus tard aussi), par le middleware « admin » (adresse tapée à la main : 403), le menu et les boutons
 *   (porte « admin-route » : @can('admin-route', 'admin.users.grant')).
 */
final class AdminAccess
{
    /**
     * Permis à un Assistant : valider ou refuser les captures, voir « Inscrits » (fiche en lecture, sans actions),
     * « Abonnés payants », « Inactifs », « Provenance », les candidatures et la liste d'attente des ambassadeurs,
     * et relancer sur WhatsApp. Jamais : « Réglages », coûts et « Consommation », diagnostics, historique des
     * paiements, rôles, accès offerts, conversations, commissions, vidéos.
     */
    public const ASSISTANT_ROUTES = [
        'admin.home',
        'admin.payments.pending',
        'admin.payments.proof',
        'admin.payments.approve',
        'admin.payments.reject',
        'admin.users.index',
        'admin.users.show',
        'admin.subscribers.index',
        'admin.inactive.index',
        'admin.inactive.whatsapp',
        'admin.origins.index',
        'admin.ambassadors.index',
        'admin.waitlist.notify',
    ];

    /** Membre de l'équipe : administrateur principal ou assistant. */
    public static function isStaff(?User $user): bool
    {
        return $user !== null && in_array($user->role, [Role::Admin, Role::Assistant], true);
    }

    public static function allows(?User $user, ?string $route): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->role === Role::Admin) {
            return true;
        }

        return $user->role === Role::Assistant && $route !== null && in_array($route, self::ASSISTANT_ROUTES, true);
    }
}
