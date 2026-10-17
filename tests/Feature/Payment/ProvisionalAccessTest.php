<?php

namespace Tests\Feature\Payment;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\PaymentChecks;
use App\Support\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsPrograms;
use Tests\Concerns\ConfiguresPayments;
use Tests\TestCase;

/**
 * Accès provisoire de 24 heures : ouverture, fin sans décision, validation sans
 * perte de jours, refus qui coupe l'accès, séance en cours jamais coupée brutalement.
 */
class ProvisionalAccessTest extends TestCase
{
    use BuildsPrograms;
    use ConfiguresPayments;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.private_disk' => 'local', 'services.gemini.key' => 'cle-test']);
        Storage::fake('local');
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton'])]);
        $this->freezeSecond();
        $this->configurePayments();
        $this->admin = User::factory()->admin()->create();
    }

    private function learner(): User
    {
        $user = User::factory()->create();
        $user->level = Level::A2;
        $user->save();

        return $user;
    }

    /**
     * Un paiement dont toutes les règles sont passées.
     */
    private function payment(User $user, string $planKey = 'essentiel'): Payment
    {
        $plan = Plan::find($planKey);
        Storage::disk('local')->put('payment-proofs/'.$user->id.'.png', 'image');

        return $user->payments()->create([
            'plan' => $plan->key,
            'plan_name' => $plan->name,
            'daily_minutes' => $plan->dailyMinutes,
            'duration_days' => $plan->durationDays,
            'amount' => $plan->price,
            'currency' => 'FCFA',
            'country' => 'TG',
            'operator' => 'flooz',
            'operator_name' => 'Flooz',
            'recipient_number' => '+228 90 00 00 22',
            'proof_path' => 'payment-proofs/'.$user->id.'.png',
            'checks' => array_map(fn (string $key) => ['key' => $key, 'label' => PaymentChecks::LABELS[$key], 'passed' => true, 'reason' => 'Bon.'], array_keys(PaymentChecks::LABELS)),
        ]);
    }

    public function test_provisional_access_lasts_24_hours_then_stops_while_payment_stays_pending(): void
    {
        $user = $this->learner();
        $payment = $this->payment($user);
        $subscription = $payment->grantProvisional();
        $this->buildProgram($user);

        $this->assertSame(SubscriptionStatus::Provisional, $subscription->status);
        $this->assertTrue($user->fresh()->activeSubscription->is($subscription));
        $this->actingAs($user->fresh())->get('/seance')->assertOk();

        // 24 heures plus tard, sans décision : l'accès s'arrête dès la requête suivante,
        // sans attendre le planificateur.
        $this->travel(24)->hours();
        $this->assertNull($user->fresh()->activeSubscription);
        $this->actingAs($user->fresh())->get('/seance')->assertRedirect(route('payment.show', $payment));
        $this->actingAs($user->fresh())->postJson('/seance/connexion')->assertForbidden();
        $this->actingAs($user->fresh())->get(route('payment.show', $payment))
            ->assertSee('Nous vérifions votre paiement')
            ->assertDontSee('Vous pouvez commencer');
        $this->assertTrue($payment->fresh()->isPending());

        // Le planificateur l'enregistre aussi.
        $this->artisan('subscriptions:expire')->assertSuccessful();
        $this->assertSame(SubscriptionStatus::Suspended, $subscription->fresh()->status);
        $this->assertTrue($payment->fresh()->isPending());
    }

    public function test_validation_makes_the_access_definitive_without_losing_any_day(): void
    {
        $user = $this->learner();
        $payment = $this->payment($user, 'intensif');
        $subscription = $payment->grantProvisional();
        $startedAt = now()->toImmutable();

        $this->travel(5)->hours();
        $this->actingAs($this->admin)->post(route('admin.payments.approve', $payment))->assertRedirect();

        $subscription->refresh();
        $this->assertSame(PaymentStatus::Validated, $payment->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->provisional_until);
        // La durée compte depuis le début de l'accès provisoire : 30 jours pleins.
        $this->assertTrue($subscription->starts_at->equalTo($startedAt));
        $this->assertTrue($subscription->ends_at->equalTo($startedAt->addDays(30)));
        $this->assertSame(1, $user->subscriptions()->count());

        $this->travel(2)->days();
        $this->assertTrue($user->fresh()->activeSubscription->is($subscription));
    }

    public function test_late_validation_after_the_24_hours_reopens_the_access(): void
    {
        $user = $this->learner();
        $payment = $this->payment($user);
        $subscription = $payment->grantProvisional();

        $this->travel(30)->hours();
        $this->artisan('subscriptions:expire');
        $this->assertNull($user->fresh()->activeSubscription);

        $payment->approve($this->admin);

        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
        $this->assertTrue($user->fresh()->activeSubscription->is($subscription));
        $this->assertTrue($subscription->fresh()->ends_at->equalTo($subscription->starts_at->copy()->addDays(30)));
    }

    public function test_refusal_cuts_the_access_and_the_lesson_in_progress_at_once(): void
    {
        $user = $this->learner();
        $payment = $this->payment($user);
        $subscription = $payment->grantProvisional();
        $this->buildProgram($user);

        $this->actingAs($user->fresh())->postJson('/seance/connexion')->assertOk();
        $lesson = $user->lessons()->sole();
        $this->travel(3)->minutes();

        $this->actingAs($this->admin)
            ->post(route('admin.payments.reject', $payment), ['reason' => ''])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(PaymentStatus::Rejected, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->rejection_reason);
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->fresh()->status);
        $this->assertSame(LessonStatus::Interrupted, $lesson->fresh()->status);
        $this->assertSame(180, $lesson->fresh()->used_seconds);
        $this->assertNull($user->fresh()->activeSubscription);

        // Le navigateur l'apprend au signal suivant ; aucune nouvelle séance.
        $this->actingAs($user->fresh())->postJson('/seance/signal')->assertJson(['active' => false]);
        $this->actingAs($user->fresh())->postJson('/seance/connexion')->assertForbidden();
        $this->actingAs($user->fresh())->get('/seance')->assertRedirect(route('payment.plans'));

        // Message poli, sans motif, avec WhatsApp.
        $this->actingAs($user->fresh())->get(route('payment.show', $payment))
            ->assertSee('Paiement non validé')
            ->assertSee('Écrivez-nous sur WhatsApp')
            ->assertDontSee('Motif :');
        $this->actingAs($user->fresh())->get('/abonnement')
            ->assertSee('Nous n\'avons pas pu valider votre dernier paiement', false)
            ->assertDontSee('Motif :');
    }

    public function test_refusal_reason_is_shown_when_given(): void
    {
        $user = $this->learner();
        $payment = $this->payment($user);

        $this->actingAs($this->admin)
            ->post(route('admin.payments.reject', $payment), ['reason' => 'Le montant reçu est de 4 000 FCFA au lieu de 5 000.'])
            ->assertRedirect();

        $this->actingAs($user->fresh())->get(route('payment.show', $payment))
            ->assertSee('Motif :')
            ->assertSee('Le montant reçu est de 4 000 FCFA au lieu de 5 000.');
        $this->actingAs($user->fresh())->get('/espace')->assertSee('Le montant reçu est de 4 000 FCFA au lieu de 5 000.');
    }

    public function test_a_lesson_in_progress_is_not_cut_when_the_24_hours_end(): void
    {
        $user = $this->learner();
        $payment = $this->payment($user);
        $payment->grantProvisional();
        $this->buildProgram($user);

        // Séance commencée 5 minutes avant la fin de l'accès provisoire.
        $this->travel(24 * 60 - 5)->minutes();
        $this->actingAs($user->fresh())->postJson('/seance/connexion')->assertOk();
        $lesson = $user->lessons()->sole();

        $this->travel(10)->minutes();
        $this->assertNull($user->fresh()->activeSubscription);

        // Elle continue : signal, renouvellement du jeton, page rechargée, bilan.
        $this->actingAs($user->fresh())->postJson('/seance/signal')->assertJson(['active' => true]);
        $this->actingAs($user->fresh())->postJson('/seance/connexion')->assertOk();
        $this->actingAs($user->fresh())->get('/seance')->assertOk();
        $this->assertSame(1, $user->lessons()->count());
        $this->assertTrue($lesson->fresh()->isInProgress());

        // Une fois finie, aucune nouvelle séance.
        $this->actingAs($user->fresh())->postJson('/seance/fin')->assertOk();
        $this->actingAs($user->fresh())->postJson('/seance/connexion')->assertForbidden();
    }

    public function test_only_one_provisional_access_at_a_time(): void
    {
        $user = $this->learner();
        $first = $this->payment($user);
        $this->assertNotNull($first->grantProvisional());
        $this->assertNull($first->grantProvisional());

        // Impossible d'avoir deux paiements en attente ; même hors de ce verrou, pas de second accès.
        $first->subscription->forceFill(['status' => SubscriptionStatus::Provisional])->save();
        $first->forceFill(['status' => PaymentStatus::Validated])->save();
        $second = $this->payment($user, 'semaine');
        $this->assertNull($second->grantProvisional());
        $this->assertSame(1, $user->subscriptions()->where('status', SubscriptionStatus::Provisional)->count());
    }

    public function test_renewal_while_subscribed_starts_at_the_end_of_the_current_one(): void
    {
        $user = $this->learner();
        $current = Subscription::factory()->for($user)->create([
            'starts_at' => now()->subDays(28),
            'ends_at' => now()->addDays(2),
        ]);

        $renewal = $this->payment($user, 'semaine')->grantProvisional();

        $this->assertTrue($renewal->starts_at->equalTo($current->ends_at));
        $this->assertTrue($user->fresh()->activeSubscription->is($current));
    }

    public function test_pending_counter_includes_provisional_accesses(): void
    {
        $this->payment($this->learner())->grantProvisional();
        $this->payment($this->learner());

        $this->actingAs($this->admin)
            ->get('/admin/paiements')
            ->assertSee('Paiements en attente (2)')
            ->assertSee('Accès provisoire')
            ->assertSee('encore 24 heures')
            ->assertSee('<span class="badge badge-alert" title="Paiements en attente">2</span>', false);
    }
}
