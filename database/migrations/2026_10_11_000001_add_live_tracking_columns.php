<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suivi des conversations vocales : limite de temps et mesures de consommation.
     */
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            // Durée autorisée, fixée au démarrage (config platform.assessment.max_minutes).
            $table->unsignedInteger('allowed_seconds')->default(0)->after('ended_at');
        });

        foreach (['assessments', 'lessons'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                // Dernier signal reçu du navigateur.
                $table->dateTime('last_seen_at')->nullable();
                // Fin de validité du dernier jeton éphémère délivré.
                $table->dateTime('token_expires_at')->nullable();
                // Nombre de jetons délivrés (connexions et reconnexions).
                $table->unsignedSmallInteger('connections')->default(0);
                // Jetons consommés, tels que rapportés par l'API Gemini.
                $table->unsignedBigInteger('input_tokens')->default(0);
                $table->unsignedBigInteger('output_tokens')->default(0);
            });
        }

        Schema::table('lessons', function (Blueprint $table) {
            // Devoir donné par le professeur à la fin de la séance.
            $table->text('homework')->nullable()->after('summary');
            $table->index(['status', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['status', 'last_seen_at']);
            $table->dropColumn('homework');
        });

        foreach (['assessments', 'lessons'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['last_seen_at', 'token_expires_at', 'connections', 'input_tokens', 'output_tokens']);
            });
        }

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('allowed_seconds');
        });
    }
};
