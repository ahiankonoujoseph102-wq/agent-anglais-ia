<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « Mes cours » : parties d'un PDF choisies par l'élève (pages ou passage décrit), lues une fois et gardées
 * pour revenir un autre jour sans renvoyer le fichier. La leçon retient la partie en cours de travail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('page_from')->nullable();
            $table->unsignedSmallInteger('page_to')->nullable();
            // Passage décrit par l'élève (« l'exercice 2 »), tel qu'il l'a écrit.
            $table->string('request', 200)->nullable();
            // Contenu lu (même forme que course_lessons.content) ; nul quand la leçon entière est déjà lue.
            $table->json('content')->nullable();
            $table->longText('text')->nullable();
            $table->string('provider', 20)->nullable();
            $table->timestamps();
        });

        Schema::table('course_lessons', function (Blueprint $table) {
            $table->foreignId('current_part_id')->nullable()->after('page_count')->constrained('course_parts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_part_id');
        });

        Schema::dropIfExists('course_parts');
    }
};
