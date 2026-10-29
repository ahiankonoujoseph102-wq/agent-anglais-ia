<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Professeur d'une vidéo de leçon (promotion ou Club) : Teacher Joe un jour, Teacher Rose le lendemain
 * (App\Support\Videos\VideoTeachers). Fixé à la création, jamais changé ; vide pour les vidéos d'avant = Teacher Joe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_videos', function (Blueprint $table) {
            $table->string('teacher', 20)->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_videos', function (Blueprint $table) {
            $table->dropColumn('teacher');
        });
    }
};
