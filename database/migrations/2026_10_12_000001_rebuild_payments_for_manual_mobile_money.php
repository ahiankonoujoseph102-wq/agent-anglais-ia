<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paiement manuel par Mobile Money, validé par l'administrateur.
     *
     * La table « payments » de la phase 1 était prévue pour une API de paiement
     * et n'a jamais reçu de données : elle est recréée.
     */
    public function up(): void
    {
        Schema::dropIfExists('payments');

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Abonnement créé à la validation.
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            // Formule choisie, recopiée au moment de l'envoi (config/platform.php).
            $table->string('plan', 50);
            $table->string('plan_name');
            $table->unsignedSmallInteger('daily_minutes');
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedInteger('amount');
            $table->string('currency', 10);

            // Pays et opérateur (clés de config/payment.php) + copie du numéro destinataire.
            $table->string('country', 2)->index();
            $table->string('operator', 30);
            $table->string('operator_name', 50);
            $table->string('recipient_number', 30);

            // Saisi par l'apprenant.
            $table->string('payer_phone', 30);
            $table->string('reference', 100);
            // Référence normalisée, unique tant que le paiement n'est pas refusé
            // (remise à null au refus pour permettre un nouvel envoi corrigé).
            $table->string('reference_key', 100)->nullable()->unique();
            // Capture d'écran, sur le disque privé config('payment.proofs.disk').
            $table->string('proof_path');

            // pending | validated | rejected. Voir App\Enums\PaymentStatus.
            $table->string('status', 20)->default('pending')->index();
            // Égal à user_id tant que le paiement est en attente, null ensuite :
            // la base interdit ainsi deux paiements en attente pour un même apprenant.
            $table->unsignedBigInteger('pending_user_id')->nullable()->unique();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // Mot de passe provisoire donné par l'administrateur : à changer à la prochaine connexion.
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });

        Schema::dropIfExists('payments');

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->string('currency', 10);
            $table->string('provider', 30)->default('manual');
            $table->string('method', 30)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('reference', 64)->unique();
            $table->string('provider_transaction_id', 100)->nullable()->index();
            $table->string('status', 20)->default('pending')->index();
            $table->dateTime('paid_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();
        });
    }
};
