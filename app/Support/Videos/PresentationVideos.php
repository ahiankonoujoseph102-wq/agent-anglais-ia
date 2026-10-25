<?php

namespace App\Support\Videos;

use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Support\Avatar;
use App\Support\PrivateStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Vidéos de présentation du site : demande de fabrication (administration), voix préparée par Laravel (VideoVoice,
 * même fournisseur et même voix que les vidéos de leçon), travaux donnés à GitHub Actions (tools/presentation),
 * réception des deux fichiers (TikTok et WhatsApp). Le script vient de resources/videos/presentations.php.
 */
class PresentationVideos
{
    public function __construct(private VideoVoice $voice) {}

    /**
     * Une ligne par vidéo du fichier des scripts, dans son ordre (créée au besoin, « pas encore préparée »).
     *
     * @return Collection<int, PresentationVideo>
     */
    public function all(): Collection
    {
        $rows = PresentationVideo::query()->get()->keyBy('key');

        return collect(PresentationScripts::keys())->map(function (string $key) use ($rows) {
            return $rows[$key] ?? PresentationVideo::query()->create([
                'key' => $key,
                'title' => PresentationScripts::script($key, 'talkami.example')['title'] ?? $key,
                'status' => LessonVideo::PLANNED,
            ]);
        })->values();
    }

    /**
     * « Préparer et fabriquer » : script du fichier (repères remplis), voix à préparer si le texte a changé ou si elle
     * manque, sinon directement prête pour GitHub. Renvoie le nombre de vidéos demandées (0 sans adresse du site).
     */
    public function request(?string $key = null): int
    {
        $count = 0;

        foreach ($this->all() as $video) {
            if ($key !== null && $video->key !== $key) {
                continue;
            }

            if (in_array($video->status, [LessonVideo::RENDERING], true)) {
                continue;
            }

            $script = PresentationScripts::script($video->key);

            if ($script === null) {
                return 0;
            }

            $hash = PresentationScripts::hash($script);
            $disk = PrivateStorage::disk();
            $voiceReady = $video->audio_path !== null && $video->script_hash === $hash && $disk->exists($video->audio_path);

            if (! $voiceReady && $video->audio_path !== null) {
                // Texte changé : l'ancienne voix n'est plus jamais utilisée.
                $disk->delete($video->audio_path);
                $disk->deleteDirectory($video->folder().'/voice');
            }

            $video->update([
                'title' => $script['title'],
                'caption' => VideoScriptWriter::publication($script['caption'], $script['hashtags']),
                'script' => $script,
                'script_hash' => $hash,
                'status' => $voiceReady ? LessonVideo::VOICED : LessonVideo::SCRIPTED,
                'audio_path' => $voiceReady ? $video->audio_path : null,
                'timings' => $voiceReady ? $video->timings : null,
                'voice_provider' => $voiceReady ? $video->voice_provider : null,
                'voice_name' => $voiceReady ? $video->voice_name : null,
                'attempts' => 0,
                'next_attempt_at' => null,
                'error' => null,
                'render_started_at' => null,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Prépare les voix demandées, jusqu'à l'heure limite (microtime).
     *
     * @return array{voiced: int, waiting: int}
     */
    public function prepare(float $deadline): array
    {
        $voiced = 0;
        $videos = PresentationVideo::query()
            ->where('status', LessonVideo::SCRIPTED)
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')
            ->get();

        foreach ($videos as $video) {
            if (microtime(true) + 20 > $deadline) {
                break;
            }

            try {
                $voiced += $this->voice->prepare($video, $deadline) === VideoVoice::DONE ? 1 : 0;
            } catch (Throwable $e) {
                Log::warning('Vidéo de présentation : préparation interrompue.', ['video' => $video->key, 'error' => class_basename($e)]);
                $video->update(['error' => 'Préparation interrompue ('.class_basename($e).').', 'next_attempt_at' => now()->addMinutes((int) config('videos.retry_minutes'))]);
            }
        }

        return ['voiced' => $voiced, 'waiting' => PresentationVideo::query()->where('status', LessonVideo::SCRIPTED)->count()];
    }

    /**
     * Vidéos que GitHub Actions peut prendre (voix prête, ou fabrication abandonnée depuis longtemps).
     *
     * @param  list<string>  $keys  vide : toutes
     * @return Builder<PresentationVideo>
     */
    private function claimable(array $keys)
    {
        $stale = now()->subMinutes((int) config('videos.render_timeout_minutes') * 2);

        return PresentationVideo::query()
            ->when($keys !== [], fn ($query) => $query->whereIn('key', $keys))
            ->where(fn ($query) => $query->where('status', LessonVideo::VOICED)
                ->orWhere(fn ($query) => $query->where('status', LessonVideo::RENDERING)->where('render_started_at', '<', $stale)));
    }

    /** @param  list<string>  $keys */
    public function waiting(array $keys = []): int
    {
        return $this->claimable($keys)->count();
    }

    /**
     * Donne à GitHub Actions les vidéos à fabriquer et les marque « en fabrication ».
     *
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    public function claim(array $keys = []): array
    {
        $videos = DB::transaction(function () use ($keys) {
            $videos = $this->claimable($keys)->orderBy('id')->lockForUpdate()->get();

            foreach ($videos as $video) {
                $video->update(['status' => LessonVideo::RENDERING, 'render_started_at' => now(), 'error' => null]);
            }

            return $videos;
        });

        return $videos->map(fn (PresentationVideo $video) => $this->job($video))->values()->all();
    }

    /**
     * Ce que GitHub Actions doit savoir (aucune clé, aucune donnée d'apprenant) : phrases avec leur heure, plans,
     * réglages de mise en page, adresses de dépôt des deux fichiers.
     *
     * @return array<string, mixed>
     */
    public function job(PresentationVideo $video): array
    {
        $segments = [];

        foreach ($video->script['segments'] ?? [] as $index => $segment) {
            $timing = $video->timings['segments'][$index] ?? ['start' => 0, 'end' => 0];
            $segments[] = array_intersect_key($segment, array_flip(['subtitle', 'shot', 'scene', 'chapter', 'medallion', 'closing', 'card', 'sfx'])) + ['start' => $timing['start'], 'end' => $timing['end']];
        }

        return [
            'id' => $video->id,
            'key' => $video->key,
            'title' => $video->title,
            'duration' => (float) ($video->timings['duration'] ?? 0),
            'segments' => $segments,
            'platform' => (string) config('platform.name'),
            'site' => VideoSettings::site(),
            'light_limit_bytes' => $video->lightLimitBytes(),
            'audio_url' => route('api.presentations.audio', $video),
            'upload' => ['video' => $this->uploadTarget($video, false), 'light' => $this->uploadTarget($video, true)],
            'done_url' => route('api.presentations.done', $video),
            'failed_url' => route('api.presentations.failed', $video),
            // Teacher Joe : mêmes réglages que les vidéos de leçon (salle de classe, caméra, lèvres), à 30 images par seconde.
            'render' => self::render($segments, $video->script['layout'] ?? null),
            'avatar' => array_intersect_key(Avatar::clientConfig()['model'] ?? [], array_flip(['profile', 'camera', 'desk_height', 'resting_smile', 'voice_hz'])),
        ];
    }

    /**
     * Réglages de l'image. Avec un bandeau en gros (« card »), le tableau du décor reste vide : son « Let's talk! »
     * dépasserait sous le bandeau. Publicité (« layout » « ad ») : sous-titres, bandeau et médaillon déplacés hors
     * des boutons de Meta et de TikTok (videos.presentations.ad), seulement pour ces vidéos.
     *
     * @param  list<array<string, mixed>>  $segments
     * @return array<string, mixed>
     */
    private static function render(array $segments, ?string $layout = null): array
    {
        $render = array_merge(config('videos.render'), config('videos.presentations.render'), ['watermark' => null]);

        if ($layout === 'ad') {
            $render = array_replace_recursive($render, config('videos.presentations.ad'), ['layout' => 'ad']);
        }

        if (collect($segments)->contains(fn (array $segment) => ($segment['card'] ?? []) !== [])) {
            $render['board'] = ['lines' => [' ']] + $render['board'];
        }

        return $render;
    }

    public static function path(PresentationVideo $video, bool $light): string
    {
        return $video->folder().'/'.$video->downloadName($light);
    }

    /**
     * @return array{method: string, url: string, headers: array<string, string>, direct: bool}
     */
    private function uploadTarget(PresentationVideo $video, bool $light): array
    {
        try {
            $signed = PrivateStorage::disk()->temporaryUploadUrl(self::path($video, $light), now()->addMinutes((int) config('videos.upload_url_minutes') * 3), ['ContentType' => 'video/mp4']);
            $headers = array_map(fn ($value) => is_array($value) ? implode(', ', $value) : (string) $value, $signed['headers'] ?? []);

            return ['method' => 'PUT', 'url' => $signed['url'], 'headers' => $headers + ['Content-Type' => 'video/mp4'], 'direct' => true];
        } catch (Throwable) {
            return ['method' => 'PUT', 'url' => route('api.presentations.upload', ['presentation' => $video, 'version' => $light ? 'whatsapp' : 'tiktok']), 'headers' => ['Content-Type' => 'video/mp4'], 'direct' => false];
        }
    }

    /**
     * Les deux fichiers sont déposés : vérifiés sur le disque privé, puis la vidéo est « prête ».
     *
     * @param  array<string, float|null>  $measures
     */
    public function markReady(PresentationVideo $video, ?float $duration, array $measures = []): bool
    {
        $disk = PrivateStorage::disk();
        $full = self::path($video, false);
        $light = self::path($video, true);

        if (! $disk->exists($full) || ! $disk->exists($light)) {
            return false;
        }

        $video->update([
            'status' => LessonVideo::READY,
            'video_path' => $full,
            'light_path' => $light,
            'size_bytes' => (int) $disk->size($full),
            'light_size_bytes' => (int) $disk->size($light),
            'duration_seconds' => $duration !== null ? (int) ceil($duration) : $video->duration_seconds,
            'ready_at' => now(),
            'error' => null,
            'attempts' => 0,
            'render_started_at' => null,
        ] + array_intersect_key($measures, array_flip(['lips_correlation', 'lips_open', 'loudness_lufs', 'true_peak_db'])));

        return true;
    }

    public function markRenderFailed(PresentationVideo $video, string $error): void
    {
        $attempts = $video->attempts + 1;
        $video->update([
            'status' => $attempts >= (int) config('videos.max_attempts') ? LessonVideo::FAILED : LessonVideo::VOICED,
            'attempts' => $attempts,
            'error' => mb_substr('Fabrication : '.$error, 0, 300),
            'render_started_at' => null,
        ]);
    }
}
