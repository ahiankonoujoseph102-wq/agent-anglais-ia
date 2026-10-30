<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vidéos de promotion et du Club envoyées par e-mail au fondateur (App\Support\Videos\VideoMailer), puis retirées du
 * stockage. Les vidéos déjà prêtes avant ce changement ne sont PAS envoyées (« skipped ») : leurs fichiers restent
 * jusqu'au nettoyage décidé par le fondateur (php artisan videos:storage).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_videos', function (Blueprint $table) {
            $table->string('mail_path')->nullable()->after('video_path');
            $table->unsignedInteger('mail_size_bytes')->nullable()->after('mail_path');
            $table->string('mail_status', 20)->nullable()->after('mail_size_bytes');
            $table->string('mail_error', 300)->nullable()->after('mail_status');
            $table->unsignedSmallInteger('mail_attempts')->default(0)->after('mail_error');
            $table->timestamp('next_mail_at')->nullable()->after('mail_attempts');
            $table->timestamp('mailed_at')->nullable()->after('next_mail_at');
            $table->timestamp('purged_at')->nullable()->after('mailed_at');
        });

        DB::table('lesson_videos')->whereIn('status', ['ready', 'failed'])->update(['mail_status' => 'skipped']);
    }

    public function down(): void
    {
        Schema::table('lesson_videos', function (Blueprint $table) {
            $table->dropColumn(['mail_path', 'mail_size_bytes', 'mail_status', 'mail_error', 'mail_attempts', 'next_mail_at', 'mailed_at', 'purged_at']);
        });
    }
};
