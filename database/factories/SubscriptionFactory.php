<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plan = Plan::find('essentiel');

        return [
            'user_id' => User::factory(),
            'plan' => $plan->key,
            'plan_name' => $plan->name,
            'daily_minutes' => $plan->dailyMinutes,
            'price' => $plan->price,
            'currency' => config('platform.currency'),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays($plan->durationDays - 1),
            'status' => SubscriptionStatus::Active,
        ];
    }

    public function forPlan(string $key): static
    {
        $plan = Plan::find($key);

        return $this->state(fn (array $attributes) => [
            'plan' => $plan->key,
            'plan_name' => $plan->name,
            'daily_minutes' => $plan->dailyMinutes,
            'price' => $plan->price,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDays(31),
            'ends_at' => now()->subDay(),
        ]);
    }
}
