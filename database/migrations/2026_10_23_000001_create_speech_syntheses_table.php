<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chaque synthèse vocale réussie (messages, essais), avec son fournisseur et une estimation du coût,
 * pour la page « Consommation ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speech_syntheses', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->index();
            $table->string('model', 80);
            $table->string('voice', 40);
            $table->string('label', 160);
            $table->unsignedInteger('characters');
            $table->unsignedInteger('audio_milliseconds');
            // Dollars US, estimation (tarif de config/services.php au moment de la synthèse).
            $table->decimal('cost_usd', 10, 6);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speech_syntheses');
    }
};
