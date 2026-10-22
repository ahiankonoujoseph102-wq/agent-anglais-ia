<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vidéos de leçon de Teacher Joe (App\Models\LessonVideo) : un sujet n'est JAMAIS traité deux fois (topic_key unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_videos', function (Blueprint $table) {
            $table->id();
            $table->string('topic_key', 80)->unique();
            $table->date('day')->index();
            $table->unsignedTinyInteger('slot');
            $table->string('level', 2);
            $table->string('title', 160);
            // planned, scripted, voiced, rendering, ready, failed
            $table->string('status', 20)->index();
            $table->json('script')->nullable();
            $table->text('caption')->nullable();
            $table->string('hashtags', 300)->nullable();
            $table->string('text_provider', 20)->nullable();
            $table->string('voice_provider', 20)->nullable();
            $table->string('voice_name', 60)->nullable();
            $table->json('timings')->nullable();
            $table->string('audio_path')->nullable();
            $table->string('video_path')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('error', 300)->nullable();
            $table->timestamp('render_started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['day', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_videos');
    }
};
