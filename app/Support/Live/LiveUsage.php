<?php

namespace App\Support\Live;

use App\Models\Assessment;
use App\Models\CourseSession;
use App\Models\Lesson;
use App\Models\LevelTest;
use App\Support\Gemini\UsageCost;
use Illuminate\Database\Eloquent\Builder;

/**
 * Consommation des séances en direct par fournisseur : nombre de séances, minutes réelles et coût estimé
 * (live.gemini_cost_per_minute : 0 en mode gratuit ; openai.realtime_cost_per_minute). Coût d'après les jetons
 * (totaux, par apprenant) : chaque séance au tarif de SON fournisseur (UsageCost::usd).
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

    /**
     * Coût estimé d'après les jetons d'un ensemble de conversations, chacune au tarif de son fournisseur.
     *
     * @param  Builder<Assessment>|Builder<CourseSession>|Builder<Lesson>|Builder<LevelTest>  $query
     */
    public static function tokenCost(Builder $query): float
    {
        $rows = $query->toBase()
            ->selectRaw('live_provider, coalesce(sum(input_tokens), 0) as input, coalesce(sum(output_tokens), 0) as output')
            ->groupBy('live_provider')
            ->get();

        return (float) $rows->sum(fn (object $row) => UsageCost::usd((int) $row->input, (int) $row->output, (string) $row->live_provider));
    }

    /**
     * Coût estimé par apprenant, chaque conversation au tarif de son fournisseur : « oral » (évaluation et
     * séances du jour, comme les colonnes du tableau « Par apprenant ») et « course » (séances « Mes cours »).
     *
     * @param  list<int>  $userIds
     * @return array<int, array{oral: float, course: float}>
     */
    public static function costsByLearner(array $userIds): array
    {
        $costs = array_fill_keys($userIds, ['oral' => 0.0, 'course' => 0.0]);

        if ($userIds === []) {
            return $costs;
        }

        $sources = [
            'oral' => [Assessment::query(), Lesson::query()->whereNotNull('started_at')],
            'course' => [CourseSession::query()->whereNotNull('started_at')],
        ];

        foreach ($sources as $column => $queries) {
            foreach ($queries as $query) {
                $rows = $query->toBase()
                    ->whereIn('user_id', $userIds)
                    ->selectRaw('user_id, live_provider, coalesce(sum(input_tokens), 0) as input, coalesce(sum(output_tokens), 0) as output')
                    ->groupBy('user_id', 'live_provider')
                    ->get();

                foreach ($rows as $row) {
                    $costs[(int) $row->user_id][$column] += UsageCost::usd((int) $row->input, (int) $row->output, (string) $row->live_provider);
                }
            }
        }

        return $costs;
    }
}
