<?php

namespace App\Support\Curriculum;

use App\Models\LessonBoard;
use App\Models\Program;
use App\Models\ProgramLesson;
use App\Support\Text\TextProviders;
use App\Support\Text\TextRequest;
use App\Support\Text\TextResult;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Le TABLEAU de la séance du jour : titre, phrases clés avec leur traduction, vocabulaire, verbes utiles avec
 * leurs formes, une règle simple. Affiché sur l'écran de séance et donné à Teacher Joe dans ses consignes.
 *
 * - Partagé : un tableau par niveau, unité, leçon, situation et objectif (et version du contenu, VERSION),
 *   préparé UNE fois par le modèle texte (Gemini puis OpenAI en secours, TextProviders), gardé en base
 *   (table lesson_boards) et réutilisé par tous les apprenants qui ont la même leçon.
 * - Préparé À L'AVANCE (queue() : après la réponse HTTP), jamais au moment de la connexion à la séance.
 * - S'il manque ou si la préparation échoue : tableau de secours tiré du programme officiel (fallback()),
 *   la séance fonctionne toujours.
 */
class LessonBoards
{
    /** Changer ce numéro fait préparer de nouveaux tableaux (nouvelles consignes de préparation). */
    public const VERSION = 'v1';

    public const PHRASES = [3, 6];

    public const VOCABULARY = [5, 8];

    public const VERBS = 6;

    /** Après un échec, aucune nouvelle demande pour ce tableau pendant ce temps (secondes). */
    private const RETRY_AFTER = 600;

    private const INSTRUCTIONS = 'You prepare the BOARD (blackboard) of one oral English lesson for French-speaking learners. '
        .'The teacher, an online AI teacher, teaches the lesson IN ENGLISH from this board, then makes the learner practice and talk. '
        .'Use ONLY the material of the unit given below (its vocabulary, grammar, speaking functions, natural spoken English) and today\'s '
        .'situation; never add grammar or vocabulary above the level. Everything in English is AMERICAN English (spelling, words, forms). '
        .'Key phrases: short, natural, useful sentences the learner will reuse in today\'s situation, adapted to the level and to the learner\'s goal, '
        .'each with a natural French translation. Vocabulary: the most useful words for today, each with its French translation. '
        .'Verbs: the verbs the learner needs today, with their forms ("go / went / gone" for irregular verbs, "work / worked / worked" for regular ones, '
        .'"be: I am / you are / he is" at A1 when useful) and the French meaning. Rule: ONE simple rule of today\'s grammar point, explained in very simple '
        .'English with one short example (rule_en), and the same rule in simple French (rule_fr). Title: a short English title of today\'s lesson. '
        .'Keep every item short: it is displayed on a phone screen.';

    /**
     * Clé du tableau partagé de cette leçon pour ce programme.
     */
    public static function key(ProgramLesson $lesson, Program $program): string
    {
        return sprintf('%s:%s-%02d-L%d-S%d-%s', self::VERSION, $program->level->value, $lesson->unit_number,
            $lesson->lesson_number, $lesson->situation_index, $program->goal->value);
    }

    /**
     * Tableau à afficher et à donner au professeur : celui préparé, sinon le tableau de secours (jamais d'appel ici).
     *
     * @return array{title: string, phrases: list<array{en: string, fr: ?string}>, vocabulary: list<array{en: string, fr: ?string}>, verbs: list<array{base: string, forms: string, fr: ?string}>, rule: array{en: ?string, fr: ?string}, source: string}
     */
    public static function for(ProgramLesson $lesson, Program $program): array
    {
        $board = LessonBoard::query()->where('key', self::key($lesson, $program))->first();

        if ($board !== null && ($content = self::normalize((array) $board->content)) !== null) {
            return $content + ['source' => $board->provider];
        }

        return self::fallback($lesson, $program);
    }

    public static function ready(ProgramLesson $lesson, Program $program): bool
    {
        return LessonBoard::query()->where('key', self::key($lesson, $program))->exists();
    }

    /**
     * Prépare le tableau après la réponse HTTP (aucune attente pour l'apprenant ; QUEUE_CONNECTION=sync, pas de worker).
     * Toute erreur est attrapée : la séance garde le tableau de secours.
     */
    public static function queue(?ProgramLesson $lesson, ?Program $program): void
    {
        if ($lesson === null || $program === null || TextProviders::available() === [] || self::ready($lesson, $program)) {
            return;
        }

        defer(static function () use ($lesson, $program) {
            try {
                self::prepare($lesson, $program);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * Prépare (si besoin) et enregistre le tableau ; null si aucun fournisseur n'a donné un tableau utilisable.
     *
     * @param  int  $timeout  délai accordé à chaque fournisseur (secondes)
     */
    public static function prepare(ProgramLesson $lesson, Program $program, int $timeout = 30): ?LessonBoard
    {
        $key = self::key($lesson, $program);

        if (($board = LessonBoard::query()->where('key', $key)->first()) !== null) {
            return $board;
        }

        if (Cache::has('lesson-board-failed:'.$key)) {
            return null;
        }

        // Deux préparations en même temps (deux apprenants, deux pages) : une seule demande au modèle.
        $lock = Cache::lock('lesson-board:'.$key, max(60, 2 * $timeout + 10));

        if (! $lock->get()) {
            return null;
        }

        try {
            $result = TextProviders::generate(new TextRequest(
                kind: 'board',
                instructions: self::INSTRUCTIONS,
                parts: [TextRequest::text(self::material($lesson, $program))],
                schema: self::schema(),
                schemaName: 'lesson_board',
                maxTokens: 1500,
                timeout: $timeout,
                effort: 'low',
            ), accept: fn (TextResult $result) => ($data = $result->json()) !== null && self::normalize($data) !== null);

            if ($result === null) {
                Cache::put('lesson-board-failed:'.$key, true, self::RETRY_AFTER);

                return null;
            }

            return LessonBoard::query()->updateOrCreate(['key' => $key], [
                'level' => $program->level->value,
                'unit_number' => $lesson->unit_number,
                'lesson_number' => $lesson->lesson_number,
                'situation_index' => $lesson->situation_index,
                'goal' => $program->goal->value,
                'content' => self::normalize($result->json()),
                'provider' => $result->provider,
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Ce que le modèle reçoit : la matière de l'unité, la leçon, la situation et l'objectif (données, pas de nom).
     */
    private static function material(ProgramLesson $lesson, Program $program): string
    {
        $unit = $lesson->unit();
        $situation = $lesson->situation();

        return implode("\n", array_filter([
            'LEVEL: '.$program->level->value.' ('.$program->level->label().')',
            'UNIT '.$unit->number.': '.$unit->title,
            'TODAY: lesson '.$lesson->lesson_number.' of '.Unit::LESSONS.' — focus: '.match ($lesson->lesson_number) {
                1 => 'new vocabulary and speaking functions',
                2 => 'grammar used in the situation',
                default => 'pronunciation and review of the whole unit',
            },
            'SITUATION: '.$situation->title.' — '.$situation->description,
            "LEARNER'S GOAL: ".$program->goal->label(),
            'OBJECTIVES: '.implode(' / ', $unit->objectives),
            'VOCABULARY (English = French): '.implode('; ', array_map(fn ($en, $fr) => $en.' = '.$fr, array_keys($unit->vocabulary), $unit->vocabulary)),
            'GRAMMAR: '.implode(' / ', $unit->grammar),
            'SPEAKING FUNCTIONS: '.implode(' / ', $unit->functions),
            $unit->spoken !== [] ? 'NATURAL SPOKEN ENGLISH: '.implode(' / ', $unit->spoken) : null,
            $unit->pitfalls !== [] ? 'DIFFICULTIES OF FRENCH SPEAKERS: '.implode(' / ', $unit->pitfalls) : null,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private static function schema(): array
    {
        $pair = fn (string $description) => [
            'type' => 'object',
            'properties' => [
                'en' => ['type' => 'string', 'description' => 'In American English.'],
                'fr' => ['type' => 'string', 'description' => 'Natural French translation.'],
            ],
            'required' => ['en', 'fr'],
            'description' => $description,
        ];

        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'Short English title of today\'s lesson (6 words at most).'],
                'phrases' => ['type' => 'array', 'description' => self::PHRASES[0].' to '.self::PHRASES[1].' key phrases.', 'items' => $pair('A key phrase.')],
                'vocabulary' => ['type' => 'array', 'description' => self::VOCABULARY[0].' to '.self::VOCABULARY[1].' words or short expressions.', 'items' => $pair('A word.')],
                'verbs' => [
                    'type' => 'array',
                    'description' => 'Up to '.self::VERBS.' useful verbs of today.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'base' => ['type' => 'string', 'description' => 'Base form, for example "go".'],
                            'forms' => ['type' => 'string', 'description' => 'Forms, for example "go / went / gone".'],
                            'fr' => ['type' => 'string', 'description' => 'French meaning, for example "aller".'],
                        ],
                        'required' => ['base', 'forms', 'fr'],
                    ],
                ],
                'rule_en' => ['type' => 'string', 'description' => 'One simple rule in very simple English, with one short example.'],
                'rule_fr' => ['type' => 'string', 'description' => 'The same rule in simple French.'],
            ],
            'required' => ['title', 'phrases', 'vocabulary', 'verbs', 'rule_en', 'rule_fr'],
        ];
    }

    /**
     * Tableau nettoyé (types sûrs, longueurs bornées), ou null s'il est inutilisable (trop peu de phrases ou de mots).
     *
     * @param  array<string, mixed>  $data
     * @return array{title: string, phrases: list<array{en: string, fr: ?string}>, vocabulary: list<array{en: string, fr: ?string}>, verbs: list<array{base: string, forms: string, fr: ?string}>, rule: array{en: ?string, fr: ?string}}|null
     */
    public static function normalize(array $data): ?array
    {
        $pairs = function ($items, int $max, int $length): array {
            $clean = [];

            foreach (is_array($items) ? $items : [] as $item) {
                $en = self::text($item['en'] ?? null, $length);

                if ($en !== null && count($clean) < $max) {
                    $clean[] = ['en' => $en, 'fr' => self::text($item['fr'] ?? null, $length)];
                }
            }

            return $clean;
        };

        $verbs = [];

        foreach (is_array($data['verbs'] ?? null) ? $data['verbs'] : [] as $verb) {
            $base = self::text($verb['base'] ?? null, 30);
            $forms = self::text($verb['forms'] ?? null, 80);

            if ($base !== null && $forms !== null && count($verbs) < self::VERBS) {
                $verbs[] = ['base' => $base, 'forms' => $forms, 'fr' => self::text($verb['fr'] ?? null, 60)];
            }
        }

        $rule = is_array($data['rule'] ?? null) ? $data['rule'] : ['en' => $data['rule_en'] ?? null, 'fr' => $data['rule_fr'] ?? null];

        $board = [
            'title' => self::text($data['title'] ?? null, 80),
            'phrases' => $pairs($data['phrases'] ?? [], self::PHRASES[1], 140),
            'vocabulary' => $pairs($data['vocabulary'] ?? [], self::VOCABULARY[1], 60),
            'verbs' => $verbs,
            'rule' => ['en' => self::text($rule['en'] ?? null, 300), 'fr' => self::text($rule['fr'] ?? null, 300)],
        ];

        if ($board['title'] === null || count($board['phrases']) < self::PHRASES[0] || count($board['vocabulary']) < self::VOCABULARY[0]) {
            return null;
        }

        return $board;
    }

    /**
     * Tableau de secours tiré du programme officiel : les phrases modèles des fonctions orales (« … »), le
     * vocabulaire de l'unité et la règle de grammaire de la leçon (en français). Aucun appel au modèle.
     *
     * @return array{title: string, phrases: list<array{en: string, fr: ?string}>, vocabulary: list<array{en: string, fr: ?string}>, verbs: list<array{base: string, forms: string, fr: ?string}>, rule: array{en: ?string, fr: ?string}, source: string}
     */
    public static function fallback(ProgramLesson $lesson, Program $program): array
    {
        $unit = $lesson->unit();
        $phrases = [];

        foreach ([...$unit->functions, ...$unit->grammar] as $line) {
            $label = trim((string) strstr($line, ':', true)) ?: null;

            preg_match_all('/«\s*([^»]+?)\s*»/u', $line, $quotes);

            foreach ($quotes[1] as $index => $quote) {
                // Seulement les phrases en anglais (les guillemets peuvent aussi citer du français).
                if (count($phrases) < self::PHRASES[1] && preg_match('/^[A-Za-z0-9 ,.!?\'’()\/-]+$/', $quote) && str_word_count($quote) >= 2) {
                    $phrases[] = ['en' => self::text($quote, 140), 'fr' => $index === 0 && $label !== null ? self::text($label, 140) : null];
                }
            }
        }

        $vocabulary = [];

        foreach (array_slice($unit->vocabulary, 0, self::VOCABULARY[1], true) as $en => $fr) {
            $vocabulary[] = ['en' => (string) $en, 'fr' => (string) $fr];
        }

        return [
            'title' => $unit->title,
            'phrases' => $phrases,
            'vocabulary' => $vocabulary,
            'verbs' => [],
            'rule' => ['en' => null, 'fr' => $unit->grammar[$lesson->lesson_number - 1] ?? $unit->grammar[0] ?? null],
            'source' => 'programme',
        ];
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
