<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AccessGrant;
use App\Support\Admin\Subscribers;
use App\Support\Ambassadors\AmbassadorProgram;
use App\Support\Plan;
use App\Support\WhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Page « Abonnés payants » de l'administration : chiffres, liste, filtre, tri par date de fin, relance WhatsApp,
 * ambassadeurs et accès offerts à part.
 */
class SubscribersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /**
     * Apprenant avec une formule payée et validée, qui se termine dans $daysLeft jours.
     */
    private function payer(string $name, string $plan, int $daysLeft, ?string $channel = null): Subscription
    {
        $user = User::factory()->create(['name' => $name, 'phone' => '+2289'.random_int(1000000, 9999999)]);
        $definition = Plan::find($plan);
        $subscription = Subscription::factory()->for($user)->forPlan($plan)->create([
            'starts_at' => now()->subDays($definition->durationDays - $daysLeft),
            'ends_at' => now()->addDays($daysLeft)->addHour(),
        ]);
        $this->paymentFor($subscription, PaymentStatus::Validated, now());

        if ($channel !== null) {
            $user->origin()->create(['channel' => $channel, 'landing_path' => '/', 'first_seen_at' => now()->subMonth()]);
        }

        return $subscription;
    }

    private function paymentFor(Subscription $subscription, PaymentStatus $status, ?\DateTimeInterface $reviewedAt): Payment
    {
        return Payment::query()->forceCreate([
            'user_id' => $subscription->user_id, 'subscription_id' => $subscription->id,
            'plan' => $subscription->plan, 'plan_name' => $subscription->plan_name, 'daily_minutes' => $subscription->daily_minutes,
            'duration_days' => 30, 'amount' => $subscription->price, 'currency' => 'XOF', 'country' => 'TG', 'operator' => 'flooz',
            'operator_name' => 'Flooz', 'recipient_number' => '+22890000000', 'proof_path' => 'proofs/'.$subscription->id.'.jpg',
            'status' => $status, 'reviewed_at' => $reviewedAt, 'reviewed_by' => $reviewedAt ? $this->admin->id : null,
        ]);
    }

    public function test_the_page_counts_paid_subscribers_by_plan_and_the_money_of_the_month(): void
    {
        $this->payer('Kossi Mensah', 'semaine', 5);
        $this->payer('Ama Kpodo', 'essentiel', 20);
        $this->payer('Yao Agbeko', 'essentiel', 10);
        $this->payer('Afi Dossou', 'intensif', 25);
        // Accès provisoire : payé, à vérifier ; compté avec les payants, signalé.
        $provisional = Subscription::factory()->for(User::factory()->create(['name' => 'Komla Adjo']))->forPlan('essentiel')->create([
            'status' => SubscriptionStatus::Provisional, 'provisional_until' => now()->addHours(20),
        ]);
        $this->paymentFor($provisional, PaymentStatus::Pending, null);
        // Terminé : pas dans les actifs.
        Subscription::factory()->for(User::factory()->create(['name' => 'Ancien Abonné']))->expired()->create(['status' => SubscriptionStatus::Expired]);
        // Paiement refusé : jamais un abonné.
        Subscription::factory()->for(User::factory()->create(['name' => 'Paiement Refusé']))->create(['status' => SubscriptionStatus::Cancelled]);

        $this->actingAs($this->admin)->get(route('admin.subscribers.index'))
            ->assertOk()
            ->assertSee('Abonnés payants actifs')
            ->assertSeeInOrder(['Abonnés payants actifs', '5', 'Semaine : 1', 'Essentiel : 3', 'Intensif : 1', 'dont 1 en accès provisoire'])
            // 2 000 + 7 500 + 7 500 + 14 000 validés ce mois-ci (le paiement en vérification ne compte pas).
            ->assertSee("31\u{202F}000\u{00A0}FCFA", false)
            ->assertSee('Kossi')->assertSee('Mensah')
            ->assertSee('en vérification')
            ->assertDontSee('Ancien')
            ->assertDontSee('Refusé');

        $totals = Subscribers::totals();
        $this->assertSame(5, $totals['paid']);
        $this->assertSame(31000, $totals['month_total']);
    }

    public function test_the_list_is_sorted_by_end_date_and_filtered(): void
    {
        $this->payer('Loin Fin', 'essentiel', 25);
        $this->payer('Bientot Fin', 'essentiel', 2);
        $this->payer('Milieu Fin', 'essentiel', 12);
        Subscription::factory()->for(User::factory()->create(['name' => 'Expire Hier']))->expired()->create(['status' => SubscriptionStatus::Expired]);

        $this->actingAs($this->admin)->get(route('admin.subscribers.index'))
            ->assertSeeInOrder(['Bientot', 'Milieu', 'Loin'])
            ->assertDontSee('Expire');

        $this->actingAs($this->admin)->get(route('admin.subscribers.index', ['tri' => 'fin-recente']))
            ->assertSeeInOrder(['Loin', 'Milieu', 'Bientot']);

        $this->actingAs($this->admin)->get(route('admin.subscribers.index', ['statut' => 'expires']))
            ->assertSee('Expire')->assertSee('Terminé')
            ->assertDontSee('Bientot');

        $this->actingAs($this->admin)->get(route('admin.subscribers.index', ['statut' => 'tous']))
            ->assertSee('Expire')->assertSee('Bientot');

        // Valeur inconnue : liste des actifs.
        $this->actingAs($this->admin)->get(route('admin.subscribers.index', ['statut' => 'nimporte', 'tri' => 'x']))
            ->assertOk()->assertSeeInOrder(['Bientot', 'Milieu', 'Loin']);
    }

    public function test_each_line_shows_the_details_and_a_whatsapp_relaunch_when_the_end_is_close(): void
    {
        $soon = $this->payer('Kossi Mensah', 'essentiel', 2, channel: 'whatsapp');
        $later = $this->payer('Ama Kpodo', 'intensif', 20, channel: 'google');

        $response = $this->actingAs($this->admin)->get(route('admin.subscribers.index'))
            ->assertSee('2 jours')
            ->assertSee('WhatsApp')->assertSee('Google')
            ->assertSee($soon->starts_at->format('d/m/Y'))->assertSee($soon->ends_at->format('d/m/Y'))
            ->assertSee("7\u{202F}500\u{00A0}FCFA", false)
            ->assertSee('Relancer sur WhatsApp')
            ->assertSee('Écrire sur WhatsApp');

        // Fin dans moins de 3 jours : relance rédigée (formule, date, où renouveler), sans rien inventer.
        $message = Subscribers::message($soon->fresh());
        $this->assertStringStartsWith('Bonjour Kossi, c\'est l\'équipe de '.config('platform.name').'.', $message);
        $this->assertStringContainsString('Votre formule Essentiel se termine le '.$soon->ends_at->translatedFormat('j F Y'), $message);
        $this->assertStringContainsString(route('payment.plans'), $message);
        $this->assertStringContainsString(config('avatar.model.name'), $message);
        $response->assertSee(e(WhatsApp::url($soon->user->phone, $message)), false);

        // Fin lointaine : simple salutation.
        $this->assertSame('Bonjour Ama, c\'est l\'équipe de '.config('platform.name').'.', Subscribers::message($later->fresh()));
    }

    public function test_an_ended_subscription_gets_a_message_to_come_back(): void
    {
        $user = User::factory()->create(['name' => 'Yao Agbeko']);
        $ended = Subscription::factory()->for($user)->forPlan('semaine')->expired()->create(['status' => SubscriptionStatus::Expired]);

        $message = Subscribers::message($ended);
        $this->assertStringContainsString("Votre formule Semaine s'est terminée le", $message);
        $this->assertStringContainsString(route('payment.plans'), $message);
    }

    public function test_ambassadors_and_offered_accesses_are_kept_apart_from_paying_subscribers(): void
    {
        $this->payer('Payant Vrai', 'essentiel', 15);
        AccessGrant::offer(User::factory()->create(['name' => 'Fondateur Offert']), $this->admin, Plan::find('intensif'), 7, 'Membre fondateur');
        AmbassadorProgram::appoint(User::factory()->create(['name' => 'Kofi Ambassadeur']), $this->admin);

        $this->actingAs($this->admin)->get(route('admin.subscribers.index'))
            ->assertSeeInOrder(['Abonnés payants (1)', 'Payant', 'Ambassadeurs (mois gratuit) (1)', 'Kofi', 'Accès offerts (démonstrations, membres fondateurs…) (1)', 'Fondateur'])
            ->assertSee('Ambassadeurs : 1 · Accès offerts : 1')
            ->assertSee('Gratuit');

        $totals = Subscribers::totals();
        $this->assertSame(1, $totals['paid']);
        $this->assertSame(1, $totals['by_plan']['essentiel']['count']);
        $this->assertSame(0, $totals['by_plan']['intensif']['count']);
        $this->assertSame(1, $totals['ambassador']);
        $this->assertSame(1, $totals['granted']);
    }

    public function test_the_page_is_linked_from_the_admin_menu_and_reserved_to_admins(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.index'))
            ->assertSee(route('admin.subscribers.index'), false)->assertSee('Abonnés payants');

        $this->actingAs(User::factory()->create())->get(route('admin.subscribers.index'))->assertForbidden();
        auth()->logout();
        $this->get(route('admin.subscribers.index'))->assertRedirect(route('login'));
    }
}
