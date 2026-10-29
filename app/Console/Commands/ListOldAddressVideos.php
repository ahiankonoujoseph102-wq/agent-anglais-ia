<?php

namespace App\Console\Commands;

use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Support\Videos\OldAddress;
use App\Support\Videos\VideoSettings;
use Illuminate\Console\Command;

/**
 * Lecture seule : liste les vidéos (de leçon, du Club, de présentation) qui disent ou affichent encore l'ancienne
 * adresse du site (talkami.laravel.cloud) d'après leur script gardé, avec leur état. Ne change rien, ne refait rien.
 */
class ListOldAddressVideos extends Command
{
    protected $signature = 'videos:old-address';

    protected $description = 'Liste les vidéos qui disent ou affichent encore l\'ancienne adresse du site (lecture seule)';

    public function handle(): int
    {
        $hosts = OldAddress::hosts();

        if ($hosts === []) {
            $this->info('Aucune ancienne adresse réglée (REDIRECT_FROM_HOSTS vide) : rien à chercher.');

            return self::SUCCESS;
        }

        $this->line('Ancienne adresse cherchée : '.implode(', ', $hosts).'. Adresse des vidéos utilisée maintenant : '.(VideoSettings::site() ?? 'aucune').'.');

        $rows = [];

        foreach (PresentationVideo::query()->orderBy('key')->get() as $video) {
            if ($video->showsOldAddress()) {
                $rows[] = ['Présentation', $video->key, $video->teacher()->name(), $video->title, $video->statusLabel()];
            }
        }

        foreach (LessonVideo::query()->orderBy('day')->orderBy('slot')->get() as $video) {
            if ($video->showsOldAddress()) {
                $kind = $video->kind === LessonVideo::CLUB ? 'Club' : 'Promotion';
                $rows[] = [$kind, $video->day->format('d/m/Y').' n° '.$video->slot, $video->teacher()->name(), $video->title, $video->statusLabel()];
            }
        }

        if ($rows === []) {
            $this->info('Aucune vidéo ne dit ni n\'affiche l\'ancienne adresse.');

            return self::SUCCESS;
        }

        $this->table(['Type', 'Vidéo', 'Professeur', 'Titre', 'État'], $rows);
        $this->line(count($rows).' vidéo(s) à refaire pour montrer '.OldAddress::primary().'. Rien n\'a été changé.');

        return self::SUCCESS;
    }
}
