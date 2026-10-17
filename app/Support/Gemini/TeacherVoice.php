<?php

namespace App\Support\Gemini;

use App\Models\Setting;

/**
 * Voix du professeur : celle choisie par l'administrateur (page « Essai des voix »),
 * sinon GEMINI_VOICE_NAME (config services.gemini.voice).
 */
class TeacherVoice
{
    public const SETTING = 'teacher_voice';

    /**
     * Voix proposées à l'essai : nom => description officielle (config services.gemini.voice_trials).
     *
     * @return array<string, string>
     */
    public static function trials(): array
    {
        return config('services.gemini.voice_trials', []);
    }

    public static function isAllowed(string $voice): bool
    {
        return array_key_exists($voice, self::trials()) || $voice === config('services.gemini.voice');
    }

    /**
     * Voix figée dans chaque jeton.
     */
    public static function current(): ?string
    {
        $chosen = Setting::get(self::SETTING);

        return filled($chosen) && self::isAllowed($chosen) ? $chosen : config('services.gemini.voice');
    }

    /** Vrai si la voix vient d'un choix de l'administrateur (et non de la valeur par défaut). */
    public static function isChosen(): bool
    {
        $chosen = Setting::get(self::SETTING);

        return filled($chosen) && self::isAllowed($chosen);
    }

    public static function choose(string $voice): void
    {
        Setting::put(self::SETTING, $voice);
    }
}
