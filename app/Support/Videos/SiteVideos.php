<?php

namespace App\Support\Videos;

use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Support\PrivateStorage;
use App\Support\Teachers\Teachers;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Vidéos de la page publique « Comment ça marche » (videos.presentations.site) : version « site » très légère
 * (480 × 854) et image d'aperçu, tirées par GitHub Actions de la version WhatsApp déjà fabriquée
 * (tools/presentation/site-version.mjs), gardées sur le disque privé. Le visiteur ne reçoit l'image d'aperçu
 * qu'en faisant défiler la page, et la vidéo (adresse signée) qu'en touchant « Regarder ».
 */
class SiteVideos
{
    /**
     * Vidéos de la page, dans l'ordre de la configuration. La version d'un autre professeur (« rose-… ») n'y est que
     * tant qu'il est proposé aux apprenants (retour à Teacher Joe seul : la page redevient celle d'avant).
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        $versions = array_keys((array) config('videos.presentations.teacher_versions', []));
        $choosable = Teachers::choosable();

        return array_values(array_filter((array) config('videos.presentations.site.keys', []), function (string $key) use ($versions, $choosable) {
            $prefix = strstr($key, '-', true);

            return $prefix === false || ! in_array($prefix, $versions, true) || isset($choosable[$prefix]);
        }));
    }

    /**
     * Vidéos prêtes pour le site, dans l'ordre de la configuration.
     *
     * @return Collection<int, PresentationVideo>
     */
    public function ready(): Collection
    {
        $keys = self::keys();

        try {
            $rows = PresentationVideo::query()->whereIn('key', $keys)->whereNotNull('web_path')->whereNotNull('poster_path')->get()->keyBy('key');
        } catch (Throwable) {
            return collect();
        }

        return collect($keys)->map(fn (string $key) => $rows[$key] ?? null)->filter()->values();
    }

    public function find(string $key): ?PresentationVideo
    {
        return $this->ready()->firstWhere('key', $key);
    }

    /**
     * Vidéos prêtes (version WhatsApp déposée) dont la version « site » manque.
     *
     * @param  list<string>  $keys  vide : toutes celles de la page
     * @return Collection<int, PresentationVideo>
     */
    public function waiting(array $keys = []): Collection
    {
        $wanted = $keys === [] ? self::keys() : array_values(array_intersect(self::keys(), $keys));

        return PresentationVideo::query()
            ->whereIn('key', $wanted)
            ->where('status', LessonVideo::READY)
            ->whereNotNull('light_path')
            ->where(fn ($query) => $query->whereNull('web_path')->orWhereNull('poster_path'))
            ->orderBy('id')
            ->get();
    }

    /**
     * Ce que GitHub Actions doit savoir pour une vidéo : d'où lire la version WhatsApp, où déposer les deux fichiers.
     *
     * @return array<string, mixed>
     */
    public function job(PresentationVideo $video): array
    {
        $site = config('videos.presentations.site');

        return [
            'id' => $video->id,
            'key' => $video->key,
            'title' => $video->title,
            'source_url' => $this->sourceUrl($video),
            'width' => (int) $site['width'],
            'height' => (int) $site['height'],
            'video_kbps' => (int) $site['video_kbps'],
            'audio_kbps' => (int) $site['audio_kbps'],
            'poster_second' => (float) $site['poster_second'],
            'upload' => [
                'video' => $this->uploadTarget($video, 'video'),
                'poster' => $this->uploadTarget($video, 'apercu'),
            ],
            'done_url' => route('api.presentations.site.done', $video),
        ];
    }

    public static function path(PresentationVideo $video, string $file): string
    {
        $name = str((string) config('platform.name'))->slug().'-presentation-'.$video->key;

        return $video->folder().'/'.($file === 'apercu' ? $name.'-apercu.jpg' : $name.'-site.mp4');
    }

    /** Les deux fichiers sont déposés : vérifiés sur le disque privé, puis montrés sur la page. */
    public function markReady(PresentationVideo $video): bool
    {
        $disk = PrivateStorage::disk();
        $web = self::path($video, 'video');
        $poster = self::path($video, 'apercu');

        if ($video->status !== LessonVideo::READY || ! $disk->exists($web) || ! $disk->exists($poster)) {
            return false;
        }

        $video->update(['web_path' => $web, 'poster_path' => $poster, 'web_size_bytes' => (int) $disk->size($web)]);

        return true;
    }

    /** La vidéo a été refaite : l'ancienne version « site » n'est plus montrée (GitHub Actions en refera une). */
    public static function forget(PresentationVideo $video): void
    {
        $disk = PrivateStorage::disk();

        foreach (array_filter([$video->web_path, $video->poster_path]) as $path) {
            try {
                $disk->delete($path);
            } catch (Throwable) {
            }
        }

        $video->forceFill(['web_path' => null, 'poster_path' => null, 'web_size_bytes' => null]);
    }

    private function sourceUrl(PresentationVideo $video): string
    {
        try {
            return PrivateStorage::disk()->temporaryUrl($video->light_path, now()->addMinutes((int) config('videos.upload_url_minutes')));
        } catch (Throwable) {
            return route('api.presentations.site.source', $video);
        }
    }

    /**
     * @return array{method: string, url: string, headers: array<string, string>, direct: bool}
     */
    private function uploadTarget(PresentationVideo $video, string $file): array
    {
        $type = $file === 'apercu' ? 'image/jpeg' : 'video/mp4';

        try {
            $signed = PrivateStorage::disk()->temporaryUploadUrl(self::path($video, $file), now()->addMinutes((int) config('videos.upload_url_minutes')), ['ContentType' => $type]);
            $headers = array_map(fn ($value) => is_array($value) ? implode(', ', $value) : (string) $value, $signed['headers'] ?? []);

            return ['method' => 'PUT', 'url' => $signed['url'], 'headers' => $headers + ['Content-Type' => $type], 'direct' => true];
        } catch (Throwable) {
            return ['method' => 'PUT', 'url' => route('api.presentations.site.upload', ['presentation' => $video, 'file' => $file]), 'headers' => ['Content-Type' => $type], 'direct' => false];
        }
    }
}
