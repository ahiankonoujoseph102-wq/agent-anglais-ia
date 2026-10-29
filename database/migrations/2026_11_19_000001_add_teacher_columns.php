<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Professeurs (config/teachers.php) : professeur choisi par l'apprenant (users.teacher, vide : professeur par défaut)
 * et professeur de chaque séance (vide pour les séances d'avant : Teacher Joe, le seul professeur d'alors).
 */
return new class extends Migration
{
    private const TABLES = ['users', 'assessments', 'lessons', 'level_tests', 'course_sessions'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('teacher', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('teacher');
            });
        }
    }
};
