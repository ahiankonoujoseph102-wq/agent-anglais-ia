<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi des élèves payants : rappels avant la séance du jour (30 minutes et 1 minute avant), relance d'une séance
 * manquée, et téléphones des élèves abonnés aux notifications (table à part : jamais mêlés aux téléphones de
 * l'administrateur, qui reçoivent les alertes de paiement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint_hash', 64)->unique('learner_push_subscriptions_endpoint_hash_unique');
            $table->text('endpoint');
            $table->string('public_key', 255);
            $table->string('auth_token', 255);
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->string('device', 120)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('learner_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Jour de la séance prévue (dans le fuseau de l'élève).
            $table->date('day');
            // soon (30 minutes avant), now (1 minute avant), missed (2 heures après, séance pas faite).
            $table->string('kind', 10);
            // sent, failed, none (aucun téléphone abonné), skipped (canal non utilisé).
            $table->string('push_status', 10)->nullable();
            // sent, failed, quota, no_email, skipped.
            $table->string('mail_status', 10)->nullable();
            $table->string('mail_error', 200)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'day', 'kind']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_reminders');
        Schema::dropIfExists('learner_push_subscriptions');
    }
};
