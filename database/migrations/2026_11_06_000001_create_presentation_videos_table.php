<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vidéos de présentation du site (resources/videos/presentations.php) : une ligne par vidéo (grande et courtes), sa
 * voix préparée par Laravel, puis ses deux fichiers fabriqués par GitHub Actions (TikTok et WhatsApp).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_videos', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('title', 160);
            // planned, scripted, voiced, rendering, ready, failed (mêmes états que lesson_videos)
            $table->string('status', 20)->index();
            $table->json('script')->nullable();
            $table->string('script_hash', 64)->nullable();
            $table->text('caption')->nullable();
            $table->string('voice_provider', 20)->nullable();
            $table->string('voice_name', 60)->nullable();
            $table->json('timings')->nullable();
            $table->string('audio_path')->nullable();
            $table->string('video_path')->nullable();
            $table->string('light_path')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->unsignedInteger('light_size_bytes')->nullable();
            $table->float('lips_correlation')->nullable();
            $table->float('lips_open')->nullable();
            $table->float('loudness_lufs')->nullable();
            $table->float('true_peak_db')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('error', 300)->nullable();
            $table->timestamp('render_started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_videos');
    }
};
