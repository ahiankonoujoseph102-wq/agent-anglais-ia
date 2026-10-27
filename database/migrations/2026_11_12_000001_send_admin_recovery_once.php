<?php

use App\Support\Admin\AdminRecovery;
use Illuminate\Database\Migrations\Migration;

/**
 * DÉCLENCHEMENT UNIQUE (octobre 2026, console de Laravel Cloud inaccessible depuis le téléphone) :
 * au déploiement, envoie UNE fois le lien de récupération de l'administrateur (app:send-admin-recovery).
 * Seulement en production ; ne fait jamais échouer le déploiement ; n'écrit ni lien ni mot de passe.
 * À RETIRER par une seconde pull request une fois l'accès retrouvé.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        try {
            AdminRecovery::send();
        } catch (Throwable) {
            // Jamais d'échec du déploiement : la commande app:send-admin-recovery reste disponible.
        }
    }

    public function down(): void {}
};
