<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transcription de l'évaluation (sous-titres envoyés avec les signaux du navigateur) :
 * si le temps se termine avant le verdict du professeur, le niveau est estimé à partir
 * de ce qui a été dit (App\Support\Gemini\AssessmentEstimator).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->text('transcript')->nullable()->after('feedback');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('transcript');
        });
    }
};
