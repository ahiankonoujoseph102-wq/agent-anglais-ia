<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Page de la séance (App\Support\Live\LivePage) : une seule page à la fois ; une autre page encore ouverte
 * (deuxième onglet ou deuxième téléphone) s'arrête d'elle-même.
 */
return new class extends Migration
{
    private const TABLES = ['assessments', 'lessons', 'level_tests', 'course_sessions'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('live_page', 40)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('live_page');
            });
        }
    }
};
