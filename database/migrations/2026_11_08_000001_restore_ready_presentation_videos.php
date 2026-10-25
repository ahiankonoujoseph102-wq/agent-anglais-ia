<?php

use App\Models\LessonVideo;
use App\Support\PrivateStorage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Correction du 25 octobre 2026 : « Préparer les vidéos de présentation » (toutes) remettait en « Voix prête » des
 * vidéos déjà prêtes, sans que personne ait demandé de les refaire. Une vidéo « Voix prête » dont les deux fichiers
 * (TikTok et WhatsApp) sont encore sur le disque privé redevient « Prête », telle quelle : rien n'est refait.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $disk = PrivateStorage::disk();
        } catch (Throwable) {
            $disk = null;
        }

        $rows = DB::table('presentation_videos')
            ->where('status', LessonVideo::VOICED)
            ->whereNotNull('video_path')
            ->whereNotNull('light_path')
            ->whereNotNull('ready_at')
            ->get(['id', 'video_path', 'light_path']);

        foreach ($rows as $row) {
            try {
                $present = $disk === null || ($disk->exists($row->video_path) && $disk->exists($row->light_path));
            } catch (Throwable) {
                $present = false;
            }

            if ($present) {
                DB::table('presentation_videos')->where('id', $row->id)->where('status', LessonVideo::VOICED)
                    ->update(['status' => LessonVideo::READY, 'attempts' => 0, 'error' => null, 'render_started_at' => null, 'next_attempt_at' => null]);
            }
        }
    }

    public function down(): void
    {
        // Rien à défaire : les vidéos remises « Prête » l'étaient avant l'erreur.
    }
};
