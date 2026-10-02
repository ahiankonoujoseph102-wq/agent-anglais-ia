<?php

namespace App\Support;

/**
 * Une formule d'abonnement, telle que définie dans config/platform.php.
 */
final readonly class Plan
{
    public function __construct(
        public string $key,
        public string $name,
        public int $dailyMinutes,
        public int $price,
        public string $period,
        public int $durationDays,
    ) {}

    /**
     * @return array<string, self>
     */
    public static function all(): array
    {
        $plans = [];

        foreach (config('platform.plans', []) as $key => $plan) {
            $plans[$key] = new self(
                key: $key,
                name: $plan['name'],
                dailyMinutes: (int) $plan['daily_minutes'],
                price: (int) $plan['price'],
                period: $plan['period'],
                durationDays: (int) $plan['duration_days'],
            );
        }

        return $plans;
    }

    public static function find(string $key): ?self
    {
        return self::all()[$key] ?? null;
    }

    public function formattedPrice(): string
    {
        return Money::format($this->price);
    }

    public function periodLabel(): string
    {
        return match ($this->period) {
            'week' => 'par semaine',
            'month' => 'par mois',
            default => 'pour '.$this->durationDays.' jours',
        };
    }
}
