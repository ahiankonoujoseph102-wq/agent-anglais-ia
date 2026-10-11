<?php

namespace App\Support\Gemini;

use App\Enums\Level;
use App\Models\User;

/**
 * Construit la configuration de conversation du professeur à partir de
 * config/teacher.php (consignes) et config/services.php (modèle, voix).
 */
class TeacherSetup
{
    public const RECORD_ASSESSMENT_RESULT = 'record_assessment_result';

    public const END_LESSON = 'end_lesson';

    /**
     * @return array<string, mixed>
     */
    public static function assessment(User $user, int $minutes, ?string $resumeHandle = null): array
    {
        return self::setup(
            self::instructions('assessment', $user, $minutes),
            [[
                'name' => self::RECORD_ASSESSMENT_RESULT,
                'description' => 'Records the learner\'s CEFR level and a short appreciation in French at the end of the assessment.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'level' => [
                            'type' => 'STRING',
                            'enum' => array_column(Level::cases(), 'value'),
                            'description' => 'CEFR level of the learner.',
                        ],
                        'feedback' => [
                            'type' => 'STRING',
                            'description' => 'Two or three sentences in French: strengths and what to work on.',
                        ],
                    ],
                    'required' => ['level', 'feedback'],
                ],
            ]],
            $resumeHandle,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function lesson(User $user, int $minutes, ?string $resumeHandle = null): array
    {
        return self::setup(
            self::instructions('lesson', $user, $minutes),
            [[
                'name' => self::END_LESSON,
                'description' => 'Records the summary of the lesson and the homework, written in French, before saying goodbye.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'summary' => [
                            'type' => 'STRING',
                            'description' => 'Short summary of the lesson in French (what was learned, key phrases).',
                        ],
                        'homework' => [
                            'type' => 'STRING',
                            'description' => 'One small homework for the next lesson, in French.',
                        ],
                    ],
                    'required' => ['summary', 'homework'],
                ],
            ]],
            $resumeHandle,
        );
    }

    /**
     * Indication envoyée au professeur pendant la conversation (config teacher.cues).
     *
     * @param  array<string, int|string>  $replace
     */
    public static function cue(string $key, array $replace = []): string
    {
        return self::fill((string) config("teacher.cues.{$key}"), $replace);
    }

    /**
     * Consignes complètes : personnage + niveau + déroulé propre à la conversation.
     */
    public static function instructions(string $kind, User $user, int $minutes): string
    {
        $level = $user->level?->value ?? 'unknown';

        $text = implode("\n\n", [
            trim((string) config('teacher.persona')),
            (string) config("teacher.level_guidance.{$level}", ''),
            trim((string) config("teacher.{$kind}")),
        ]);

        return self::fill($text, [
            'platform' => config('platform.name'),
            'name' => $user->name,
            'level' => $level,
            'minutes' => $minutes,
            'warning' => (int) config('platform.lessons.warning_minutes_before_end'),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $functions
     * @return array<string, mixed>
     */
    private static function setup(string $instructions, array $functions, ?string $resumeHandle): array
    {
        $generationConfig = ['responseModalities' => ['AUDIO']];

        if (filled($voice = config('services.gemini.voice'))) {
            $generationConfig['speechConfig'] = [
                'voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voice]],
            ];
        }

        return [
            'model' => 'models/'.config('services.gemini.live_model'),
            'generationConfig' => $generationConfig,
            'systemInstruction' => ['parts' => [['text' => $instructions]]],
            'tools' => [['functionDeclarations' => $functions]],
            // Sous-titres : transcription de ce que dit l'apprenant et le professeur.
            'inputAudioTranscription' => (object) [],
            'outputAudioTranscription' => (object) [],
            // Au-delà d'environ 15 min d'audio, la fenêtre de contexte doit glisser.
            'contextWindowCompression' => ['slidingWindow' => (object) []],
            // Permet de reprendre la conversation sur une nouvelle connexion.
            'sessionResumption' => filled($resumeHandle) ? ['handle' => $resumeHandle] : (object) [],
        ];
    }

    /**
     * @param  array<string, int|string>  $replace
     */
    private static function fill(string $text, array $replace): string
    {
        $pairs = [];

        foreach ($replace as $key => $value) {
            $pairs[':'.$key] = (string) $value;
        }

        // strtr remplace en une seule passe, les marqueurs les plus longs d'abord.
        return strtr($text, $pairs);
    }
}
