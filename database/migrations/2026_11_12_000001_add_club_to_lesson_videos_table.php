<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vidéo quotidienne du Club des abonnés (App\Support\Videos\ClubVideos) dans la même table que les vidéos de leçon :
 * kind (« promo » ou « club »), thème du jour et éléments présentés (historique : rien n'est repris avant plusieurs
 * semaines).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_videos', function (Blueprint $table) {
            $table->string('kind', 10)->default('promo')->after('id')->index();
            $table->string('theme', 20)->nullable()->after('kind');
            $table->json('items')->nullable()->after('hashtags');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_videos', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn(['kind', 'theme', 'items']);
        });
    }
};
