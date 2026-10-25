<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Version « site » des vidéos de présentation (page « Comment ça marche ») : une vidéo très légère (480 × 854) et son
 * image d'aperçu, tirées par GitHub Actions de la version WhatsApp déjà fabriquée (App\Support\Videos\SiteVideos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentation_videos', function (Blueprint $table) {
            $table->string('web_path')->nullable()->after('light_path');
            $table->string('poster_path')->nullable()->after('web_path');
            $table->unsignedBigInteger('web_size_bytes')->nullable()->after('light_size_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('presentation_videos', function (Blueprint $table) {
            $table->dropColumn(['web_path', 'poster_path', 'web_size_bytes']);
        });
    }
};
