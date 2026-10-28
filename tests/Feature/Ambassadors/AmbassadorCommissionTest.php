<?php

namespace Tests\Feature\Ambassadors;

use App\Enums\CommissionStatus;
use App\Models\Ambassador;
use App\Models\AmbassadorCommission;
use App\Models\AmbassadorReferral;
use App\Models\Payment;
use App\Models\User;
use App\Support\Ambassadors\AmbassadorCommissions;
use App\Support\Ambassadors\AmbassadorProgram;
use App\Support\Money;
use App\Support\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Programme d'ambassadeurs, étape 3 : commission de 20 % du PREMIER paiement validé d'un compte rattaché, dans les
 * 90 jours suivant son inscription, créée à la validation ; « Commission payée », annulation avec motif,
 * « Ajouter une commission » à la main ; sommes dans l'espace ambassadeur et l'administration.
 */
class AmbassadorCommissionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.private_disk' => 'local']);
        Storage::fake('local');
        $this->freezeSecond();
        $this->admin = User::factory()->admin()->create();
    }

    private function ambassador(string $name = 'Kofi Mensah', string $phone = '+22890111111'): Ambassador
    {
        return AmbassadorProgram::appoint(User::factory()->create(['name' => $name, 'phone' => $phone]), $this->admin)['ambassador'];
    }

    private function referred(Ambassador $ambassador, string $name = 'Ama Koffi'): User
    {
        $user = User::factory()->create(['name' => $name]);
        AmbassadorReferral::query()->create(['ambassador_id' => $ambassador->id, 'user_id' => $user->id, 'source' => 'link']);

        return $user;
    }

    /** @param array<string, mixed> $attributes */
    private function payment(User $user, string $planKey = 'essentiel', array $attributes = []): Payment
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
            'recipient_number' => '+228 90 00 00 11',
            'proof_path' => 'payment-proofs/test.png',
        ], $attributes));
    }

    private function approve(Payment $payment): void
    {
        $this->actingAs($this->admin)->post(route('admin.payments.approve', $payment), ['confirmed' => 1])->assertSessionHasNoErrors();
    }

    public function test_the_amounts_are_twenty_percent_of_each_plan(): void
    {
        $this->assertSame(400, AmbassadorCommissions::amountFor(Plan::find('semaine')->price));
        $this->assertSame(1500, AmbassadorCommissions::amountFor(Plan::find('essentiel')->price));
        $this->assertSame(2800, AmbassadorCommissions::amountFor(Plan::find('intensif')->price));
    }

    public function test_validating_the_first_payment_creates_the_commission(): void
    {
        $ambassador = $this->ambassador();
        $learner = $this->referred($ambassador);
        $payment = $this->payment($learner, 'intensif');

        $this->approve($payment);

        $commission = AmbassadorCommission::query()->sole();
        $this->assertTrue($commission->ambassador->is($ambassador));
        $this->assertTrue($commission->user->is($learner));
        $this->assertSame($payment->id, $commission->payment_id);
        $this->assertSame('payment', $commission->source);
        $this->assertSame(14000, $commission->base_amount);
        $this->assertSame(2800, $commission->amount);
        $this->assertSame(CommissionStatus::Due, $commission->status);
        $this->assertNull($commission->flags);
    }

    public function test_no_commission_on_a_later_payment_nor_twice(): void
    {
        $ambassador = $this->ambassador();
        $learner = $this->referred($ambassador);
        $this->approve($this->payment($learner, 'semaine'));
        $this->travel(8)->days();

        $this->approve($this->payment($learner, 'essentiel'));

        $commission = AmbassadorCommission::query()->sole();
        $this->assertSame(400, $commission->amount);
    }

    public function test_no_commission_without_referral_or_after_ninety_days(): void
    {
        $ambassador = $this->ambassador();
        $this->approve($this->payment(User::factory()->create()));

        $late = $this->referred($ambassador, 'Yao');
        $late->forceFill(['created_at' => now()->subDays(91)])->save();
        $this->approve($this->payment($late));

        $this->assertSame(0, AmbassadorCommission::query()->count());
    }

    public function test_a_removed_ambassador_earns_no_new_commission_but_keeps_those_already_due(): void
    {
        $ambassador = $this->ambassador();
        $first = $this->referred($ambassador, 'Ama');
        $second = $this->referred($ambassador, 'Yao');
        $this->approve($this->payment($first));

        AmbassadorProgram::remove($ambassador);
        $this->approve($this->payment($second));

        $commission = AmbassadorCommission::query()->sole();
        $this->assertTrue($commission->user->is($first));
        $this->assertSame(CommissionStatus::Due, $commission->status);

        // Toujours payable depuis l'onglet « Ambassadeurs ».
        $this->actingAs($this->admin)->get(route('admin.ambassadors.index'))->assertSee('Commissions à payer : '.Money::format(1500));
    }

    public function test_a_payment_made_from_the_ambassador_number_is_flagged(): void
    {
        $ambassador = $this->ambassador(phone: '+22890111111');
        $learner = $this->referred($ambassador);

        $this->approve($this->payment($learner, attributes: ['analysis' => ['readable' => true, 'sender_number' => '228 90 11 11 11']]));

        $commission = AmbassadorCommission::query()->sole();
        $this->assertSame(['paid_by_ambassador'], $commission->flags);
        $this->actingAs($this->admin)->get(route('admin.ambassadors.index'))
            ->assertSee('À vérifier : Payé depuis le numéro de l&#039;ambassadeur', false);
    }

    public function test_a_hidden_or_other_sender_number_is_not_flagged(): void
    {
        $ambassador = $this->ambassador(phone: '+22890111111');
        $this->approve($this->payment($this->referred($ambassador, 'Ama'), attributes: ['analysis' => ['sender_number' => '9011****']]));
        $this->approve($this->payment($this->referred($ambassador, 'Yao'), attributes: ['analysis' => ['sender_number' => '+228 91 22 33 44']]));

        $this->assertSame(0, AmbassadorCommission::query()->whereNotNull('flags')->count());
    }

    public function test_the_administrator_marks_a_commission_paid_once(): void
    {
        $ambassador = $this->ambassador();
        $this->approve($this->payment($this->referred($ambassador)));
        $commission = AmbassadorCommission::query()->sole();

        $this->actingAs($this->admin)->post(route('admin.commissions.pay', $commission))->assertOk()->assertSee('Commission payée ?');
        $this->assertTrue($commission->fresh()->isDue());

        $this->actingAs($this->admin)->post(route('admin.commissions.pay', $commission), ['confirmed' => 1, 'from' => 'commissions'])
            ->assertRedirect(route('admin.ambassadors.index').'#commissions');

        $commission->refresh();
        $this->assertSame(CommissionStatus::Paid, $commission->status);
        $this->assertNotNull($commission->paid_at);
        $this->assertSame($this->admin->id, $commission->paid_by);

        // Jamais deux fois ; jamais annulée après coup.
        $this->actingAs($this->admin)->post(route('admin.commissions.pay', $commission), ['confirmed' => 1])->assertSessionHasErrors('ambassador');
        $this->actingAs($this->admin)->post(route('admin.commissions.cancel', $commission), ['confirmed' => 1, 'reason' => 'Test'])->assertSessionHasErrors('ambassador');
        $this->actingAs($ambassador->user)->post(route('admin.commissions.pay', $commission), ['confirmed' => 1])->assertForbidden();
    }

    public function test_cancelling_needs_a_reason(): void
    {
        $ambassador = $this->ambassador();
        $this->approve($this->payment($this->referred($ambassador)));
        $commission = AmbassadorCommission::query()->sole();

        $this->actingAs($this->admin)->post(route('admin.commissions.cancel', $commission), ['confirmed' => 1, 'reason' => ''])->assertSessionHasErrors('reason');
        $this->assertTrue($commission->fresh()->isDue());

        $this->actingAs($this->admin)->post(route('admin.commissions.cancel', $commission), ['confirmed' => 1, 'reason' => 'Compte créé par l\'ambassadeur lui-même'])->assertSessionHasNoErrors();
        $this->assertSame(CommissionStatus::Cancelled, $commission->fresh()->status);
        $this->assertSame('Compte créé par l\'ambassadeur lui-même', $commission->fresh()->cancel_reason);
    }

    public function test_the_administrator_adds_a_commission_by_hand_once(): void
    {
        $ambassador = $this->ambassador();
        $learner = $this->referred($ambassador);

        $this->actingAs($this->admin)->get(route('admin.users.show', $learner))->assertSee('Ajouter une commission');
        $this->actingAs($this->admin)->post(route('admin.users.commission', $learner), ['confirmed' => 1, 'plan' => 'semaine', 'note' => 'Paiement par WhatsApp'])->assertSessionHasNoErrors();

        $commission = AmbassadorCommission::query()->sole();
        $this->assertSame('manual', $commission->source);
        $this->assertSame(400, $commission->amount);
        $this->assertSame('Paiement par WhatsApp', $commission->note);
        $this->assertNull($commission->payment_id);

        $this->actingAs($this->admin)->post(route('admin.users.commission', $learner), ['confirmed' => 1, 'plan' => 'intensif'])
            ->assertSessionHasErrors(['ambassador' => 'Une commission existe déjà pour ce compte : une seule commission par personne.']);

        $this->actingAs($this->admin)->post(route('admin.users.commission', User::factory()->create()), ['confirmed' => 1, 'plan' => 'semaine'])
            ->assertSessionHasErrors(['ambassador' => 'Ce compte n\'est rattaché à aucun ambassadeur : rattachez-le d\'abord.']);
    }

    public function test_the_ambassador_sees_paying_subscribers_and_amounts(): void
    {
        $ambassador = $this->ambassador();
        $this->approve($this->payment($this->referred($ambassador, 'Ama Koffi'), 'semaine'));
        $this->approve($this->payment($this->referred($ambassador, 'Yao Mensah'), 'intensif'));
        AmbassadorCommissions::markPaid(AmbassadorCommission::query()->where('amount', 400)->sole(), $this->admin);

        $this->actingAs($ambassador->user)->get(route('ambassador.show'))
            ->assertOk()
            ->assertSee('Vos commissions')
            ->assertSee('Abonnés payants')
            ->assertSeeInOrder(['À recevoir', Money::format(2800), 'Déjà reçu', Money::format(400)])
            ->assertSee('Ama')
            ->assertDontSee('Ama Koffi')
            ->assertSee('payée');

        $this->actingAs($this->admin)->get(route('admin.ambassadors.index'))
            ->assertSee('2 abonnés payants')
            ->assertSee('À payer : '.Money::format(2800))
            ->assertSee('Déjà payé : '.Money::format(400));
    }
}
