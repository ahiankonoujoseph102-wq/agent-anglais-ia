<?php

namespace App\Support\Videos;

use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Models\Setting;
use App\Support\PrivateStorage;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * Nettoyage du stockage des vidéos (commande videos:storage) : la liste de ce qui peut être effacé, et l'effacement
 * seulement avec la confirmation écrite du fondateur. Gardé TOUJOURS : versions « site » de « Comment ça marche » (et
 * la version WhatsApp d'une vidéo dont la version « site » reste à fabriquer), voix des présentations, vidéos pas
 * encore envoyées par e-mail ou encore dans leur durée de garde, extraits des voix.
 */
class VideoStorage
{
    /** Prix du stockage de Laravel Cloud, en dollars par gigaoctet et par mois (documentation lue le 30 octobre 2026). */
    public const DOLLARS_PER_GB_MONTH = 0.02;

    private Filesystem $disk;

    public function __construct()
    {
        $this->disk = PrivateStorage::disk();
    }

    /**
     * Ce qui peut être effacé, par groupe.
     *
     * @return array<string, array{label: string, files: list<array{path: string, bytes: int}>}>
     */
    public function proposal(): array
    {
        $groups = [
            'lessons' => ['label' => 'Vidéos de promotion et du Club d\'avant l\'envoi par e-mail (vidéo et voix)', 'files' => []],
            'failed' => ['label' => 'Vidéos de promotion et du Club en échec (voix et morceaux)', 'files' => []],
            'presentations' => ['label' => 'Vidéos de présentation, versions TikTok et WhatsApp (la version « site » est gardée)', 'files' => []],
            'catalogue' => ['label' => 'Catalogue des écrans (vidéo et voix ; refaisable avec GitHub)', 'files' => []],
            'orphans' => ['label' => 'Fichiers du dossier des vidéos qui n\'appartiennent plus à aucune vidéo', 'files' => []],
        ];

        $known = [];

        foreach (LessonVideo::query()->get() as $video) {
            $folder = $video->folder();
            $known[$folder] = true;
            $group = match (true) {
                $video->status === LessonVideo::READY && $video->mail_status === LessonVideo::MAIL_SKIPPED && $video->purged_at === null => 'lessons',
                $video->status === LessonVideo::FAILED && $video->day->lt(today()) => 'failed',
                default => null,
            };

            if ($group !== null) {
                array_push($groups[$group]['files'], ...$this->filesIn($folder));
            }
        }

        foreach ($this->filesIn('videos') as $file) {
            // videos/{jour}/{id}/…
            $folder = implode('/', array_slice(explode('/', $file['path']), 0, 3));

            if (! isset($known[$folder])) {
                $groups['orphans']['files'][] = $file;
            }
        }

        $siteKeys = SiteVideos::keys();

        foreach (PresentationVideo::query()->get() as $presentation) {
            $keepLight = in_array($presentation->key, $siteKeys, true) && $presentation->web_path === null;

            foreach (array_filter([$presentation->video_path, $keepLight ? null : $presentation->light_path]) as $path) {
                if ($this->disk->exists($path)) {
                    $groups['presentations']['files'][] = ['path' => $path, 'bytes' => (int) $this->disk->size($path)];
                }
            }
        }

        foreach (array_keys(ScreenCatalogue::FILES) as $file) {
            $path = ScreenCatalogue::path($file);

            if ($this->disk->exists($path)) {
                $groups['catalogue']['files'][] = ['path' => $path, 'bytes' => (int) $this->disk->size($path)];
            }
        }

        return $groups;
    }

    /**
     * Tout le stockage des vidéos, par dossier : nombre de fichiers et poids.
     *
     * @return array<string, array{files: int, bytes: int}>
     */
    public function usage(): array
    {
        $usage = [];

        foreach (['videos', 'presentations', 'catalogue', 'video-voice-trials', 'voice-samples'] as $directory) {
            $files = $this->filesIn($directory);
            $usage[$directory] = ['files' => count($files), 'bytes' => array_sum(array_column($files, 'bytes'))];
        }

        return $usage;
    }

    /** Coût estimé d'un poids gardé tout un mois, en dollars. */
    public static function monthlyCost(int $bytes): float
    {
        return $bytes / 1024 ** 3 * self::DOLLARS_PER_GB_MONTH;
    }

    /**
     * Efface la liste proposée et met la base à jour (aucune page ne pointe plus vers un fichier absent).
     *
     * @return array{files: int, bytes: int}
     */
    public function delete(): array
    {
        $groups = $this->proposal();
        $files = array_merge(...array_column($groups, 'files'));

        foreach ($files as $file) {
            $this->disk->delete($file['path']);
        }

        foreach (LessonVideo::query()->get() as $video) {
            $old = $video->status === LessonVideo::READY && $video->mail_status === LessonVideo::MAIL_SKIPPED && $video->purged_at === null;
            $failed = $video->status === LessonVideo::FAILED && $video->day->lt(today());

            if ($old || $failed) {
                $this->disk->deleteDirectory($video->folder());
                $video->update(['video_path' => null, 'mail_path' => null, 'audio_path' => null, 'purged_at' => $old ? now() : $video->purged_at]);
            }
        }

        foreach (PresentationVideo::query()->get() as $presentation) {
            $changes = [];

            if ($presentation->video_path !== null && ! $this->disk->exists($presentation->video_path)) {
                $changes['video_path'] = null;
            }

            if ($presentation->light_path !== null && ! $this->disk->exists($presentation->light_path)) {
                $changes['light_path'] = null;
            }

            if ($changes !== []) {
                $presentation->update($changes);
            }
        }

        if ($groups['catalogue']['files'] !== []) {
            Setting::put(ScreenCatalogue::SETTING, null);
        }

        return ['files' => count($files), 'bytes' => array_sum(array_column($files, 'bytes'))];
    }

    /** Phrase à recopier pour effacer (nombre de fichiers de la liste du moment). */
    public function confirmation(): string
    {
        return 'SUPPRIMER '.count(array_merge(...array_column($this->proposal(), 'files'))).' FICHIERS';
    }

    /** @return list<array{path: string, bytes: int}> */
    private function filesIn(string $directory): array
    {
        return array_map(fn (string $path) => ['path' => $path, 'bytes' => (int) $this->disk->size($path)], $this->disk->allFiles($directory));
    }
}
