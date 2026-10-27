<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jetons des séances en direct répartis par nature (texte, audio, mis en cache), nombre de tours et plus grand
 * contexte d'un tour : le coût est estimé au vrai tarif de chaque nature (les consignes, en texte, coûtent bien
 * moins cher que l'audio). Colonnes vides pour les séances d'avant : elles restent estimées au tarif audio.
 */
return new class extends Migration
{
    private const TABLES = ['assessments', 'lessons', 'level_tests', 'course_sessions'];

    private const COLUMNS = [
        'input_text_tokens', 'input_audio_tokens', 'cached_text_tokens', 'cached_audio_tokens',
        'output_text_tokens', 'output_audio_tokens',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                foreach (self::COLUMNS as $column) {
                    $table->unsignedBigInteger($column)->nullable();
                }

                $table->unsignedInteger('turns')->default(0);
                $table->unsignedInteger('max_context_tokens')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn([...self::COLUMNS, 'turns', 'max_context_tokens']);
            });
        }
    }
};
