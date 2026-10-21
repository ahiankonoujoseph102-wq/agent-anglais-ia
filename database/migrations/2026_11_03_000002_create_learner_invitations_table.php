<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations à refaire l'évaluation gratuite après une remise à zéro (app:reset-learners --notify), envoyées par
 * lots chaque heure (learners:send-invitations).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // pending, sent, failed, no_email
            $table->string('status', 20)->index();
            $table->string('error', 200)->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_invitations');
    }
};
