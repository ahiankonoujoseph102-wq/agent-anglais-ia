<?php

namespace App\Support\Gemini;

use App\Enums\Level;
use App\Enums\Skill;
use App\Models\Assessment;
use App\Models\User;
use App\Support\Curriculum\Curriculum;
use App\Support\Curriculum\Unit;
use App\Support\Live\WorkingTogether;

/**
 * Construit la configuration de conversation du professeur à partir de
 * config/teacher.php (consignes) et config/services.php (modèle, voix).
 */
class TeacherSetup
{
    /**
     * Résumé de l'évaluation (fonction record_assessment_result et estimation sans verdict) : en français,
     * adressé directement à l'apprenant avec « vous », jamais à la troisième personne.
     */
    public const FEEDBACK_RULE = 'Two or three sentences in French, written directly TO the learner with "vous" '
        .'(for example: "Vous comprenez bien les questions simples. Travaillez maintenant les verbes au passé."): '
        .'strengths, then what to work on. Never write about the learner in the third person '
        .'(never "l\'apprenant", "il", "elle" or the learner\'s name as a subject).';

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
                            'description' => self::FEEDBACK_RULE,
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
     * @param  int  $session  numéro de la séance parmi celles déjà faites (WorkingTogether::lessonNumber)
     * @return array<string, mixed>
     */
    public static function lesson(User $user, int $minutes, ?string $resumeHandle = null, ?string $context = null, int $session = 1): array
    {
        $scores = [];

        foreach (Skill::cases() as $skill) {
            $scores[$skill->value] = ['type' => 'INTEGER', 'description' => 'Mark from 1 to 5 ('.$skill->value.').'];
        }

        return self::setup(
            self::instructions('lesson', $user, $minutes, [
                'working_together' => WorkingTogether::lesson($session),
                'context' => $context ?? 'No programme context and no board today (the learner has finished the programme): skip STEP 1 and STEP 2, practise free conversation adapted to the learner\'s level (STEP 3), then STEP 4.',
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
                            'description' => 'Short summary of the lesson in French. Start with the sentence to remember, in English between double quotes, with its meaning in French; then what the learner can now do and the key phrases. It is used for the warm-up of the next lesson.',
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
            self::paceLevel('lesson', $user),
            'lesson',
        );
    }

    /**
     * Séance « Mes cours » : Teacher Joe aide l'apprenant sur une leçon qu'il a apportée. Aucune
     * fonction à appeler (pas de bilan) : la séance s'arrête au temps, par « Terminer » ou à la fermeture de la page.
     *
     * @param  string  $lesson  contenu de la leçon (App\Models\CourseLesson::promptContext), donnée et non consigne
     * @param  int  $session  numéro de la séance « Mes cours » parmi celles déjà faites (WorkingTogether::courseNumber)
     * @return array<string, mixed>
     */
    public static function course(User $user, int $minutes, string $lesson, ?string $resumeHandle = null, int $session = 1): array
    {
        return self::setup(
            self::instructions('course', $user, $minutes, [
                'working_together' => WorkingTogether::course($session),
                'lesson' => $lesson,
                'warning_seconds' => (int) config('platform.courses.warning_seconds'),
            ]),
            [],
            $resumeHandle,
            self::paceLevel('course', $user),
            'course',
        );
    }

    /**
     * Essai de 30 secondes de l'administrateur (diagnostic) : même personnage, aucune fonction.
     *
     * @return array<string, mixed>
     */
    public static function trial(User $user): array
    {
        return self::setup(self::instructions('trial', $user, 1), [], null);
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
            self::paceLevel('level_test', $user),
        );
    }

    /**
     * Indication envoyée au professeur pendant la conversation (config teacher.cues).
     *
     * @param  array<string, int|string>  $replace
     */
    public static function cue(string $key, array $replace = []): string
    {
        return self::fill((string) config("teacher.cues.{$key}"), $replace + ['closing' => (string) config('platform.assessment.closing_phrase')] + self::phasePhrases());
    }

    /**
     * Phrases de passage de la séance du jour (platform.lessons.phase_phrases), comme marqueurs :practice_phrase…
     *
     * @return array<string, string>
     */
    private static function phasePhrases(): array
    {
        $phrases = [];

        foreach ((array) config('platform.lessons.phase_phrases') as $phase => $phrase) {
            $phrases[$phase.'_phrase'] = (string) $phrase;
        }

        return $phrases;
    }

    /**
     * Règle de débit (config teacher.pace) avec la ligne du niveau de l'élève ; évaluation gratuite : niveau inconnu
     * (commencer lentement, puis s'adapter aux réponses).
     */
    public static function pace(string $kind, User $user): string
    {
        $level = self::paceLevel($kind, $user);

        return str_replace(':level_pace', (string) config("teacher.pace.levels.{$level}", config('teacher.pace.levels.unknown')), trim((string) config('teacher.pace.rules')));
    }

    /**
     * Niveau qui règle le débit (consignes et vitesse de la voix chez OpenAI) : « unknown » pendant l'évaluation.
     */
    public static function paceLevel(string $kind, User $user): string
    {
        return $kind === 'assessment' ? 'unknown' : ($user->level?->value ?? 'unknown');
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
            // Débit selon le niveau (seule règle de débit), toutes les conversations, chez les deux fournisseurs.
            self::pace($kind, $user),
            // L'évaluation gratuite et « Mes cours » ont leurs propres règles de langue ; la séance du jour a les siennes
            // (anglais d'abord, teacher.lesson_language, insérées par le marqueur :language de teacher.lesson).
            in_array($kind, ['assessment', 'course', 'lesson'], true) ? '' : (string) config("teacher.level_guidance.{$level}", ''),
            // Savoir-faire d'un vrai professeur : séance orale quotidienne et « Mes cours » seulement.
            in_array($kind, ['lesson', 'course'], true) ? trim((string) config('teacher.coaching')) : '',
            // Façon de corriger : faire chercher, puis donner la bonne forme, puis faire redire (même périmètre).
            in_array($kind, ['lesson', 'course'], true) ? trim((string) config('teacher.correction')) : '',
            // Écoute active (résumé de la réponse, une ou deux corrections importantes, question liée) : séance
            // quotidienne, « Mes cours » et bilan de niveau ; l'évaluation gratuite a sa propre règle, plus courte.
            in_array($kind, ['lesson', 'course', 'level_test'], true) ? trim((string) config('teacher.listening')) : '',
            // Anglais américain, toutes les conversations (aucun réglage d'accent chez les fournisseurs : voir config/teacher.php).
            trim((string) config('teacher.american_english')),
            trim((string) config("teacher.{$kind}")),
            trim((string) config('teacher.site_help')),
            // Conseiller honnête : prix réels, objections, WhatsApp seulement pour un vrai problème (pas l'essai du diagnostic).
            $kind === 'trial' ? '' : trim((string) config('teacher.advisor')),
        ]));

        $text = self::fill($text, $extra + [
            'platform' => config('platform.name'),
            'teacher' => config('avatar.model.name'),
            'founder' => (string) config('platform.founder.name'),
            'name' => $user->name,
            'level' => $level,
            'minutes' => $minutes,
            'warning' => (int) config('platform.lessons.warning_minutes_before_end'),
            // « Comment travailler avec moi » : accueil des premières séances (sinon première séance par défaut).
            'working_together' => $kind === 'course' ? WorkingTogether::course(1) : WorkingTogether::lesson(1),
            // Fin de l'évaluation : questions libres, conclusion et sa phrase fixe.
            'questions_minutes' => (int) ceil(Assessment::afterVerdictSeconds() / 60),
            'discovery_intro' => (string) config('platform.assessment.discovery_intro'),
            'max_questions' => (int) config('platform.assessment.questions_max'),
            'closing' => (string) config('platform.assessment.closing_phrase'),
            // Séance du jour : langue d'enseignement selon le niveau et phrases de passage d'un temps à l'autre.
            'language' => trim(config('teacher.lesson_language.common')."\n".config("teacher.lesson_language.{$level}", '')),
        ] + self::phasePhrases() + PlanFacts::replacements($kind));

        // Ajoutés APRÈS le remplacement des marqueurs : le nom de l'apprenant n'est jamais relu comme un marqueur.
        return implode("\n\n", [
            $text,
            "SITE GUIDE (in French)\n".SiteGuide::text($kind),
            "LEARNER STATUS (in French)\n".LearnerSituation::describe($user, $kind),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $functions
     * @return array<string, mixed>
     */
    private static function setup(string $instructions, array $functions, ?string $resumeHandle, string $paceLevel = 'unknown', ?string $kind = null): array
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

        return array_filter([
            'model' => 'models/'.config('services.gemini.live_model'),
            'generationConfig' => $generationConfig,
            'systemInstruction' => ['parts' => [['text' => $instructions]]],
            // Séance sans bilan (« Mes cours ») : aucune fonction déclarée.
            'tools' => $functions === [] ? null : [['functionDeclarations' => $functions]],
            'realtimeInputConfig' => self::realtimeInputConfig($paceLevel),
            // Sous-titres : transcription de ce que dit l'apprenant et le professeur.
            'inputAudioTranscription' => (object) [],
            'outputAudioTranscription' => (object) [],
            // Au-delà d'environ 15 min d'audio, la fenêtre de contexte doit glisser ; pour la séance du jour et
            // « Mes cours », elle glisse plus tôt (contextWindow), car tout le contexte est refacturé à chaque réponse.
            'contextWindowCompression' => self::contextWindow($instructions, $kind),
            // Permet de reprendre la conversation sur une nouvelle connexion.
            'sessionResumption' => filled($resumeHandle) ? ['handle' => $resumeHandle] : (object) [],
        ], fn ($value) => $value !== null);
    }

    /**
     * Fenêtre de contexte : Gemini Live refacture à CHAQUE réponse tout le contexte (consignes + audio déjà échangé,
     * environ 25 jetons par seconde de conversation). Pour la séance du jour et « Mes cours », l'audio gardé est borné
     * (services.gemini.context_window.audio_tokens, environ 8 minutes d'échange) : au-delà, les plus anciens échanges
     * glissent hors du contexte, les consignes (leçon du jour, tableau, erreurs à revoir) restent toujours. L'évaluation
     * et le bilan de niveau gardent tout : leur verdict dépend de toute la conversation.
     *
     * @return array<string, mixed>
     */
    public static function contextWindow(string $instructions, ?string $kind): array
    {
        $audio = (int) config('services.gemini.context_window.audio_tokens', 0);

        if ($audio <= 0 || ! in_array($kind, (array) config('services.gemini.context_window.kinds', []), true)) {
            return ['slidingWindow' => (object) []];
        }

        // Estimation large des consignes (3 caractères par jeton) : la fenêtre n'est jamais plus petite que prévu.
        $instructionTokens = (int) ceil(mb_strlen($instructions) / 3);
        $trigger = min(128_000, max(5_000, $instructionTokens + $audio));

        return [
            'triggerTokens' => $trigger,
            'slidingWindow' => ['targetTokens' => max(2_500, $trigger - intdiv($audio, 2))],
        ];
    }

    /**
     * Silence attendu avant de considérer que l'élève a fini de parler (services.live.silence_ms), selon le niveau.
     */
    public static function silenceMs(string $paceLevel): int
    {
        $silence = (array) config('services.live.silence_ms');

        return max(300, (int) ($silence[$paceLevel] ?? $silence['default'] ?? 800));
    }

    /**
     * Détection de la parole de l'apprenant par Google (config services.gemini.activity_detection).
     * Le professeur peut toujours être interrompu par une vraie prise de parole
     * (bouton « Interrompre le professeur ») : le navigateur coupe le micro pendant sa voix.
     *
     * @return array<string, mixed>
     */
    private static function realtimeInputConfig(string $paceLevel): array
    {
        $detection = config('services.gemini.activity_detection');

        return [
            'automaticActivityDetection' => [
                'disabled' => false,
                'startOfSpeechSensitivity' => $detection['start_sensitivity'],
                'endOfSpeechSensitivity' => $detection['end_sensitivity'],
                'prefixPaddingMs' => (int) $detection['prefix_padding_ms'],
                'silenceDurationMs' => self::silenceMs($paceLevel),
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
