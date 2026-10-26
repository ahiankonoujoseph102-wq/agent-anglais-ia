<?php

namespace App\Support\Videos;

use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Models\Setting;
use App\Support\Avatar;
use App\Support\PrivateStorage;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Catalogue des écrans, pour un montage (videos.catalogue) : UNE vidéo muette de tous les écrans du site de
 * DÉMONSTRATION et la voix de Teacher Joe à part, fabriquées par GitHub Actions (tools/presentation/catalogue.mjs),
 * déposées sur le disque privé, téléchargées depuis la page « Vidéos » de l'administration. Le dernier catalogue
 * (date, durée, poids, liste des passages) est gardé dans la table « settings ».
 */
class ScreenCatalogue
{
    public const SETTING = 'videos.catalogue.last';

    /** Fichiers : la vidéo et la voix de Teacher Joe. */
    public const FILES = [
        'video' => ['name' => 'catalogue-des-ecrans.mp4', 'type' => 'video/mp4'],
        'voix' => ['name' => 'catalogue-voix-de-teacher-joe.wav', 'type' => 'audio/wav'],
    ];

    public static function path(string $file): string
    {
        return 'catalogue/'.str((string) config('platform.name'))->slug().'-'.self::FILES[$file]['name'];
    }

    /**
     * Voix de Teacher Joe pour le passage où il parle : vidéo de présentation déjà prête (voix gardée), ses phrases et
     * leurs heures. Null tant qu'elle n'est pas prête.
     *
     * @return array<string, mixed>|null
     */
    public function voice(): ?array
    {
        $video = PresentationVideo::query()->where('key', config('videos.catalogue.voice_key'))->first();

        if ($video === null || $video->status !== LessonVideo::READY || $video->audio_path === null || ! PrivateStorage::disk()->exists($video->audio_path)) {
            return null;
        }

        $segments = [];

        foreach ($video->script['segments'] ?? [] as $index => $segment) {
            $timing = $video->timings['segments'][$index] ?? null;

            if ($timing !== null) {
                $segments[] = ['shot' => $segment['shot'] ?? 'full', 'start' => (float) $timing['start'], 'end' => (float) $timing['end']];
            }
        }

        return ['key' => $video->key, 'audio_url' => route('api.presentations.audio', $video), 'segments' => $segments, 'max_seconds' => (float) config('videos.catalogue.voice_max_seconds')];
    }

    /**
     * Réglages de l'image de Teacher Joe (les mêmes que les vidéos de présentation : salle de classe, caméra, lèvres).
     *
     * @return array<string, mixed>
     */
    public static function render(): array
    {
        return [
            'render' => PresentationVideos::render([]),
            'avatar' => array_intersect_key(Avatar::clientConfig()['model'] ?? [], array_flip(['profile', 'camera', 'desk_height', 'resting_smile', 'voice_hz'])),
        ];
    }

    /**
     * Où GitHub Actions dépose les deux fichiers : adresse signée du bucket, sinon le site lui-même.
     *
     * @return array<string, array{method: string, url: string, headers: array<string, string>, direct: bool}>
     */
    public function uploadTargets(): array
    {
        $targets = [];

        foreach (self::FILES as $file => $meta) {
            try {
                $signed = PrivateStorage::disk()->temporaryUploadUrl(self::path($file), now()->addMinutes((int) config('videos.upload_url_minutes') * 3), ['ContentType' => $meta['type']]);
                $headers = array_map(fn ($value) => is_array($value) ? implode(', ', $value) : (string) $value, $signed['headers'] ?? []);
                $targets[$file] = ['method' => 'PUT', 'url' => $signed['url'], 'headers' => $headers + ['Content-Type' => $meta['type']], 'direct' => true];
            } catch (Throwable) {
                $targets[$file] = ['method' => 'PUT', 'url' => route('api.catalogue.upload', ['file' => $file]), 'headers' => ['Content-Type' => $meta['type']], 'direct' => false];
            }
        }

        return $targets;
    }

    /**
     * Les deux fichiers sont là : le catalogue est enregistré (date, durée, poids, passages).
     *
     * @param  list<array<string, mixed>>  $passages
     */
    public function markReady(float $duration, array $passages, ?int $voiceStart): bool
    {
        $disk = PrivateStorage::disk();

        foreach (array_keys(self::FILES) as $file) {
            if (! $disk->exists(self::path($file))) {
                return false;
            }
        }

        Setting::put(self::SETTING, (string) json_encode([
            'made_at' => now()->toIso8601String(),
            'duration' => round($duration, 1),
            'size_bytes' => (int) $disk->size(self::path('video')),
            'voice_start' => $voiceStart,
            'passages' => array_values(array_map(fn (array $p) => [
                'number' => (int) ($p['number'] ?? 0),
                'key' => (string) ($p['key'] ?? ''),
                'title' => (string) ($p['title'] ?? ''),
                'start' => (float) ($p['start'] ?? 0),
                'end' => (float) ($p['end'] ?? 0),
                'moments' => array_values(array_map(fn ($m) => [(float) ($m[0] ?? 0), (string) ($m[1] ?? '')], (array) ($p['moments'] ?? []))),
            ], $passages)),
        ], JSON_UNESCAPED_UNICODE));

        return true;
    }

    /** @return array<string, mixed>|null */
    public function last(): ?array
    {
        $value = json_decode((string) Setting::get(self::SETTING), true);

        return is_array($value) ? $value : null;
    }

    /** Téléchargement : adresse signée de courte durée, sinon le fichier envoyé par le site. */
    public function download(string $file): Response|RedirectResponse
    {
        $disk = PrivateStorage::disk();
        $path = self::path($file);
        abort_unless($disk->exists($path), 404);
        $name = basename($path);

        try {
            return redirect()->away($disk->temporaryUrl($path, now()->addMinutes((int) config('videos.catalogue.download_url_minutes')), [
                'ResponseContentType' => self::FILES[$file]['type'],
                'ResponseContentDisposition' => 'attachment; filename="'.$name.'"',
            ]));
        } catch (Throwable) {
            return $disk->download($path, $name, ['Content-Type' => self::FILES[$file]['type'], 'Cache-Control' => 'private, no-store']);
        }
    }

    /** « 1:05 » : minutes et secondes, pour l'index du monteur. */
    public static function clock(float $seconds): string
    {
        $total = (int) round($seconds);

        return intdiv($total, 60).':'.str_pad((string) ($total % 60), 2, '0', STR_PAD_LEFT);
    }
}
