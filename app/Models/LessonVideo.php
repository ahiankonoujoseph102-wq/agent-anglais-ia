<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Vidéo de leçon de Teacher Joe (App\Support\Videos\LessonVideos) : un sujet du programme des vidéos, son script,
 * sa voix (disque privé), puis la vidéo fabriquée par GitHub Actions. Un sujet n'est jamais traité deux fois.
 */
#[Fillable(['kind', 'theme', 'items', 'topic_key', 'day', 'slot', 'level', 'title', 'status', 'script', 'caption', 'hashtags', 'text_provider', 'voice_provider', 'voice_name', 'timings', 'audio_path', 'video_path', 'duration_seconds', 'size_bytes', 'lips_correlation', 'lips_open', 'loudness_lufs', 'true_peak_db', 'attempts', 'next_attempt_at', 'error', 'render_started_at', 'ready_at', 'published_at'])]
class LessonVideo extends Model
{
    /** Vidéo de promotion (TikTok, chaîne WhatsApp). */
    public const PROMO = 'promo';

    /** Vidéo du jour du Club des abonnés (groupe WhatsApp des abonnés payants). */
    public const CLUB = 'club';

    /** Sujet choisi, script à écrire. */
    public const PLANNED = 'planned';

    /** Script écrit, voix à préparer. */
    public const SCRIPTED = 'scripted';

    /** Voix prête : la vidéo attend GitHub Actions. */
    public const VOICED = 'voiced';

    /** Prise par GitHub Actions. */
    public const RENDERING = 'rendering';

    public const READY = 'ready';

    public const FAILED = 'failed';

    public const LABELS = [
        self::PLANNED => 'Script à écrire',
        self::SCRIPTED => 'Voix à préparer',
        self::VOICED => 'Voix prête : en attente de fabrication',
        self::RENDERING => 'En fabrication',
        self::READY => 'Prête',
        self::FAILED => 'En échec',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'date',
            'slot' => 'integer',
            'script' => 'array',
            'items' => 'array',
            'timings' => 'array',
            'duration_seconds' => 'integer',
            'size_bytes' => 'integer',
            'lips_correlation' => 'float',
            'lips_open' => 'float',
            'loudness_lufs' => 'float',
            'true_peak_db' => 'float',
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'render_started_at' => 'datetime',
            'ready_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeClub($query): void
    {
        $query->where('kind', self::CLUB);
    }

    /** @param Builder<self> $query */
    public function scopePromo($query): void
    {
        $query->where('kind', self::PROMO);
    }

    public function isClub(): bool
    {
        // Lu sans passer par l'attribut : les vidéos de présentation (même modèle, autre table) n'ont pas de « kind ».
        return ($this->getAttributes()['kind'] ?? null) === self::CLUB;
    }

    /** Thème du jour d'une vidéo du Club (« Le son difficile »…), repli du lundi compris. */
    public function themeLabel(): ?string
    {
        if ($this->theme === 'pitfall') {
            return (string) config('videos.club.pitfall_label');
        }

        return collect(config('videos.club.themes'))->firstWhere('key', $this->theme)['label'] ?? null;
    }

    public function statusLabel(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    /** Dossier de la vidéo sur le disque privé. */
    public function folder(): string
    {
        return 'videos/'.$this->day->format('Y-m-d').'/'.$this->id;
    }

    /** Ton demandé à la synthèse vocale (VideoVoice). */
    public function voiceStyle(): string
    {
        return (string) config('videos.voice.style');
    }

    /** Silence entre deux parties de la voix, en millisecondes. */
    public function voiceGapMs(): int
    {
        return (int) config('videos.voice.gap_ms');
    }

    /** Libellé des synthèses (page « Consommation »). */
    public function voiceLabel(): string
    {
        return $this->isClub() ? 'Vidéo du Club des abonnés' : 'Vidéo de leçon';
    }

    /** Nom du fichier proposé au téléchargement. */
    public function downloadName(): string
    {
        if ($this->isClub()) {
            return 'talkami-club-'.$this->day->format('Y-m-d').'.mp4';
        }

        return 'teacher-joe-'.$this->day->format('Y-m-d').'-'.$this->slot.'-'.$this->topic_key.'.mp4';
    }
}
