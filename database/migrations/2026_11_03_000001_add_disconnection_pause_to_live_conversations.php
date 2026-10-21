<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temps de séance mis en pause pendant une coupure de connexion (IsLiveConversation) : total des coupures
 * déjà comptées et début de la coupure en cours.
 */
return new class extends Migration
{
    private const TABLES = ['assessments', 'lessons', 'level_tests', 'course_sessions'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedInteger('paused_seconds')->default(0);
                $table->timestamp('disconnected_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['paused_seconds', 'disconnected_at']);
            });
        }
    }
};
