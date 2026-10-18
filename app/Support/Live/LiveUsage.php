<?php

namespace App\Support\Live;

use App\Models\Assessment;
use App\Models\CourseSession;
use App\Models\Lesson;
use App\Models\LevelTest;

/**
 * Consommation des séances en direct par fournisseur : nombre de séances, minutes réelles et coût estimé
 * (live.gemini_cost_per_minute : 0 en mode gratuit ; openai.realtime_cost_per_minute).
 */
final class LiveUsage
{
    /**
     * @return array<string, array{count: int, seconds: int, cost: float}>
     */
    public static function totals(): array
    {
        $totals = [];

        foreach (LiveProviders::all() as $provider) {
            $totals[$provider] = ['count' => 0, 'seconds' => 0, 'cost' => 0.0];
        }

        foreach ([Assessment::query(), Lesson::query(), LevelTest::query(), CourseSession::query()] as $query) {
            $rows = $query->toBase()
                ->whereNotNull('started_at')
                ->selectRaw('live_provider, count(*) as aggregate_count, coalesce(sum(used_seconds), 0) as seconds')
                ->groupBy('live_provider')
                ->get();

            foreach ($rows as $row) {
                if (isset($totals[$row->live_provider])) {
                    $totals[$row->live_provider]['count'] += (int) $row->aggregate_count;
                    $totals[$row->live_provider]['seconds'] += (int) $row->seconds;
                }
            }
        }

        foreach ($totals as $provider => $total) {
            $totals[$provider]['cost'] = round($total['seconds'] / 60 * self::costPerMinute($provider), 4);
        }

        return $totals;
    }

    public static function costPerMinute(string $provider): float
    {
        return $provider === LiveProviders::OPENAI
            ? (float) config('services.openai.realtime_cost_per_minute')
            : (float) config('services.live.gemini_cost_per_minute');
    }
}
