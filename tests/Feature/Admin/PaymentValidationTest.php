<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['payment.proofs.disk' => 'local']);
        Storage::fake('local');
        $this->freezeSecond();
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pendingPayment(User $user, string $planKey = 'essentiel', array $attributes = []): Payment
    {
        $plan = Plan::find($planKey);
        Storage::disk('local')->put('payment-proofs/test.png', 'image');

        return $user->payments()->create(array_merge([
            'plan' => $plan->key,
            'plan_name' => $plan->name,
            'daily_minutes' => $plan->dailyMinutes,
            'duration_days' => $plan->durationDays,
            'amount' => $plan->price,
            'currency' => 'FCFA',
            'country' => 'TG',
            'operator' => 'mixx',
            'operator_name' => 'Mixx by Yas',
            'recipient_number' => '+228 72 24 02 31',
            'payer_phone' => '+228 90 00 00 01',
            'reference' => 'REF-'.$user->id,
            'reference_key' => 'REF'.$user->id,
            'proof_path' => 'payment-proofs/test.png',
        ], $attributes));
    }

    public function test_pending_page_lists_payments_with_counter_for_admins_only(): void
    {
        $learner = User::factory()->create(['name' => 'Ablavi Kodjo']);
        $payment = $this->pendingPayment($learner);

        $this->actingAs($this->admin)
            ->get('/admin/paiements')
            ->assertOk()
            ->assertSee('Paiements en attente (1)')
            ->assertSee('Ablavi Kodjo')
            ->assertSee('REF-'.$learner->id)
            ->assertSee(route('admin.payments.proof', $payment))
            ->assertSee('Valider')
            ->assertSee('Refuser')
            ->assertSee('<span class="badge badge-alert" title="Paiements en attente">1</span>', false);

        $this->actingAs($learner)->get('/admin/paiements')->assertForbidden();
        $this->actingAs($learner)->post(route('admin.payments.approve', $payment))->assertForbidden();
        $this->assertTrue($payment->fresh()->isPending());
    }

    public function test_validation_activates_the_subscription_immediately(): void
    {
        $learner = User::factory()->create();
        $payment = $this->pendingPayment($learner, 'intensif');

        $this->actingAs($this->admin)
            ->post(route('admin.payments.approve', $payment))
            ->assertRedirect()
            ->assertSessionHas('status');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Validated, $payment->status);
        $this->assertSame($this->admin->id, $payment->reviewed_by);
        $this->assertNull($payment->pending_user_id);

        $subscription = $payment->subscription;
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame('Intensif', $subscription->plan_name);
        $this->assertSame(30, $subscription->daily_minutes);
        $this->assertSame(9000, $subscription->price);
        $this->assertTrue($subscription->starts_at->equalTo(now()));
        $this->assertTrue($subscription->ends_at->equalTo(now()->addDays(30)));

        $this->assertTrue($learner->fresh()->activeSubscription->is($subscription));
        $this->assertSame(1800, $learner->fresh()->remainingSecondsToday());

        // Un paiement déjà traité ne peut pas l'être une seconde fois.
        $this->actingAs($this->admin)->post(route('admin.payments.approve', $payment))
            ->assertSessionHas('status', 'Ce paiement a déjà été traité.');
        $this->assertSame(1, $learner->subscriptions()->count());
    }

    public function test_renewal_starts_at_the_end_of_the_current_subscription(): void
    {
        $learner = User::factory()->create();
        $current = Subscription::factory()->for($learner)->create([
            'starts_at' => now()->subDays(28),
            'ends_at' => now()->addDays(2),
        ]);
        $payment = $this->pendingPayment($learner, 'semaine');

        $this->actingAs($this->admin)->post(route('admin.payments.approve', $payment))->assertRedirect();

        $renewal = $payment->fresh()->subscription;
        $this->assertTrue($renewal->starts_at->equalTo($current->ends_at));
        $this->assertTrue($renewal->ends_at->equalTo($current->ends_at->copy()->addDays(7)));

        // L'abonnement en cours reste celui d'aujourd'hui ; le suivant est annoncé.
        $this->assertTrue($learner->fresh()->activeSubscription->is($current));
        $this->actingAs($learner)->get('/espace')->assertSee('Prochaine formule déjà payée : Semaine');

        $this->travel(3)->days();
        $this->assertTrue($learner->fresh()->activeSubscription->is($renewal));
    }

    public function test_rejection_requires_a_reason_shown_to_the_learner_who_can_resend(): void
    {
        $learner = User::factory()->create();
        $payment = $this->pendingPayment($learner, 'essentiel', ['reference' => 'ABC-123', 'reference_key' => 'ABC123']);

        $this->actingAs($this->admin)
            ->from('/admin/paiements')
            ->post(route('admin.payments.reject', $payment), ['reason' => ''])
            ->assertRedirect('/admin/paiements')
            ->assertSessionHasErrors('reason', null, 'reject-'.$payment->id);
        $this->assertTrue($payment->fresh()->isPending());

        $this->actingAs($this->admin)
            ->post(route('admin.payments.reject', $payment), ['reason' => 'Le montant reçu est de 4 000 FCFA au lieu de 5 000.'])
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Rejected, $payment->status);
        $this->assertSame('Le montant reçu est de 4 000 FCFA au lieu de 5 000.', $payment->rejection_reason);
        $this->assertNull($learner->fresh()->activeSubscription);

        $this->actingAs($learner)->get(route('payment.show', $payment))
            ->assertSee('Paiement refusé')
            ->assertSee('Le montant reçu est de 4 000 FCFA au lieu de 5 000.');
        $this->actingAs($learner)->get('/espace')
            ->assertSee('Le montant reçu est de 4 000 FCFA au lieu de 5 000.')
            ->assertSee('Renvoyer un paiement corrigé');

        // Il peut renvoyer un paiement corrigé, même avec la même référence.
        $this->actingAs($learner)->get('/abonnement')->assertOk()->assertSee('Votre dernier paiement a été refusé');
        $this->pendingPayment($learner, 'essentiel', ['reference' => 'ABC-123', 'reference_key' => 'ABC123']);
        $this->assertSame(2, $learner->payments()->count());
    }

    public function test_history_filters_and_month_total(): void
    {
        $togo = User::factory()->create(['name' => 'Apprenant Togo']);
        $benin = User::factory()->create(['name' => 'Apprenant Bénin']);
        $old = User::factory()->create(['name' => 'Apprenant Ancien']);

        $this->pendingPayment($togo, 'intensif')->approve($this->admin);
        $this->pendingPayment($benin, 'essentiel', ['country' => 'BJ', 'operator' => 'mtn', 'operator_name' => 'MTN'])
            ->reject($this->admin, 'Capture illisible.');

        $this->travel(-2)->months();
        $this->pendingPayment($old, 'semaine')->approve($this->admin);
        $this->travelBack();
        $this->freezeSecond();

        $this->actingAs($this->admin)
            ->get('/admin/paiements/historique')
            ->assertOk()
            ->assertSee('Apprenant Togo')
            ->assertSee('Apprenant Bénin')
            ->assertSee('Apprenant Ancien')
            // Seul le paiement validé ce mois-ci compte : 9 000 FCFA.
            ->assertSee("9\u{202F}000\u{00A0}FCFA", false);

        $this->actingAs($this->admin)->get('/admin/paiements/historique?pays=BJ')
            ->assertSee('Apprenant Bénin')->assertDontSee('Apprenant Togo');

        $this->actingAs($this->admin)->get('/admin/paiements/historique?statut=validated')
            ->assertSee('Apprenant Togo')->assertDontSee('Apprenant Bénin');

        $this->actingAs($this->admin)->get('/admin/paiements/historique?du='.now()->subMonths(3)->toDateString().'&au='.now()->subMonth()->toDateString())
            ->assertSee('Apprenant Ancien')->assertDontSee('Apprenant Togo');
    }
}
