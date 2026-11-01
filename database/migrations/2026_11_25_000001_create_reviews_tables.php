<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avis des élèves (un par compte) et minutes offertes (un merci de 5 minutes pour tout avis, une seule fois).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            // pending (attend l'administrateur), published, hidden (retiré par l'administrateur).
            $table->string('status', 12)->default('pending')->index();
            // Raisons de l'attente (note basse, lien, numéro, mot grossier, marque).
            $table->json('flags')->nullable();
            // Ce que l'avis montre, figé à l'envoi : abonné, ambassadeur, accès offert ou évaluation gratuite ; niveau, pays.
            $table->string('kind', 12);
            $table->string('level', 2)->nullable();
            $table->string('country', 2)->nullable();
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('time_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Raison (review : avis envoyé) : une seule par compte et par raison.
            $table->string('reason', 20);
            $table->unsignedInteger('seconds');
            // Jour où les minutes s'ajoutent au temps du jour (premier jour avec un accès en cours) ; vide : à venir.
            $table->date('applied_on')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_bonuses');
        Schema::dropIfExists('reviews');
    }
};
