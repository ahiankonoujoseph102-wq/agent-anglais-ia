<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fournisseur de chaque séance en direct (Gemini ou OpenAI, en secours) et, chez OpenAI, l'identifiant de
 * l'appel en cours : le serveur raccroche lui-même à la limite de temps.
 */
return new class extends Migration
{
    private const TABLES = ['assessments', 'lessons', 'level_tests', 'course_sessions'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('live_provider', 20)->default('gemini')->index();
                $table->string('live_call_id', 120)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropIndex($name.'_live_provider_index');
                $table->dropColumn(['live_provider', 'live_call_id']);
            });
        }
    }
};
