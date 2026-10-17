<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    private function completedAssessment(User $user, Level $level): void
    {
        $user->assessment()->create([
            'status' => AssessmentStatus::Completed,
            'started_at' => now()->subDay(),
            'allowed_seconds' => 300,
            'used_seconds' => 280,
            'level' => $level,
        ]);
    }

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
            ->assertSee('Passez votre évaluation gratuite')
            ->assertSee(route('assessment.show'));
    }

    public function test_interrupted_assessment_without_level_is_explained(): void
    {
        $user = User::factory()->create();
        $user->assessment()->create([
            'status' => AssessmentStatus::Abandoned,
            'started_at' => now()->subHour(),
            'allowed_seconds' => 300,
            'used_seconds' => 40,
        ]);

        $this->actingAs($user)
            ->get('/espace')
            ->assertOk()
            ->assertSee('Contactez-nous pour la repasser.')
            ->assertDontSee(route('assessment.show'));
    }

    public function test_subscriber_can_open_the_daily_lesson(): void
    {
        $user = User::factory()->level(Level::A1)->create();
        Subscription::factory()->for($user)->create();

        // Sans programme, l'apprenant construit d'abord son programme personnel.
        $this->actingAs($user)->get('/espace')
            ->assertSee('Construire mon programme')
            ->assertSee(route('program.questionnaire', 1));

        $this->buildProgram($user);

        $this->actingAs($user->fresh())
            ->get('/espace')
            ->assertSee('Commencer ma séance')
            ->assertSee('Unité 1')
            ->assertSee(route('lesson.show'));
    }

    public function test_dashboard_shows_level_plan_and_time_left_today(): void
    {
        $user = User::factory()->level(Level::B1)->create();
        $this->completedAssessment($user, Level::B1);
        $subscription = Subscription::factory()->for($user)->forPlan('intensif')->create();
        $this->buildProgram($user);

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
            ->assertSee('30 minutes par jour')
            ->assertSee('20 minutes')
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
        $this->completedAssessment($user, Level::A2);
        Subscription::factory()->for($user)->expired()->create();
        Subscription::factory()->for($user)->create(['status' => SubscriptionStatus::Pending]);

        $this->assertNull($user->activeSubscription);
        $this->assertNull($user->remainingSecondsToday());

        $this->actingAs($user)
            ->get('/espace')
            ->assertSee('Pas d\'abonnement actif', false)
            ->assertSee('Votre abonnement a expiré')
            ->assertSee('Renouveler');
    }

    public function test_subscription_keeps_the_conditions_it_was_sold_with(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->forPlan('essentiel')->create();

        config(['platform.plans.essentiel.daily_minutes' => 60]);

        $this->assertSame(15 * 60, $user->remainingSecondsToday());
    }
}
