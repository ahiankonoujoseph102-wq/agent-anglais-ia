<?php

namespace App\Support\Videos;

use App\Models\LessonVideo;
use App\Support\Avatar;
use App\Support\PrivateStorage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Vidéos de leçon : planification (sujets du jour), préparation (script puis voix, côté Laravel), travaux de
 * fabrication donnés à GitHub Actions, réception de la vidéo. Rien ne bloque jamais une page de l'apprenant.
 */
class LessonVideos
{
    public function __construct(private VideoScriptWriter $writer, private VideoVoice $voice) {}

    /**
     * Ajoute jusqu'à « count » vidéos pour ce jour, avec les prochains sujets jamais traités. Renvoie le nombre créé
     * (moins si le programme des sujets est épuisé).
     */
    public function plan(CarbonInterface $day, int $count): int
    {
        return DB::transaction(function () use ($day, $count) {
            $slot = (int) LessonVideo::query()->promo()->whereDate('day', $day)->lockForUpdate()->max('slot');
            $created = 0;

            foreach (VideoTopics::next($count) as $topic) {
                LessonVideo::query()->create([
                    'kind' => LessonVideo::PROMO,
                    'topic_key' => $topic['key'],
                    'day' => $day->toDateString(),
                    'slot' => ++$slot,
                    'level' => $topic['level'],
                    'title' => $topic['title'],
                    'status' => LessonVideo::PLANNED,
                ]);
                $created++;
            }

            return $created;
        });
    }

    /** Vidéos du jour (réglage de la page « Vidéos », sinon config videos.per_day), seulement celles qui manquent. */
    public function planToday(): int
    {
        $missing = VideoSettings::perDay() - LessonVideo::query()->promo()->whereDate('day', today())->count();

        return $missing > 0 ? $this->plan(today(), $missing) : 0;
    }

    /**
     * Écrit les scripts et prépare les voix des vidéos en attente, jusqu'à la fin du temps accordé.
     *
     * @return array{scripted: int, voiced: int, waiting: int}
     */
    public function prepare(int $budget): array
    {
        $deadline = microtime(true) + $budget;
        $result = ['scripted' => 0, 'voiced' => 0, 'waiting' => 0];

        // Sans adresse du site, la fin de la vidéo ne peut pas être écrite ni dite : rien n'est préparé.
        if (VideoSettings::blocked() !== null) {
            $result['waiting'] = LessonVideo::query()->whereIn('status', [LessonVideo::PLANNED, LessonVideo::SCRIPTED])->count();

            return $result;
        }

        $videos = LessonVideo::query()
            ->whereIn('status', [LessonVideo::PLANNED, LessonVideo::SCRIPTED])
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderByRaw("case when kind = 'club' then 0 else 1 end")->orderBy('day')->orderBy('slot')
            ->get();

        foreach ($videos as $video) {
            if (microtime(true) + 20 > $deadline) {
                break;
            }

            try {
                // Vidéo du Club : script écrit pour les abonnés (thème du jour), aucune fin publicitaire.
                $written = $video->isClub() ? fn () => app(ClubVideos::class)->writeScript($video) : fn () => $this->writeScript($video);

                if ($video->status === LessonVideo::PLANNED && ! $written()) {
                    continue;
                }

                if ($video->isClub() && $video->audio_path !== null) {
                    $video->update(['status' => LessonVideo::VOICED, 'attempts' => 0, 'error' => null, 'next_attempt_at' => null]);
                    $result['voiced']++;

                    continue;
                }

                $result['scripted'] += $video->wasChanged('script') ? 1 : 0;

                // Vidéo à refaire (voix déjà là) : seule la fin est mise à jour, même script, même voix.
                if ($video->audio_path !== null) {
                    if ($this->refreshEnding($video, $deadline) === VideoVoice::DONE) {
                        $result['voiced']++;
                    }

                    continue;
                }

                if ($this->voice->prepare($video, $deadline) === VideoVoice::DONE) {
                    $result['voiced']++;
                }
            } catch (Throwable $e) {
                Log::warning('Vidéo de leçon : préparation interrompue.', ['video' => $video->id, 'error' => class_basename($e)]);
                $video->update(['error' => 'Préparation interrompue ('.class_basename($e).').', 'next_attempt_at' => now()->addMinutes((int) config('videos.retry_minutes'))]);
            }
        }

        $result['waiting'] = LessonVideo::query()->whereIn('status', [LessonVideo::PLANNED, LessonVideo::SCRIPTED])->count();

        return $result;
    }

    /** Fin d'une vidéo à refaire : question du jour (écrite seulement si elle manque), puis la voix de la fin. */
    private function refreshEnding(LessonVideo $video, float $deadline): string
    {
        $question = VideoScriptWriter::normalizeQuestion($video->script['question'] ?? null) ?? $this->writer->question($video);

        if ($question === null) {
            return $this->voice->failure($video, 'Question du jour : aucun fournisseur du modèle texte n\'a répondu correctement.');
        }

        return $this->voice->refreshEnding($video, $question, $deadline);
    }

    /**
     * « Refaire la vidéo » : même sujet, même script, même voix ; la fin est remise à jour si besoin, puis la vidéo
     * repart en fabrication. L'ancienne vidéo reste visible jusqu'à l'arrivée de la nouvelle. Aucun sujet consommé.
     */
    public function redo(LessonVideo $video): bool
    {
        if (! in_array($video->status, [LessonVideo::READY, LessonVideo::FAILED], true)) {
            return false;
        }

        // Vidéo du Club : aucune fin à remettre à jour ; elle est réécrite (nouveau script, nouvelle voix) avec les
        // règles du moment. L'ancienne vidéo reste visible jusqu'à l'arrivée de la nouvelle.
        if ($video->isClub()) {
            $video->update(['status' => LessonVideo::PLANNED, 'script' => null, 'audio_path' => null, 'timings' => null, 'attempts' => 0, 'next_attempt_at' => null, 'error' => null, 'render_started_at' => null]);

            return true;
        }

        if ($video->audio_path === null) {
            $this->retry($video);

            return true;
        }

        $video->update(['status' => LessonVideo::SCRIPTED, 'attempts' => 0, 'next_attempt_at' => null, 'error' => null, 'render_started_at' => null]);

        return true;
    }

    /** Toutes les vidéos d'aujourd'hui prêtes ou en échec. */
    public function redoToday(): int
    {
        return LessonVideo::query()->whereDate('day', today())->get()->filter(fn (LessonVideo $video) => $this->redo($video))->count();
    }

    private function writeScript(LessonVideo $video): bool
    {
        $written = $this->writer->write($video);

        if ($written === null) {
            $attempts = $video->attempts + 1;
            $video->update([
                'attempts' => $attempts,
                'error' => 'Script : aucun fournisseur du modèle texte n\'a répondu correctement.',
                'next_attempt_at' => now()->addMinutes((int) config('videos.retry_minutes')),
                'status' => $attempts >= (int) config('videos.max_attempts') ? LessonVideo::FAILED : LessonVideo::PLANNED,
            ]);

            return false;
        }

        $script = $written['script'];
        $video->update([
            'status' => LessonVideo::SCRIPTED,
            'script' => $script,
            'title' => $script['title'],
            'caption' => VideoScriptWriter::publication($script['caption'], $script['hashtags']),
            'hashtags' => implode(' ', $script['hashtags']),
            'text_provider' => $written['provider'],
            'attempts' => 0,
            'error' => null,
            'next_attempt_at' => null,
        ]);

        return true;
    }

    /** Vidéos que GitHub Actions peut prendre maintenant. */
    /** @param  string|null  $kind  « club », « promo », ou null pour toutes */
    public function waiting(?string $kind = null): int
    {
        return VideoSettings::blocked() === null ? $this->claimable($kind)->count() : 0;
    }

    /** @return Builder<LessonVideo> */
    private function claimable(?string $kind = null)
    {
        $stale = now()->subMinutes((int) config('videos.render_timeout_minutes'));

        return LessonVideo::query()->where(fn ($query) => $query->where('status', LessonVideo::VOICED)
            ->orWhere(fn ($query) => $query->where('status', LessonVideo::RENDERING)->where('render_started_at', '<', $stale)))
            ->when(in_array($kind, [LessonVideo::CLUB, LessonVideo::PROMO], true), fn ($query) => $query->where('kind', $kind));
    }

    /**
     * Donne à GitHub Actions les vidéos à fabriquer (voix prête, ou fabrication abandonnée depuis longtemps) et les
     * marque « en fabrication ».
     *
     * @return list<array<string, mixed>>
     */
    public function claim(int $limit, ?string $kind = null): array
    {
        if (VideoSettings::blocked() !== null) {
            return [];
        }

        // Fin dépassée (adresse du site changée, ancienne fin) : la vidéo repart d'abord en préparation.
        foreach ($this->claimable()->promo()->get() as $video) {
            if (! VideoEnding::isCurrent($video->script ?? [])) {
                $video->update(['status' => LessonVideo::SCRIPTED, 'render_started_at' => null]);
            }
        }

        $videos = DB::transaction(function () use ($limit, $kind) {
            // La vidéo du Club d'abord : elle est attendue dans le groupe chaque jour.
            $videos = $this->claimable($kind)
                ->orderByRaw("case when kind = 'club' then 0 else 1 end")->orderBy('day')->orderBy('slot')
                ->limit(max(1, min(6, $limit)))
                ->lockForUpdate()
                ->get();

            foreach ($videos as $video) {
                $video->update(['status' => LessonVideo::RENDERING, 'render_started_at' => now(), 'error' => null]);
            }

            return $videos;
        });

        return $videos->map(fn (LessonVideo $video) => $this->job($video))->all();
    }

    /**
     * Ce que GitHub Actions doit savoir pour fabriquer une vidéo (aucune clé, aucune donnée d'apprenant).
     *
     * @return array<string, mixed>
     */
    public function job(LessonVideo $video): array
    {
        $segments = [];

        foreach ($video->script['segments'] ?? [] as $index => $segment) {
            $timing = $video->timings['segments'][$index] ?? ['start' => 0, 'end' => 0];
            $segments[] = ['subtitle' => $segment['subtitle'], 'kind' => $segment['kind'] ?? null, 'closing' => (bool) ($segment['closing'] ?? false), 'start' => $timing['start'], 'end' => $timing['end']];
        }

        return [
            'id' => $video->id,
            'title' => $video->title,
            'level' => $video->level,
            'duration' => (float) ($video->timings['duration'] ?? 0),
            'segments' => $segments,
            'platform' => (string) config('platform.name'),
            'site' => VideoSettings::site(),
            'audio_url' => route('api.videos.audio', $video),
            'upload' => $this->uploadTarget($video),
            'done_url' => route('api.videos.done', $video),
            'failed_url' => route('api.videos.failed', $video),
            'release_url' => route('api.videos.release', $video),
            // Club : fabrication plus légère (config videos.club.render).
            'render' => ['watermark' => VideoSettings::watermark()] + ($video->isClub() ? array_replace(config('videos.render'), (array) config('videos.club.render')) : config('videos.render')),
            'avatar' => array_intersect_key(Avatar::clientConfig()['model'] ?? [], array_flip(['profile', 'camera', 'desk_height', 'resting_smile', 'voice_hz'])),
        ];
    }

    /** Chemin de la vidéo sur le disque privé. */
    public static function videoPath(LessonVideo $video): string
    {
        return $video->folder().'/'.$video->downloadName();
    }

    /**
     * Où déposer la vidéo : adresse signée du bucket (aucune limite de taille de Laravel Cloud), sinon l'API.
     *
     * @return array{method: string, url: string, headers: array<string, string>, direct: bool}
     */
    private function uploadTarget(LessonVideo $video): array
    {
        try {
            $signed = PrivateStorage::disk()->temporaryUploadUrl(self::videoPath($video), now()->addMinutes((int) config('videos.upload_url_minutes')), ['ContentType' => 'video/mp4']);
            $headers = array_map(fn ($value) => is_array($value) ? implode(', ', $value) : (string) $value, $signed['headers'] ?? []);

            return ['method' => 'PUT', 'url' => $signed['url'], 'headers' => $headers + ['Content-Type' => 'video/mp4'], 'direct' => true];
        } catch (Throwable) {
            return ['method' => 'PUT', 'url' => route('api.videos.upload', $video), 'headers' => ['Content-Type' => 'video/mp4'], 'direct' => false];
        }
    }

    /**
     * Vidéo déposée : vérifiée sur le disque privé puis marquée « prête ».
     */
    /**
     * @param  array{lips_correlation?: float|null, lips_open?: float|null, loudness_lufs?: float|null, true_peak_db?: float|null}  $measures
     */
    public function markReady(LessonVideo $video, ?float $duration = null, array $measures = []): bool
    {
        $disk = PrivateStorage::disk();
        $path = self::videoPath($video);

        if (! $disk->exists($path)) {
            return false;
        }

        $video->update([
            'status' => LessonVideo::READY,
            'video_path' => $path,
            'size_bytes' => (int) $disk->size($path),
            'duration_seconds' => $duration !== null ? (int) ceil($duration) : $video->duration_seconds,
            'ready_at' => now(),
            'error' => null,
            'attempts' => 0,
            'render_started_at' => null,
        ] + array_intersect_key($measures, array_flip(['lips_correlation', 'lips_open', 'loudness_lufs', 'true_peak_db'])));

        return true;
    }

    /** Fabrication ratée : la vidéo repart au prochain passage de GitHub, ou passe « en échec » après trop d'essais. */
    public function markRenderFailed(LessonVideo $video, string $error): void
    {
        $attempts = $video->attempts + 1;
        $video->update([
            'status' => $attempts >= (int) config('videos.max_attempts') ? LessonVideo::FAILED : LessonVideo::VOICED,
            'attempts' => $attempts,
            'error' => mb_substr('Fabrication : '.$error, 0, 300),
            'render_started_at' => null,
        ]);
    }

    /**
     * Fabrication arrêtée par GitHub parce que la machine était trop lente pour finir à temps : la vidéo redevient
     * « voix prête », sans échec compté, et sera reprise au passage suivant.
     */
    public function release(LessonVideo $video): void
    {
        $video->update([
            'status' => LessonVideo::VOICED,
            'error' => 'Fabrication reportée : la machine de GitHub était trop lente, nouvel essai au prochain passage.',
            'render_started_at' => null,
        ]);
    }

    /** « Réessayer » de l'administration : la vidéo reprend là où elle s'était arrêtée. */
    public function retry(LessonVideo $video): void
    {
        $status = match (true) {
            $video->script === null => LessonVideo::PLANNED,
            $video->audio_path === null => LessonVideo::SCRIPTED,
            default => LessonVideo::VOICED,
        };

        $video->update(['status' => $status, 'attempts' => 0, 'next_attempt_at' => null, 'error' => null, 'render_started_at' => null]);
    }
}
