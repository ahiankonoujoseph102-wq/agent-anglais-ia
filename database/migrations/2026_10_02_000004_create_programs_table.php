<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Niveau de départ et niveau visé (CECRL).
            $table->string('start_level', 2)->nullable();
            $table->string('target_level', 2)->nullable();
            // Objectifs exprimés par l'apprenant (travail, voyage, examen…).
            $table->text('goals')->nullable();
            // Plan de cours construit avec le professeur (thèmes, étapes…).
            $table->json('content')->nullable();
            // Un seul programme actif à la fois ; les anciens sont conservés.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
