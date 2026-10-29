<?php

namespace App\Support\Teachers;

use App\Support\Gemini\TeacherVoice;

/**
 * Un professeur (config/teachers.php) : son nom, sa voix chez chaque fournisseur, son personnage. Tout ce qui est
 * pédagogique est commun à tous les professeurs.
 */
final class Teacher
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(public readonly string $key, private readonly array $config) {}

    /** Nom affiché et dit (« Teacher Joe »), lu dans config/avatar.php. */
    public function name(): string
    {
        return (string) config('avatar.'.$this->avatarKey().'.name');
    }

    /**
     * Personnage animé : fichier du modèle 3D, cadrage, hauteur de la voix (bloc de config/avatar.php).
     *
     * @return array<string, mixed>
     */
    public function avatar(): array
    {
        return (array) config('avatar.'.$this->avatarKey());
    }

    /** Voix figée dans le jeton Gemini Live ; sans voix propre, celle de « Essai des voix ». */
    public function geminiVoice(): ?string
    {
        return filled($this->config['gemini_voice'] ?? null) ? (string) $this->config['gemini_voice'] : TeacherVoice::current();
    }

    /** Voix de l'appel OpenAI Realtime (secours des séances en direct). */
    public function realtimeVoice(): string
    {
        return (string) (($this->config['realtime_voice'] ?? null) ?: config('services.openai.realtime_voice'));
    }

    private function avatarKey(): string
    {
        return (string) ($this->config['avatar'] ?? 'model');
    }
}
