<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « Mes cours » : document personnel de révision préparé après une séance (une fois par séance), et la partie
 * du PDF travaillée pendant la séance (le document porte sur elle, même si l'élève en choisit une autre ensuite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_sessions', function (Blueprint $table) {
            $table->foreignId('course_part_id')->nullable()->after('course_lesson_id')->constrained('course_parts')->nullOnDelete();
        });

        Schema::create('course_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Une seule par séance ; supprimée avec la leçon (CourseLesson::purge efface d'abord le PDF).
            $table->foreignId('course_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('course_lesson_id')->nullable()->constrained()->cascadeOnDelete();
            // pending (à préparer), ready (prêt), failed (essais épuisés), skipped (séance trop courte).
            $table->string('status', 20)->default('pending')->index();
            $table->string('reason', 40)->nullable();
            $table->json('content')->nullable();
            // PDF sur le disque privé (App\Support\PrivateStorage), jamais public.
            $table->string('path')->nullable();
            $table->string('provider', 20)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            // E-mail : sent, failed, quota (limite du jour atteinte), no_email ; détail court sans donnée personnelle.
            $table->string('mail_status', 20)->nullable();
            $table->string('mail_error', 200)->nullable();
            $table->timestamp('mailed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_reviews');

        Schema::table('course_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_part_id');
        });
    }
};
