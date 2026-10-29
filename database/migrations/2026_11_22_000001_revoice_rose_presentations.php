<?php

use App\Models\LessonVideo;
use App\Support\Videos\PresentationVideos;
use App\Support\Videos\VideoSettings;
use App\Support\Videos\VideoVoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Voix des vidéos de Teacher Rose choisie (« Essai des voix », 29 octobre 2026 : Zephyr) : ses vidéos de présentation
 * (« rose-… ») repartent en préparation pour être redites avec cette voix, en quelques demandes regroupées. Une vidéo
 * déjà prête AVEC cette voix, ou en cours de fabrication, n'est pas touchée. Ne fait jamais échouer le déploiement.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (VideoSettings::blocked() !== null) {
            return;
        }

        try {
            $videos = app(PresentationVideos::class);

            foreach ($videos->all() as $video) {
                if (! str_starts_with($video->key, 'rose-') || $video->status === LessonVideo::RENDERING) {
                    continue;
                }

                $current = VideoVoice::current($video->teacher());
                $sameVoice = $current === null || [$video->voice_provider, $video->voice_name] === [$current['provider'], $current['voice']];

                if ($video->status === LessonVideo::READY && $sameVoice) {
                    continue;
                }

                $videos->request($video->key);
            }
        } catch (Throwable $e) {
            Log::warning('Vidéos de présentation de Teacher Rose non remises en préparation : '.$e->getMessage());
        }
    }

    public function down(): void
    {
        // Rien à défaire : les vidéos gardent leur nouvelle voix.
    }
};
