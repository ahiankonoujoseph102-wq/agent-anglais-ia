<?php

namespace App\Support\Curriculum;

use App\Enums\Level;

/**
 * Déroulé de la séance du jour en quatre temps : LEÇON (enseigner le tableau), PRATIQUE guidée, CONVERSATION
 * (mise en situation), BILAN. Le bilan occupe toujours les dernières minutes (platform.lessons.warning_minutes_before_end,
 * moment où le professeur est prévenu de la fin) ; le reste se partage selon le niveau (program.lesson_shares) :
 * surtout leçon et pratique en A1 et A2, équilibré en B1, surtout conversation de B2 à C2. Sous
 * program.lesson_merge_below minutes, la leçon et la pratique sont fusionnées. Calculé sur les minutes réelles de la
 * séance (temps restant du jour compris).
 */
final class LessonPlan
{
    public const PHASES = ['lesson', 'practice', 'conversation', 'summary'];

    /**
     * @return array{lesson: int, practice: int, conversation: int, summary: int, merged: bool, minutes: int}
     */
    public static function for(?Level $level, int $minutes): array
    {
        $minutes = max(0, $minutes);
        $summary = min($minutes, (int) config('platform.lessons.warning_minutes_before_end'));
        $rest = $minutes - $summary;
        $shares = (array) config('program.lesson_shares.'.self::band($level));
        $total = max(1, array_sum($shares));

        $lesson = $rest > 0 ? max(1, (int) round($rest * $shares['lesson'] / $total)) : 0;
        $practice = $rest > 0 ? (int) round($rest * $shares['practice'] / $total) : 0;
        $merged = $minutes < (int) config('program.lesson_merge_below');

        // Séance courte : leçon et pratique en un seul temps.
        if ($merged) {
            $lesson += $practice;
            $practice = 0;
        }

        $lesson = min($lesson, $rest);
        $practice = min($practice, $rest - $lesson);

        return [
            'lesson' => $lesson,
            'practice' => $practice,
            'conversation' => $rest - $lesson - $practice,
            'summary' => $summary,
            'merged' => $merged,
            'minutes' => $minutes,
        ];
    }

    /**
     * Début de chaque temps, en secondes depuis le début de la séance (affichage de la phase quand le professeur ne
     * dit pas sa phrase de passage, et relance douce vers la conversation).
     *
     * @param  array{lesson: int, practice: int, conversation: int, summary: int}  $plan
     * @return array{lesson: int, practice: int, conversation: int, summary: int}
     */
    public static function starts(array $plan): array
    {
        return [
            'lesson' => 0,
            'practice' => $plan['lesson'] * 60,
            'conversation' => ($plan['lesson'] + $plan['practice']) * 60,
            'summary' => ($plan['lesson'] + $plan['practice'] + $plan['conversation']) * 60,
        ];
    }

    private static function band(?Level $level): string
    {
        return match ($level) {
            Level::A1, Level::A2, null => 'beginner',
            Level::B1 => 'intermediate',
            default => 'advanced',
        };
    }
}
