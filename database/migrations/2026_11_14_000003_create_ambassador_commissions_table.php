<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commissions des ambassadeurs : au plus UNE par compte rattaché (sur son premier paiement validé, ou ajoutée à la
 * main par l'administrateur pour un paiement hors des quatre pays). Montants en francs CFA, entiers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambassador_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ambassador_id')->constrained()->cascadeOnDelete();
            // Compte inscrit grâce à l'ambassadeur : une seule commission par compte.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            // payment (premier paiement validé) ou manual (ajoutée par l'administrateur).
            $table->string('source', 10);
            // Copie de la formule et de son prix au moment de la commission.
            $table->string('plan', 30);
            $table->string('plan_name', 60);
            $table->unsignedInteger('base_amount');
            $table->unsignedTinyInteger('percent');
            $table->unsignedInteger('amount');
            $table->string('status', 10)->default('due')->index();
            $table->json('flags')->nullable();
            $table->string('note', 200)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambassador_commissions');
    }
};
