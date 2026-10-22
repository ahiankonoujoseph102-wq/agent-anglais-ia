<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Vidéo de leçon de Teacher Joe (App\Support\Videos\LessonVideos) : un sujet du programme des vidéos, son script,
 * sa voix (disque privé), puis la vidéo fabriquée par GitHub Actions. Un sujet n'est jamais traité deux fois.
 */
#[Fillable(['topic_key', 'day', 'slot', 'level', 'title', 'status', 'script', 'caption', 'hashtags', 'text_provider', 'voice_provider', 'voice_name', 'timings', 'audio_path', 'video_path', 'duration_seconds', 'size_bytes', 'attempts', 'next_attempt_at', 'error', 'render_started_at', 'ready_at', 'published_at'])]
class LessonVideo extends Model
{
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
            'timings' => 'array',
            'duration_seconds' => 'integer',
            'size_bytes' => 'integer',
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'render_started_at' => 'datetime',
            'ready_at' => 'datetime',
            'published_at' => 'datetime',
        ];
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

    /** Nom du fichier proposé au téléchargement. */
    public function downloadName(): string
    {
        return 'teacher-joe-'.$this->day->format('Y-m-d').'-'.$this->slot.'-'.$this->topic_key.'.mp4';
    }
}
