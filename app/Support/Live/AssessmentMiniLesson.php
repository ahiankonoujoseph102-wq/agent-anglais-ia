<?php

namespace App\Support\Live;

use App\Enums\Level;
use App\Support\Curriculum\Curriculum;
use App\Support\Curriculum\LessonBoards;

/**
 * Mini-leçon de la fin de l'évaluation gratuite (AssessmentController::result, banc d'essai du professeur) :
 * PERSONNELLE, sur les une ou deux vraies erreurs de l'apprenant transmises avec le verdict (ou une tournure plus
 * naturelle du niveau supérieur) ; en secours seulement, quelques phrases de la première leçon du niveau trouvé.
 * Renvoie le tableau montré à l'écran (« discovery ») et la suite donnée au professeur (« next_step »), qui finit par ce
 * que l'apprenant apprendra ensuite : deux objectifs RÉELS du programme de son niveau (goals), jamais une promesse.
 */
final class AssessmentMiniLesson
{
    /**
     * Tableau et suite de la mini-leçon pour un niveau.
     *
     * @param  list<array{said: string, correct: string, why: string}>  $lesson
     * @return array<string, mixed>
     */
    public static function for(Level $level, array $lesson = []): array
    {
        if ($lesson !== []) {
            $items = collect($lesson)->values()->map(fn (array $item, int $i) => '('.($i + 1).') '
                .($item['said'] !== '' ? 'they said "'.$item['said'].'", right form "'.$item['correct'].'"' : 'a more natural phrase: "'.$item['correct'].'"')
                .' (why: '.$item['why'].')')->implode(' ');

            return [
                'discovery' => [
                    'title' => 'Votre leçon personnelle',
                    'heading' => count($lesson) > 1 ? 'Vos deux points du jour' : 'Votre point du jour',
                    'phrases' => array_map(fn (array $item) => [
                        'en' => $item['correct'],
                        'fr' => ($item['said'] !== '' ? 'Vous avez dit « '.$item['said'].' ». ' : '').$item['why'],
                        'note' => null,
                    ], $lesson),
                ],
                'next_step' => strtr((string) config('teacher.assessment_next_step'), [
                    ':intro' => (string) config('platform.assessment.discovery_intro'),
                    ':items' => $items,
                    ':goals' => self::goals($level),
                ]),
            ];
        }

        $board = LessonBoards::firstLesson($level);
        $phrases = array_slice($board['phrases'], 0, max(1, (int) config('platform.assessment.discovery_phrases')));

        if ($phrases === []) {
            return [];
        }

        $phrases = array_map(fn (array $phrase) => ['en' => $phrase['en'], 'fr' => $phrase['fr'] ?? null, 'note' => $phrase['use'] ?? null], $phrases);
        // En une minute : la phrase la plus courte est enseignée, et vient en premier au tableau.
        usort($phrases, fn (array $a, array $b) => mb_strlen($a['en']) <=> mb_strlen($b['en']));
        $first = $phrases[0];

        return [
            'discovery' => ['title' => $board['title'], 'heading' => 'Votre première phrase', 'phrases' => $phrases],
            'next_step' => strtr((string) config('teacher.assessment_next_step_phrase'), [
                ':intro' => (string) config('platform.assessment.discovery_intro'),
                ':phrase' => $first['en'],
                ':meaning' => $first['fr'] ? ' (in French: "'.$first['fr'].'")' : '',
                ':goals' => self::goals($level),
            ]),
        ];
    }

    /**
     * Ce que l'apprenant apprendra dans les premières semaines : le premier objectif des unités 1 et 2 du programme
     * officiel de son niveau (« saluer quelqu'un …, puis à présenter les membres de votre famille »).
     */
    public static function goals(Level $level): string
    {
        $goals = collect(array_slice(Curriculum::units($level), 0, 2))
            ->map(fn ($unit) => $unit->objectives[0] ?? null)
            ->filter()
            ->values();

        return $goals->count() > 1 ? $goals[0].', puis à '.$goals[1] : (string) $goals->first();
    }
}
