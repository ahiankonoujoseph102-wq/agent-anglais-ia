<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provenance d'un inscrit (App\Support\Origins) : la première page vue sur le site avant l'inscription, le site
 * d'origine (nom de domaine seulement, jamais l'adresse complète) et les paramètres utm_* d'un lien partagé.
 * Aucun outil de mesure extérieur : ces informations viennent de la visite elle-même et restent dans la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_origins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('landing_path', 200)->nullable();
            $table->string('referrer_host', 120)->nullable();
            $table->string('channel', 40);
            $table->string('utm_source', 60)->nullable();
            $table->string('utm_medium', 60)->nullable();
            $table->string('utm_campaign', 60)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamps();

            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_origins');
    }
};
