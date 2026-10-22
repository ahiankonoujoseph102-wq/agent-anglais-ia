<?php

namespace App\Console\Commands;

use App\Support\Videos\LessonVideos;
use App\Support\Videos\VideoSettings;
use App\Support\Videos\VideoTopics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Vidéos de leçon : avec --plan, ajoute les vidéos du jour (seulement si le jeton de GitHub Actions est réglé),
 * puis écrit les scripts et prépare les voix en attente, dans le temps accordé. Lancée chaque minute par le
 * planificateur (sans --plan) et chaque matin (avec --plan). Ne fait jamais échouer une tâche.
 */
class PrepareVideos extends Command
{
    protected $signature = 'videos:prepare
        {--plan : Ajouter d\'abord les vidéos du jour qui manquent}
        {--budget= : Temps accordé, en secondes}';

    protected $description = 'Prépare les vidéos de leçon (sujet, script, voix) ; avec --plan, ajoute celles du jour';

    public function handle(LessonVideos $videos): int
    {
        // Une seule préparation à la fois (la tâche du matin et celle de chaque minute peuvent se croiser).
        $lock = Cache::lock('videos:prepare', 600);

        if (! $lock->get()) {
            $this->line('Une préparation des vidéos est déjà en cours.');

            return self::SUCCESS;
        }

        try {
            return $this->work($videos);
        } finally {
            $lock->release();
        }
    }

    private function work(LessonVideos $videos): int
    {
        if ($this->option('plan')) {
            if (blank(config('videos.token'))) {
                $this->line('Jeton des vidéos non réglé (VIDEO_UPLOAD_TOKEN) : aucune vidéo ajoutée automatiquement.');
            } else {
                $created = $videos->planToday();
                $this->line("Vidéos ajoutées pour aujourd'hui : {$created}. Sujets encore jamais traités : ".VideoTopics::remaining().'.');
            }
        }

        if (($blocked = VideoSettings::blocked()) !== null) {
            $this->warn($blocked);
        }

        $result = $videos->prepare((int) ($this->option('budget') ?: config('videos.prepare_budget')));
        $this->info("Scripts écrits : {$result['scripted']}, voix prêtes : {$result['voiced']}, encore en préparation : {$result['waiting']}.");

        return self::SUCCESS;
    }
}
