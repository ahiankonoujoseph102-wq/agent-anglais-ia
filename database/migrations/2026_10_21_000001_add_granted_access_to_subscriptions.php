<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accès offert par l'administrateur (démonstrations dans les écoles, auprès des parents).
 * C'est un abonnement ordinaire (mêmes droits que la formule choisie) reconnaissable à
 * granted_by ; il n'a jamais de paiement et son prix est 0 : il ne compte dans aucun revenu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Administrateur qui a offert l'accès (null : abonnement payé).
            $table->foreignId('granted_by')->nullable()->after('provisional_until')->constrained('users')->nullOnDelete();
            // Note facultative (« Visite au Collège X ») et durée offerte, gardées pour l'historique.
            $table->string('grant_note', 200)->nullable()->after('granted_by');
            $table->unsignedSmallInteger('granted_days')->nullable()->after('grant_note');
            // Accès retiré par l'administrateur (ends_at est alors ramené à ce moment).
            $table->dateTime('revoked_at')->nullable()->after('granted_days');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('granted_by');
            $table->dropColumn(['grant_note', 'granted_days', 'revoked_at']);
        });
    }
};
