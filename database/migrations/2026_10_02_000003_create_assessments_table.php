<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            // unique : une seule évaluation gratuite par compte, garantie par la base.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // in_progress | completed | abandoned. Voir App\Enums\AssessmentStatus.
            $table->string('status', 20)->default('in_progress');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('used_seconds')->default(0);
            // Niveau CECRL annoncé par le professeur.
            $table->string('level', 2)->nullable();
            // Appréciation du professeur (points forts, points à travailler).
            $table->text('feedback')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
