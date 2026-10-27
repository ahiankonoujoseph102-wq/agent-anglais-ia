<?php

namespace App\Support\Live;

use App\Enums\SubscriptionStatus;
use App\Models\CourseSession;
use App\Models\Lesson;
use App\Models\LevelTest;
use App\Models\Subscription;
use App\Support\Gemini\UsageCost;
use App\Support\Plan;
use App\Support\SiteSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Coût des abonnés : ce que coûtent déjà (estimation d'après les jetons, UsageCost) les séances d'un abonné depuis le
 * début de sa formule en cours, comparé au prix de cette formule. Au-delà d'un seuil réglable (« Réglages » :
 * pourcentage du prix, taux de change), l'abonné apparaît dans « Consommation » et l'administrateur est prévenu une
 * fois (commande usage:alert-costs).
 *
 * Accès offert (prix 0) : comparé au prix de la même formule au catalogue.
 */
final class CostWatch
{
    public const SETTINGS = ['cost_alert.percent', 'cost_alert.xof_per_usd'];

    /** Seuil : pourcentage du prix de la formule. */
    public static function percent(): int
    {
        return max(1, (int) SiteSettings::get('cost_alert.percent'));
    }

    /** Francs CFA pour un dollar US (pour comparer le coût, en dollars, au prix, en francs CFA). */
    public static function xofPerUsd(): int
    {
        return max(1, (int) SiteSettings::get('cost_alert.xof_per_usd'));
    }

    /**
     * Prix de référence de la formule, en francs CFA (accès offert : prix de la formule au catalogue).
     */
    public static function revenue(Subscription $subscription): int
    {
        return $subscription->price > 0 ? $subscription->price : (Plan::find((string) $subscription->plan)?->price ?? 0);
    }

    /**
     * Coût estimé, en dollars US, des séances de l'abonné commencées pendant cette formule (séance du jour,
     * « Mes cours », bilan de niveau ; l'évaluation gratuite n'en fait pas partie).
     */
    public static function cost(Subscription $subscription): float
    {
        $cost = 0.0;

        foreach ([Lesson::query(), CourseSession::query(), LevelTest::query()] as $query) {
            /** @var Builder<Lesson>|Builder<CourseSession>|Builder<LevelTest> $query */
            $query->where('user_id', $subscription->user_id)
                ->whereNotNull('started_at')
                ->where('started_at', '>=', $subscription->starts_at)
                ->where('started_at', '<', $subscription->ends_at);
            $cost += LiveUsage::tokenCost($query);
        }

        return $cost;
    }

    /**
     * Formules en cours (payées, provisoires ou offertes), du plus coûteux au moins coûteux par rapport au prix.
     *
     * @return Collection<int, array{subscription: Subscription, cost: float, revenue: int, threshold: float, share: int, over: bool}>
     */
    public static function current(): Collection
    {
        return Subscription::query()
            ->where(fn (Builder $query) => $query
                ->where('status', SubscriptionStatus::Active)
                ->orWhere(fn (Builder $query) => $query->where('status', SubscriptionStatus::Provisional)->where('provisional_until', '>', now())))
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->with('user')
            ->get()
            ->map(fn (Subscription $subscription) => self::row($subscription))
            ->filter(fn (array $row) => $row['cost'] > 0)
            ->sortByDesc('share')
            ->values();
    }

    /**
     * @return array{subscription: Subscription, cost: float, revenue: int, threshold: float, share: int, over: bool}
     */
    public static function row(Subscription $subscription): array
    {
        $cost = self::cost($subscription);
        $revenue = self::revenue($subscription);
        $revenueUsd = $revenue / self::xofPerUsd();
        $threshold = $revenueUsd * self::percent() / 100;

        return [
            'subscription' => $subscription,
            'cost' => $cost,
            'revenue' => $revenue,
            'threshold' => $threshold,
            // Part du prix déjà dépensée (en pour cent).
            'share' => $revenueUsd > 0 ? (int) round(100 * $cost / $revenueUsd) : 0,
            'over' => $threshold > 0 && $cost >= $threshold,
        ];
    }
}
