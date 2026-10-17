<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nouveau parcours de paiement : l'apprenant envoie seulement la capture de son paiement,
 * l'IA la lit, des règles décident d'un accès provisoire de 24 heures en attendant la
 * validation de l'administrateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Plus demandés à l'apprenant (gardés pour les anciens paiements).
            $table->string('payer_phone', 30)->nullable()->change();
            $table->string('reference', 100)->nullable()->change();

            // Empreinte SHA-256 de la capture : une même image ne sert qu'une fois.
            $table->string('proof_hash', 64)->nullable()->unique()->after('proof_path');
            // Numéro de transaction lu sur la capture, normalisé : jamais utilisé deux fois.
            $table->string('transaction_key', 100)->nullable()->unique()->after('proof_hash');
            // Données lues par l'IA (opérateur, montant, date…) et résultat de chaque règle.
            $table->json('analysis')->nullable()->after('transaction_key');
            $table->json('checks')->nullable()->after('analysis');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // Fin de l'accès provisoire (statut « provisional ») ; null une fois le paiement validé.
            $table->dateTime('provisional_until')->nullable()->after('ends_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['provisional_until']);
            $table->dropColumn('provisional_until');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['proof_hash']);
            $table->dropUnique(['transaction_key']);
            $table->dropColumn(['proof_hash', 'transaction_key', 'analysis', 'checks']);
        });
    }
};
