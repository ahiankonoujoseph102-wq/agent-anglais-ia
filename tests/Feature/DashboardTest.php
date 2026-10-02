<?php

namespace Tests\Feature;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_learner_sees_empty_dashboard(): void
    {
        $user = User::factory()->create(['name' => 'Afi']);

        $this->actingAs($user)
            ->get('/espace')
            ->assertOk()
            ->assertSee('Bonjour Afi')
            ->assertSee('Pas encore évalué')
            ->assertSee('Pas d\'abonnement actif', false)
            ->assertSee('Disponible avec un abonnement')
            ->assertSee('Votre évaluation gratuite');
    }

    public function test_dashboard_shows_level_plan_and_time_left_today(): void
    {
        $user = User::factory()->level(Level::B1)->create();
        $subscription = Subscription::factory()->for($user)->forPlan('intensif')->create();

        // 10 minutes déjà consommées aujourd'hui, en deux séances.
        foreach ([240, 360] as $seconds) {
            $user->lessons()->create([
                'subscription_id' => $subscription->id,
                'date' => today(),
                'allowed_seconds' => 1800,
                'used_seconds' => $seconds,
                'status' => LessonStatus::Completed,
            ]);
        }

        // Une séance d'hier ne compte pas.
        $user->lessons()->create([
            'date' => today()->subDay(),
            'allowed_seconds' => 1800,
            'used_seconds' => 1800,
            'status' => LessonStatus::Completed,
        ]);

        $this->actingAs($user)
            ->get('/espace')
            ->assertOk()
            ->assertSee('B1')
            ->assertSee('Intermédiaire')
            ->assertSee('Intensif')
            ->assertSee('30 min par jour')
            ->assertSee('20 min')
            ->assertSee('Ma séance du jour');

        $this->assertSame(1200, $user->remainingSecondsToday());
    }

    public function test_time_left_never_goes_below_zero(): void
    {
        $user = User::factory()->level(Level::A1)->create();
        Subscription::factory()->for($user)->create();

        $user->lessons()->create([
            'date' => today(),
            'allowed_seconds' => 900,
            'used_seconds' => 1000,
            'status' => LessonStatus::Interrupted,
        ]);

        $this->assertSame(0, $user->remainingSecondsToday());
    }

    public function test_expired_or_pending_subscriptions_are_ignored(): void
    {
        $user = User::factory()->level(Level::A2)->create();
        Subscription::factory()->for($user)->expired()->create();
        Subscription::factory()->for($user)->create(['status' => SubscriptionStatus::Pending]);

        $this->assertNull($user->activeSubscription);
        $this->assertNull($user->remainingSecondsToday());

        $this->actingAs($user)
            ->get('/espace')
            ->assertSee('Pas d\'abonnement actif', false)
            ->assertSee('Choisissez une formule');
    }

    public function test_subscription_keeps_the_conditions_it_was_sold_with(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->forPlan('essentiel')->create();

        config(['platform.plans.essentiel.daily_minutes' => 60]);

        $this->assertSame(15 * 60, $user->remainingSecondsToday());
    }
}
