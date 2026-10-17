<?php

namespace App\Support\Gemini;

use App\Enums\Level;
use App\Enums\Skill;
use App\Models\User;
use App\Support\Curriculum\Curriculum;
use App\Support\Curriculum\Unit;

/**
 * Construit la configuration de conversation du professeur à partir de
 * config/teacher.php (consignes) et config/services.php (modèle, voix).
 */
class TeacherSetup
{
    public const RECORD_ASSESSMENT_RESULT = 'record_assessment_result';

    public const END_LESSON = 'end_lesson';

    public const RECORD_LEVEL_TEST_RESULT = 'record_level_test_result';

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
     * @param  string|null  $context  profil, leçon du jour et erreurs (App\Support\Curriculum\LessonContext)
     * @return array<string, mixed>
     */
    public static function lesson(User $user, int $minutes, ?string $resumeHandle = null, ?string $context = null): array
    {
        $scores = [];

        foreach (Skill::cases() as $skill) {
            $scores[$skill->value] = ['type' => 'INTEGER', 'description' => 'Mark from 1 to 5 ('.$skill->value.').'];
        }

        return self::setup(
            self::instructions('lesson', $user, $minutes, [
                'context' => $context ?? 'No programme context: practise free conversation adapted to the learner\'s level.',
            ]),
            [[
                'name' => self::END_LESSON,
                'description' => 'Records the structured report of the lesson before saying goodbye.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'lesson_status' => [
                            'type' => 'STRING',
                            'enum' => ['completed', 'repeat'],
                            'description' => '"completed" if the objectives of today\'s lesson are reached, "repeat" if it must be done again.',
                        ],
                        'summary' => [
                            'type' => 'STRING',
                            'description' => 'Short summary of the lesson in French (what was learned, key phrases).',
                        ],
                        'homework' => [
                            'type' => 'STRING',
                            'description' => 'One small homework for the next lesson, in French.',
                        ],
                        'errors' => [
                            'type' => 'ARRAY',
                            'description' => 'New mistakes made by the learner today.',
                            'items' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'wrong' => ['type' => 'STRING', 'description' => 'What the learner said.'],
                                    'correct' => ['type' => 'STRING', 'description' => 'The correct form.'],
                                    'explanation' => ['type' => 'STRING', 'description' => 'Very short explanation in French.'],
                                ],
                                'required' => ['wrong', 'correct'],
                            ],
                        ],
                        'reviewed_errors' => [
                            'type' => 'ARRAY',
                            'description' => 'Errors from "ERRORS TO REVIEW" checked today.',
                            'items' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'id' => ['type' => 'INTEGER', 'description' => 'Error id given in the instructions.'],
                                    'corrected' => ['type' => 'BOOLEAN', 'description' => 'True if the learner said it correctly today.'],
                                ],
                                'required' => ['id', 'corrected'],
                            ],
                        ],
                        'scores' => [
                            'type' => 'OBJECT',
                            'description' => 'Marks from 1 (very weak) to 5 (excellent) for the learner\'s level.',
                            'properties' => $scores,
                            'required' => array_keys($scores),
                        ],
                    ],
                    'required' => ['lesson_status', 'summary', 'homework', 'scores'],
                ],
            ]],
            $resumeHandle,
        );
    }

    /**
     * Bilan oral de fin de niveau.
     *
     * @return array<string, mixed>
     */
    public static function levelTest(User $user, Level $level, int $minutes, ?string $resumeHandle = null): array
    {
        $units = array_map(
            fn (Unit $unit) => sprintf('%d. %s — grammar: %s', $unit->number, $unit->title, implode(' / ', $unit->grammar)),
            Curriculum::units($level),
        );

        return self::setup(
            self::instructions('level_test', $user, $minutes, ['level' => $level->value, 'units' => implode("\n", $units)]),
            [[
                'name' => self::RECORD_LEVEL_TEST_RESULT,
                'description' => 'Records the result of the end-of-level oral test.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'passed' => ['type' => 'BOOLEAN', 'description' => 'True if the learner has the level.'],
                        'feedback' => ['type' => 'STRING', 'description' => 'Short feedback in French: strengths and what to improve.'],
                        'units_to_review' => [
                            'type' => 'ARRAY',
                            'description' => 'If failed: numbers (1 to 12) of the units to work on again.',
                            'items' => ['type' => 'INTEGER'],
                        ],
                    ],
                    'required' => ['passed', 'feedback'],
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
    /**
     * @param  array<string, int|string>  $extra  marqueurs supplémentaires (:context, :units…)
     */
    public static function instructions(string $kind, User $user, int $minutes, array $extra = []): string
    {
        $level = $user->level?->value ?? 'unknown';

        $text = implode("\n\n", array_filter([
            trim((string) config('teacher.persona')),
            // L'évaluation gratuite a ses propres règles (entièrement en anglais).
            $kind === 'assessment' ? '' : (string) config("teacher.level_guidance.{$level}", ''),
            trim((string) config("teacher.{$kind}")),
            trim((string) config('teacher.site_help')),
        ]));

        $text = self::fill($text, $extra + [
            'platform' => config('platform.name'),
            'teacher' => config('avatar.model.name'),
            'name' => $user->name,
            'level' => $level,
            'minutes' => $minutes,
            'warning' => (int) config('platform.lessons.warning_minutes_before_end'),
        ]);

        // Ajoutés APRÈS le remplacement des marqueurs : le nom de l'apprenant n'est jamais relu comme un marqueur.
        return implode("\n\n", [
            $text,
            "SITE GUIDE (in French)\n".SiteGuide::text(),
            "LEARNER STATUS (in French)\n".LearnerSituation::describe($user, $kind),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $functions
     * @return array<string, mixed>
     */
    private static function setup(string $instructions, array $functions, ?string $resumeHandle): array
    {
        // Pas de fonction d'expression (set_expression) : les appels de fonction de l'API Live sont
        // synchrones, le modèle s'arrête puis reprend la parole et répète sa dernière phrase. Les
        // expressions du professeur 3D sont déduites de ses paroles dans le navigateur (teacher-cues.js).

        $generationConfig = ['responseModalities' => ['AUDIO']];

        // Voix choisie par l'administrateur (page « Essai des voix »), sinon GEMINI_VOICE_NAME.
        if (filled($voice = TeacherVoice::current())) {
            $generationConfig['speechConfig'] = [
                'voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voice]],
            ];
        }

        return [
            'model' => 'models/'.config('services.gemini.live_model'),
            'generationConfig' => $generationConfig,
            'systemInstruction' => ['parts' => [['text' => $instructions]]],
            'tools' => [['functionDeclarations' => $functions]],
            'realtimeInputConfig' => self::realtimeInputConfig(),
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
     * Détection de la parole de l'apprenant par Google (config services.gemini.activity_detection).
     * Le professeur peut toujours être interrompu par une vraie prise de parole
     * (bouton « Interrompre le professeur ») : le navigateur coupe le micro pendant sa voix.
     *
     * @return array<string, mixed>
     */
    private static function realtimeInputConfig(): array
    {
        $detection = config('services.gemini.activity_detection');

        return [
            'automaticActivityDetection' => [
                'disabled' => false,
                'startOfSpeechSensitivity' => $detection['start_sensitivity'],
                'endOfSpeechSensitivity' => $detection['end_sensitivity'],
                'prefixPaddingMs' => (int) $detection['prefix_padding_ms'],
                'silenceDurationMs' => (int) $detection['silence_duration_ms'],
            ],
            'activityHandling' => 'START_OF_ACTIVITY_INTERRUPTS',
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
