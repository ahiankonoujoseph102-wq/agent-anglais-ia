<?php

namespace Tests\Feature\Live;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AccessGrant;
use App\Support\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Première séance d'une formule payée : Teacher Joe félicite et remercie en une phrase, puis dit en deux phrases
 * comment se passe la séance. Seulement la première séance de chaque formule payée.
 */
class FirstPaidLessonTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton']), '*/interactions' => Http::response([], 500)]);
    }

    private function learner(): User
    {
        $user = User::factory()->level(Level::A1)->create(['name' => 'Afi Mensah', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A1);

        return $user->fresh();
    }

    private function heldLesson(User $user, int $subscriptionId): void
    {
        $user->lessons()->create([
            'subscription_id' => $subscriptionId, 'date' => today()->subDay(), 'allowed_seconds' => 900, 'used_seconds' => 600,
            'started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addMinutes(10), 'status' => LessonStatus::Completed,
        ]);
    }

    private function startCue(User $user): string
    {
        return $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json('cues.start');
    }

    public function test_the_first_lesson_of_a_paid_plan_congratulates_and_explains_once(): void
    {
        $user = $this->learner();
        $cue = $this->startCue($user);

        $this->assertStringContainsString('ONE sentence to congratulate and thank them by first name for choosing '.config('platform.name'), $cue);
        $this->assertStringContainsString('TWO short sentences on how the lesson goes', $cue);
        // Le prénom n'est jamais écrit dans l'indication (il vient de la situation de l'apprenant).
        $this->assertStringNotContainsString('Afi', $cue);
    }

    public function test_not_again_on_the_same_plan(): void
    {
        $user = $this->learner();
        $this->heldLesson($user, $user->activeSubscription->id);

        $this->assertStringNotContainsString('congratulate', $this->startCue($user));
    }

    public function test_a_renewal_is_a_new_plan_so_once_again(): void
    {
        $user = $this->learner();
        $old = $user->activeSubscription;
        $this->heldLesson($user, $old->id);
        $old->forceFill(['ends_at' => now()->subMinute(), 'status' => 'expired'])->save();
        Subscription::factory()->for($user)->forPlan('intensif')->create();

        $this->assertStringContainsString('congratulate', $this->startCue($user->fresh()));
    }

    public function test_never_for_an_offered_access(): void
    {
        $user = User::factory()->level(Level::A1)->create(['welcomed_at' => now()]);
        AccessGrant::offer($user, User::factory()->admin()->create(), Plan::find('essentiel'), 7, null);
        $this->buildProgram($user, Level::A1);

        $this->assertStringNotContainsString('congratulate', $this->startCue($user->fresh()));
    }
}
