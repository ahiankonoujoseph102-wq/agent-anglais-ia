<?php

namespace App\Support\Live;

use App\Enums\Level;
use App\Support\Curriculum\LessonBoards;

/**
 * Mini-leçon de la fin de l'évaluation gratuite (AssessmentController::result, banc d'essai du professeur) :
 * PERSONNELLE, sur les une ou deux vraies erreurs de l'apprenant transmises avec le verdict (ou une tournure plus
 * naturelle du niveau supérieur) ; en secours seulement, quelques phrases de la première leçon du niveau trouvé.
 * Renvoie le tableau montré à l'écran (« discovery ») et la suite donnée au professeur (« next_step »).
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
            ]),
        ];
    }
}
