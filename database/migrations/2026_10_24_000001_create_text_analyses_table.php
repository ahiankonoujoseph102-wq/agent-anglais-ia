<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chaque réponse du modèle texte (Gemini ou OpenAI, en secours), avec une estimation du coût, pour la page
 * « Consommation ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('text_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->index();
            $table->string('model', 80);
            $table->string('kind', 30);
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            $table->unsignedInteger('duration_ms');
            // Dollars US, estimation (tarif de config/services.php au moment de la demande).
            $table->decimal('cost_usd', 10, 6);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('text_analyses');
    }
};
