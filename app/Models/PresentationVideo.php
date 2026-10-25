<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * Vidéo de présentation du site (App\Support\Videos\PresentationVideos) : script de resources/videos/presentations.php,
 * voix préparée par Laravel (même code que les vidéos de leçon : App\Support\Videos\VideoVoice), puis deux fichiers
 * fabriqués par GitHub Actions : la vidéo pour TikTok (1080 × 1920) et une version légère pour WhatsApp.
 */
#[Fillable(['key', 'title', 'status', 'script', 'script_hash', 'caption', 'voice_provider', 'voice_name', 'timings', 'audio_path', 'video_path', 'light_path', 'duration_seconds', 'size_bytes', 'light_size_bytes', 'lips_correlation', 'lips_open', 'loudness_lufs', 'true_peak_db', 'attempts', 'next_attempt_at', 'error', 'render_started_at', 'ready_at'])]
class PresentationVideo extends LessonVideo
{
    protected $table = 'presentation_videos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'script' => 'array',
            'timings' => 'array',
            'duration_seconds' => 'integer',
            'size_bytes' => 'integer',
            'light_size_bytes' => 'integer',
            'lips_correlation' => 'float',
            'lips_open' => 'float',
            'loudness_lufs' => 'float',
            'true_peak_db' => 'float',
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'render_started_at' => 'datetime',
            'ready_at' => 'datetime',
        ];
    }

    /** Mêmes états que les vidéos de leçon, sauf le premier : rien n'est écrit, le script vient du fichier. */
    public function statusLabel(): string
    {
        return $this->status === self::PLANNED ? 'Pas encore préparée' : parent::statusLabel();
    }

    public function folder(): string
    {
        return 'presentations/'.$this->key;
    }

    /** Nom du fichier proposé au téléchargement : « talkami-presentation-paiement.mp4 » ou « …-whatsapp.mp4 ». */
    public function downloadName(bool $light = false): string
    {
        return str((string) config('platform.name'))->slug().'-presentation-'.$this->key.($light ? '-whatsapp' : '').'.mp4';
    }

    /** Ton de la vidéo (« voice_style » de son script), sinon celui des présentations. */
    public function voiceStyle(): string
    {
        return (string) ($this->script['voice_style'] ?? null ?: config('videos.presentations.voice_style'));
    }

    public function voiceGapMs(): int
    {
        return (int) config('videos.presentations.gap_ms');
    }

    public function voiceLabel(): string
    {
        return 'Vidéo de présentation';
    }

    /** La grande vidéo (limite de la version légère « long ») ? */
    public function isLong(): bool
    {
        return (int) ($this->timings['duration'] ?? $this->duration_seconds ?? 0) > (int) config('videos.presentations.long_after_seconds');
    }

    /** Taille au plus de la version légère (WhatsApp), en octets. */
    public function lightLimitBytes(): int
    {
        return (int) config('videos.presentations.light_megabytes.'.($this->isLong() ? 'long' : 'short')) * 1024 * 1024;
    }
}
