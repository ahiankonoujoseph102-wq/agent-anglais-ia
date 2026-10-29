<?php

namespace App\Console\Commands;

use App\Support\Videos\ClubVideos;
use App\Support\Videos\LessonVideos;
use App\Support\Videos\PresentationVideos;
use App\Support\Videos\VideoSettings;
use App\Support\Videos\VideoTopics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Vidéos de leçon : avec --plan, ajoute les vidéos du jour (seulement si le jeton de GitHub Actions est réglé) ;
 * à chaque passage, la vidéo du jour du Club des abonnés si elle manque ;
 * puis écrit les scripts et prépare les voix en attente, dans le temps accordé. Lancée chaque minute par le
 * planificateur (sans --plan) et chaque matin (avec --plan). Ne fait jamais échouer une tâche.
 */
class PrepareVideos extends Command
{
    protected $signature = 'videos:prepare
        {--plan : Ajouter d\'abord les vidéos du jour qui manquent}
        {--budget= : Temps accordé, en secondes}';

    protected $description = 'Prépare les vidéos de leçon (sujet, script, voix) ; avec --plan, ajoute celles du jour';

    public function handle(LessonVideos $videos, PresentationVideos $presentations): int
    {
        // Une seule préparation à la fois (la tâche du matin et celle de chaque minute peuvent se croiser).
        $lock = Cache::lock('videos:prepare', 600);

        if (! $lock->get()) {
            $this->line('Une préparation des vidéos est déjà en cours.');

            return self::SUCCESS;
        }

        try {
            return $this->work($videos, $presentations);
        } finally {
            $lock->release();
        }
    }

    private function work(LessonVideos $videos, PresentationVideos $presentations): int
    {
        $budget = (int) ($this->option('budget') ?: config('videos.prepare_budget'));
        $deadline = microtime(true) + $budget;

        if ($this->option('plan')) {
            if (blank(config('videos.token'))) {
                $this->line('Jeton des vidéos non réglé (VIDEO_UPLOAD_TOKEN) : aucune vidéo ajoutée automatiquement.');
            } else {
                $created = $videos->planToday();
                $this->line("Vidéos ajoutées pour aujourd'hui : {$created}. Sujets encore jamais traités : ".VideoTopics::remaining().'.');
            }
        }

        // Vidéo du jour du Club des abonnés : ajoutée dès qu'elle manque (chaque minute), jamais deux le même jour.
        if (filled(config('videos.token')) && ($club = app(ClubVideos::class)->ensureToday()) !== null) {
            $this->line('Vidéo du Club des abonnés ajoutée pour aujourd\'hui : '.$club->themeLabel().'.');
        }

        if (($blocked = VideoSettings::blocked()) !== null) {
            $this->warn($blocked);
        }

        $result = $videos->prepare($budget);
        $this->info("Scripts écrits : {$result['scripted']}, voix prêtes : {$result['voiced']}, encore en préparation : {$result['waiting']}.");

        // Vidéos de présentation demandées dans l'administration : leurs voix, avec le temps qui reste.
        $presented = $presentations->prepare($deadline);

        if ($presented['voiced'] > 0 || $presented['waiting'] > 0) {
            $this->info("Vidéos de présentation : voix prêtes : {$presented['voiced']}, encore en préparation : {$presented['waiting']}.");
        }

        return self::SUCCESS;
    }
}
