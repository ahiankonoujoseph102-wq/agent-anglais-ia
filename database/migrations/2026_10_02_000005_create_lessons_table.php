<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Séances quotidiennes (la table « sessions » est déjà prise par Laravel).
     */
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            // Jour de la séance (fuseau de l'application), pour le décompte quotidien.
            $table->date('date');
            // Durée autorisée au démarrage et durée réellement consommée.
            $table->unsignedInteger('allowed_seconds');
            $table->unsignedInteger('used_seconds')->default(0);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            // in_progress | completed | interrupted. Voir App\Enums\LessonStatus.
            $table->string('status', 20)->default('in_progress');
            $table->string('topic')->nullable();
            // Résumé de la séance rédigé par le professeur.
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
