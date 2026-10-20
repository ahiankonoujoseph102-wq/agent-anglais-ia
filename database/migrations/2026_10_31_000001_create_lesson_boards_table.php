<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tableaux des séances (App\Support\Curriculum\LessonBoards) : un par niveau, unité, leçon, situation et
 * objectif, préparé une fois par le modèle texte, partagé par tous les apprenants qui ont la même leçon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_boards', function (Blueprint $table) {
            $table->id();
            // Niveau, unité, leçon, situation, objectif et version du contenu : la clé du tableau.
            $table->string('key', 120)->unique();
            $table->string('level', 2);
            $table->unsignedTinyInteger('unit_number');
            $table->unsignedTinyInteger('lesson_number');
            $table->unsignedTinyInteger('situation_index');
            $table->string('goal', 20);
            $table->json('content');
            $table->string('provider', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_boards');
    }
};
