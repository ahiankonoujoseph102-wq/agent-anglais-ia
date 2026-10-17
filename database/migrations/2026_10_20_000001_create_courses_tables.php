<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * « Mes cours » : leçons d'anglais apportées par l'apprenant (photos ou PDF), contenu lu par
     * le modèle, et séances de Teacher Joe sur ces leçons (consommation SÉPARÉE des séances orales).
     */
    public function up(): void
    {
        Schema::create('course_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            // photos | pdf.
            $table->string('source', 10);
            $table->unsignedTinyInteger('page_count');
            // Contenu lu : points de grammaire, vocabulaire, exemples, exercices (JSON) et transcription.
            // Enregistré une fois : le fichier n'est pas relu à chaque séance.
            $table->json('content');
            $table->longText('text')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'id']);
        });

        Schema::create('course_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            // Chemin sur le disque PRIVÉ (App\Support\PrivateStorage), jamais d'adresse publique.
            $table->string('path');
            $table->string('mime', 40);
            $table->unsignedInteger('size');
            $table->timestamps();

            $table->unique(['course_lesson_id', 'position']);
        });

        Schema::create('course_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // La leçon peut être supprimée : la séance reste (compteur du jour, consommation).
            $table->foreignId('course_lesson_id')->nullable()->constrained()->nullOnDelete();
            // Jour de la séance (fuseau de l'application), pour le décompte quotidien.
            $table->date('date');
            $table->unsignedInteger('allowed_seconds');
            $table->unsignedInteger('used_seconds')->default(0);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            // in_progress | completed | interrupted. Voir App\Enums\LessonStatus.
            $table->string('status', 20)->default('in_progress');
            // Colonnes de suivi communes aux conversations vocales (App\Models\Concerns\IsLiveConversation).
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->unsignedSmallInteger('connections')->default(0);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_sessions');
        Schema::dropIfExists('course_pages');
        Schema::dropIfExists('course_lessons');
    }
};
