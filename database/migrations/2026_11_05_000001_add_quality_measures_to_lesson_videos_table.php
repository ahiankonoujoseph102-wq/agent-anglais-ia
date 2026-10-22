<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesures de chaque vidéo fabriquée : accord entre la voix et la bouche, ouverture de la bouche pendant la parole,
 * sonie finale et crête (page « Vidéos »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_videos', function (Blueprint $table) {
            $table->decimal('lips_correlation', 4, 2)->nullable()->after('size_bytes');
            $table->decimal('lips_open', 5, 3)->nullable()->after('lips_correlation');
            $table->decimal('loudness_lufs', 5, 1)->nullable()->after('lips_open');
            $table->decimal('true_peak_db', 5, 1)->nullable()->after('loudness_lufs');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_videos', function (Blueprint $table) {
            $table->dropColumn(['lips_correlation', 'lips_open', 'loudness_lufs', 'true_peak_db']);
        });
    }
};
