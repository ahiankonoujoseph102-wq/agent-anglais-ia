<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Séance d'essai gratuite (subscriptions.is_trial), e-mails de relance des inscrits (learner_mails, un par
 * campagne et par compte) et refus des e-mails de relance (users.follow_up_opt_out_at, lien « Ne plus recevoir »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('is_trial')->default(false)->after('granted_days');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('follow_up_opt_out_at')->nullable();
            // Message de bienvenue de la séance d'essai déjà vu (celui d'une formule payée garde welcomed_at).
            $table->timestamp('trial_welcomed_at')->nullable();
        });

        Schema::create('learner_mails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('campaign', 20);
            $table->string('status', 10);
            $table->string('error', 200)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'campaign']);
            $table->index(['status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_mails');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['follow_up_opt_out_at', 'trial_welcomed_at']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('is_trial');
        });
    }
};
