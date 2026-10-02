<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->string('currency', 10);
            // Prestataire de paiement (fedapay) et moyen utilisé (flooz, mixx…).
            $table->string('provider', 30)->default('fedapay');
            $table->string('method', 30)->nullable();
            // Numéro mobile money débité.
            $table->string('phone', 20)->nullable();
            // Référence interne, envoyée au prestataire.
            $table->string('reference', 64)->unique();
            // Identifiant de la transaction chez le prestataire.
            $table->string('provider_transaction_id', 100)->nullable()->index();
            // pending | succeeded | failed | cancelled. Voir App\Enums\PaymentStatus.
            $table->string('status', 20)->default('pending')->index();
            $table->dateTime('paid_at')->nullable();
            // Dernière réponse brute du prestataire, pour le support.
            $table->json('provider_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
