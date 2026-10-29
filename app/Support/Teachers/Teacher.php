<?php

namespace App\Support\Teachers;

use App\Models\Setting;
use App\Support\Avatar;

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

    /** Professeur animé en 3D disponible (sinon : visage simple). */
    public function has3d(): bool
    {
        return Avatar::enabled($this);
    }

    /** Visage simple (SVG) de l'écran de séance. */
    public function face(): string
    {
        return (string) ($this->config['face'] ?? 'joe');
    }

    /** Portrait fixe (pages publiques, « Mon espace ») : chemin dans public/. */
    public function portrait(): string
    {
        return (string) ($this->config['portrait'] ?? 'images/teacher-joe.webp');
    }

    /**
     * Remplacements dans les scripts des vidéos de présentation, quand ce professeur les dit (accords au féminin).
     *
     * @return array<string, string>
     */
    public function presentationTexts(): array
    {
        return (array) ($this->config['presentation_texts'] ?? []);
    }

    /** Une phrase qui présente le professeur sur les pages publiques (jamais une promesse). */
    public function publicLine(): string
    {
        return (string) ($this->config['public_line'] ?? '');
    }

    /** Professeur par défaut (Teacher Joe) : ses messages vocaux gardent exactement les réglages d'avant. */
    public function isDefault(): bool
    {
        return $this->key === (string) config('teachers.default');
    }

    /**
     * Ton d'un message vocal pour ce professeur : le ton commun (écrit pour Teacher Joe), adapté par
     * « speech_style » (remplacements, par exemple « male » → « female »). Teacher Joe : inchangé.
     */
    public function speechStyle(string $style): string
    {
        $style = strtr($style, (array) ($this->config['speech_style'] ?? []));
        $extra = trim((string) ($this->config['speech_extra'] ?? ''));

        // Consigne de prononciation ajoutée seulement avec la voix des vidéos et des messages choisie (test en
        // français et en anglais) : avant ce choix, aucun fichier n'est refait.
        return $extra !== '' && $this->speechVoice() !== null ? trim($style.' '.$extra) : $style;
    }

    /**
     * Voix proposées pour les vidéos et les messages (test en français et en anglais) : fournisseur => voix =>
     * description. Vide (Teacher Joe) : aucun choix à part, réglages d'avant.
     *
     * @return array<string, array<string, string>>
     */
    public function speechTrials(): array
    {
        return array_filter((array) ($this->config['speech_trials'] ?? []), fn ($voices) => is_array($voices) && $voices !== []);
    }

    /**
     * Voix des vidéos et des messages choisie dans « Essai des voix » (fournisseur et voix), ou null : sa voix de
     * séance, comme avant. Une voix qui n'est plus proposée est ignorée.
     *
     * @return array{provider: string, voice: string}|null
     */
    public function speechVoice(): ?array
    {
        $chosen = $this->chosen($this->config['speech_setting'] ?? null);

        if (! filled($chosen) || ! str_contains($chosen, ':')) {
            return null;
        }

        [$provider, $voice] = explode(':', $chosen, 2);

        return isset($this->speechTrials()[$provider][$voice]) ? ['provider' => $provider, 'voice' => $voice] : null;
    }

    /** Voix de synthèse choisie pour ce fournisseur, ou null (autre fournisseur, ou rien de choisi). */
    public function speechVoiceFor(string $provider): ?string
    {
        $chosen = $this->speechVoice();

        return $chosen !== null && $chosen['provider'] === $provider ? $chosen['voice'] : null;
    }

    public function chooseSpeechVoice(string $provider, string $voice): void
    {
        $setting = $this->config['speech_setting'] ?? null;

        if (filled($setting) && isset($this->speechTrials()[$provider][$voice])) {
            Setting::put((string) $setting, $provider.':'.$voice);
        }
    }

    /**
     * Paragraphes du message de bienvenue propres à ce professeur (accords au féminin…), par nom de paragraphe.
     *
     * @return array<string, string>
     */
    public function welcomeParagraphs(): array
    {
        return (array) ($this->config['welcome_paragraphs'] ?? []);
    }

    /** Voix OpenAI des messages vocaux (synthèse vocale) : Teacher Joe : services.openai.tts_voice ; sinon sa voix d'appel. */
    public function openaiSpeechVoice(): string
    {
        return $this->isDefault() ? (string) config('services.openai.tts_voice') : $this->realtimeVoice();
    }

    /** Le choix de ce professeur est montré aux apprenants (voix et visage prêts). */
    public function isReady(): bool
    {
        return (bool) ($this->config['ready'] ?? false);
    }

    /** Ce que le professeur dit être quand on le lui demande (« un professeur virtuel »). */
    public function virtualTeacher(): string
    {
        return (string) ($this->config['virtual_teacher'] ?? 'un professeur virtuel');
    }

    /** « il » ou « elle » (textes de l'écran de séance). */
    public function pronoun(): string
    {
        return (string) ($this->config['pronoun'] ?? 'il');
    }

    /** « le professeur » ou « la professeure ». */
    public function theTeacher(): string
    {
        return (string) ($this->config['the_teacher'] ?? 'le professeur');
    }

    /** Court bloc de personnalité propre au professeur (vide : rien n'est ajouté aux consignes). */
    public function personality(): string
    {
        return trim((string) ($this->config['personality'] ?? ''));
    }

    /** Ton des extraits de « Essai des voix ». */
    public function sampleStyle(): string
    {
        return (string) ($this->config['sample_style'] ?? config('teacher.voice_trial.style'));
    }

    /**
     * Voix Gemini proposées dans « Essai des voix » : nom => description officielle.
     *
     * @return array<string, string>
     */
    public function geminiTrials(): array
    {
        return (array) ($this->config['gemini_trials'] ?? config('services.gemini.voice_trials', []));
    }

    /**
     * Voix OpenAI proposées dans « Essai des voix » : nom => description (vide : aucun choix).
     *
     * @return array<string, string>
     */
    public function openaiTrials(): array
    {
        return filled($this->config['realtime_setting'] ?? null) ? (array) ($this->config['openai_trials'] ?? []) : [];
    }

    public function defaultGeminiVoice(): string
    {
        return (string) (($this->config['gemini_voice'] ?? null) ?: config('services.gemini.voice'));
    }

    public function defaultRealtimeVoice(): string
    {
        return (string) (($this->config['realtime_voice'] ?? null) ?: config('services.openai.realtime_voice'));
    }

    /**
     * Voix figée dans le jeton Gemini Live : celle choisie dans « Essai des voix » si elle est proposée, sinon la
     * voix par défaut du professeur (Teacher Joe : même règle que App\Support\Gemini\TeacherVoice::current).
     */
    public function geminiVoice(): ?string
    {
        $chosen = $this->chosen($this->config['voice_setting'] ?? null);
        $default = $this->defaultGeminiVoice();

        return filled($chosen) && (array_key_exists($chosen, $this->geminiTrials()) || $chosen === $default) ? $chosen : $default;
    }

    /** Voix de l'appel OpenAI Realtime (secours des séances en direct). */
    public function realtimeVoice(): string
    {
        $chosen = $this->chosen($this->config['realtime_setting'] ?? null);

        return filled($chosen) && array_key_exists($chosen, $this->openaiTrials()) ? $chosen : $this->defaultRealtimeVoice();
    }

    /** La voix Gemini a-t-elle été choisie dans « Essai des voix » ? */
    public function isGeminiVoiceChosen(): bool
    {
        return $this->geminiVoice() === $this->chosen($this->config['voice_setting'] ?? null);
    }

    public function isRealtimeVoiceChosen(): bool
    {
        return $this->realtimeVoice() === $this->chosen($this->config['realtime_setting'] ?? null);
    }

    /** Enregistre la voix choisie dans « Essai des voix » (fournisseur gemini ou openai). */
    public function chooseVoice(string $provider, string $voice): void
    {
        $setting = $provider === 'openai' ? ($this->config['realtime_setting'] ?? null) : ($this->config['voice_setting'] ?? null);

        if (filled($setting)) {
            Setting::put((string) $setting, $voice);
        }
    }

    private function chosen(mixed $setting): ?string
    {
        return filled($setting) ? Setting::get((string) $setting) : null;
    }

    private function avatarKey(): string
    {
        return (string) ($this->config['avatar'] ?? 'model');
    }
}
