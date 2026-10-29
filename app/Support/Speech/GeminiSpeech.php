<?php

namespace App\Support\Speech;

use App\Support\Gemini\TeacherVoice;
use App\Support\Gemini\VoiceSampler;

/**
 * Synthèse vocale de Google (modèle GEMINI_TTS_MODEL, voix choisie dans « Essai des voix »).
 */
class GeminiSpeech extends SpeechProvider
{
    public const NAME = 'gemini';

    public function __construct(private VoiceSampler $sampler) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return 'Gemini (Google)';
    }

    public function keyVariable(): string
    {
        return 'GEMINI_API_KEY';
    }

    public function hasKey(): bool
    {
        return VoiceSampler::keys() !== [];
    }

    public function model(): string
    {
        return (string) config('services.gemini.tts_model');
    }

    public function voice(): string
    {
        // Teacher Joe : la voix d'« Essai des voix », comme avant ; un autre professeur : la sienne.
        return (string) ($this->teacher?->speechVoiceFor('gemini') ?? $this->teacher?->geminiVoice() ?? TeacherVoice::current());
    }

    public function spacing(): int
    {
        return (int) config('services.gemini.tts_spacing_seconds');
    }

    public function costPerMinute(): float
    {
        return (float) config('services.gemini.tts_cost_per_minute');
    }

    public function synthesize(string $text, string $style, string $label = 'Synthèse vocale', ?int $timeout = null): string
    {
        // VoiceSampler journalise les échecs et compte la consommation.
        return $this->sampler->synthesize($this->voice(), $text, $style, $label, $timeout);
    }
}
