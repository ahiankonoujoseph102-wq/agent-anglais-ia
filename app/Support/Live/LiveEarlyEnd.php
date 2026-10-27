<?php

namespace App\Support\Live;

use App\Models\Assessment;
use App\Models\Lesson;
use Illuminate\Support\Facades\Cache;

/**
 * Fin trop tôt : dans les vraies conversations, le professeur donnait parfois le niveau après deux ou trois
 * questions (évaluations de 2 minutes annoncées de 5) ou commençait le bilan de la séance du jour au bout de
 * 5 minutes sur 15. La PREMIÈRE demande trop tôt n'est pas enregistrée : la réponse dit au professeur de continuer.
 * Une seconde demande est toujours acceptée (l'élève veut vraiment arrêter, ou ne peut pas en dire plus).
 */
final class LiveEarlyEnd
{
    /**
     * @return array{saved: false, too_early: true, next_step: string}|null
     */
    public static function assessment(Assessment $assessment): ?array
    {
        if (! config('platform.live.refuse_early_end')) {
            return null;
        }

        $minimum = (int) config('platform.assessment.min_verdict_seconds');
        $elapsed = $assessment->elapsedUntil(now());

        if (! $assessment->isInProgress() || $elapsed >= $minimum || ! self::firstTime('assessment', $assessment->id)) {
            return null;
        }

        return self::refusal('Not saved yet: the assessment has lasted only about '.max(1, intdiv($elapsed, 60) + 1).' minutes, '
            .'too early to judge the level. Do NOT announce any level and do not say goodbye. Ask two or three more '
            .'questions first, one at a time, on new topics adapted to the learner (daily routine, last weekend, plans, '
            .'a simple opinion; easier ones if they struggle), then call the function again.');
    }

    /**
     * @return array{saved: false, too_early: true, next_step: string}|null
     */
    public static function lesson(Lesson $lesson): ?array
    {
        if (! config('platform.live.refuse_early_end')) {
            return null;
        }

        // Trop tôt : il reste plus que (préavis + marge) minutes ET plus du tiers de la séance.
        $margin = max(
            ((int) config('platform.lessons.warning_minutes_before_end') + (int) config('platform.lessons.early_end_extra_minutes')) * 60,
            (int) ceil($lesson->allowed_seconds * 0.35),
        );
        $remaining = $lesson->remainingSeconds();

        if (! $lesson->isInProgress() || $remaining <= $margin || ! self::firstTime('lesson', $lesson->id)) {
            return null;
        }

        return self::refusal('Not saved: it is too early, about '.(int) ceil($remaining / 60).' minutes of today\'s lesson remain. '
            .'Do NOT sum up and do NOT say goodbye. Go back to the lesson: continue STEP 3 (conversation with today\'s '
            .'situation, the learner reusing what you taught), or more STEP 2 practice if the learner needs it. Call '
            .'end_lesson only when you are told that '.(int) config('platform.lessons.warning_minutes_before_end').' minutes remain, '
            .'or if the learner clearly says they must stop now.');
    }

    private static function firstTime(string $kind, int $id): bool
    {
        return Cache::add("live-early-end:{$kind}:{$id}", true, now()->addHours(3));
    }

    /**
     * @return array{saved: false, too_early: true, next_step: string}
     */
    private static function refusal(string $nextStep): array
    {
        return ['saved' => false, 'too_early' => true, 'next_step' => $nextStep];
    }
}
