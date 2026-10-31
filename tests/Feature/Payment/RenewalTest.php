<?php

namespace Tests\Feature\Payment;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

class RenewalTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    public function test_reminder_appears_three_days_before_the_end(): void
    {
        $user = User::factory()->level(Level::A2)->create();
        Subscription::factory()->for($user)->create([
            'starts_at' => now()->subDays(26),
            'ends_at' => now()->addDays(4),
        ]);
        $this->buildProgram($user);

        // Le lien discret « Renouveler ou changer » de la carte « Ma formule » est toujours là ; le rappel, non.
        $this->actingAs($user)->get('/espace')->assertDontSee('Votre formule se termine le');

        $this->travel(2)->days();

        $this->actingAs($user)->get('/espace')
            ->assertSee('Votre formule se termine le')
            ->assertSee('Renouveler')
            ->assertSee(route('payment.plans'))
            ->assertSee('Parler avec Teacher Joe');
    }

    public function test_expiration_blocks_lessons_and_guides_to_renewal_while_assessment_stays_visible(): void
    {
        Http::fake();
        config(['services.gemini.key' => 'cle']);

        $user = User::factory()->level(Level::B1)->create();
        $user->assessment()->create([
            'status' => AssessmentStatus::Completed,
            'started_at' => now()->subMonth(),
            'allowed_seconds' => 300,
            'used_seconds' => 300,
            'level' => Level::B1,
            'feedback' => 'Très bonne compréhension orale.',
        ]);
        Subscription::factory()->for($user)->create([
            'starts_at' => now()->subDays(29),
            'ends_at' => now()->addDay(),
        ]);
        $this->buildProgram($user);

        $this->actingAs($user)->get('/seance')->assertOk();

        $this->travel(2)->days();
        $this->artisan('subscriptions:expire')->assertSuccessful();

        $user = $user->fresh();
        $this->actingAs($user)->get('/seance')
            ->assertRedirect('/abonnement')
            ->assertSessionHas('status', 'Votre abonnement a expiré : renouvelez-le pour reprendre vos séances.');
        $this->actingAs($user)->postJson('/seance/connexion')->assertForbidden();
        // Aucun jeton de séance demandé (la seule demande possible est la préparation du tableau de la leçon).
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'auth_tokens'));

        $this->actingAs($user)->get('/espace')
            ->assertSee('Votre abonnement a expiré')
            ->assertSee('Renouveler')
            ->assertDontSee('Parler avec Teacher Joe')
            // L'évaluation et ses résultats restent visibles.
            ->assertSee('B1')
            ->assertSee('Très bonne compréhension orale.');
    }
}
