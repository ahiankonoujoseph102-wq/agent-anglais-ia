<?php

namespace Tests\Feature\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmitPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Disque non précisé : le disque par défaut de Laravel est utilisé (bucket sur Laravel Cloud).
        config(['payment.proofs.disk' => null, 'filesystems.default' => 'local']);
        Storage::fake('local');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'country' => 'TG',
            'operator' => 'flooz',
            'payer_phone' => '+228 90 12 34 56',
            'reference' => 'TX-2026-00123',
            'proof' => UploadedFile::fake()->image('capture.png', 600, 1200),
        ], $overrides);
    }

    public function test_plan_choice_lists_plans_from_configuration(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/abonnement')
            ->assertOk()
            ->assertSee('Essentiel')
            ->assertSee('Intensif')
            ->assertSee('Semaine')
            ->assertSee(route('payment.create', 'essentiel'));
    }

    public function test_country_page_shows_operators_numbers_holder_and_exact_amount(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/abonnement/essentiel?pays=TG')
            ->assertOk()
            ->assertSee('Mixx by Yas')
            ->assertSee('+228 72 24 02 31')
            ->assertSee('Flooz')
            ->assertSee('+228 79 16 23 90')
            ->assertSee('Ahiankonou Kokou Joseph')
            ->assertSee("5\u{202F}000\u{00A0}FCFA", false)
            ->assertSee('data-copy="72240231"', false)
            ->assertDontSee('T-Money');

        $this->actingAs($user)
            ->get('/abonnement/essentiel?pays=BJ')
            ->assertSee('KOUAGOU Noutcha Adam Fernando')
            ->assertSee('Celtiis')
            ->assertSee('+229 01 40 28 57 61')
            ->assertSee('data-copy="0140285761"', false);

        $this->actingAs($user)
            ->get('/abonnement/essentiel?pays=BF')
            ->assertSee('Anderson Passoule Oyo')
            ->assertSee('Orange Money')
            ->assertSee('Wave');

        $this->actingAs($user)->get('/abonnement/inconnue')->assertNotFound();
    }

    public function test_learner_submits_a_payment_and_sees_it_pending_with_whatsapp_button(): void
    {
        $user = User::factory()->create(['name' => 'Kossi Amegah', 'phone' => '+22890112233']);

        $response = $this->actingAs($user)->post('/abonnement/intensif', $this->form());

        $payment = Payment::query()->sole();
        $response->assertRedirect(route('payment.show', $payment));

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('intensif', $payment->plan);
        $this->assertSame(9000, $payment->amount);
        $this->assertSame(30, $payment->duration_days);
        $this->assertSame('Flooz', $payment->operator_name);
        $this->assertSame('+228 79 16 23 90', $payment->recipient_number);
        $this->assertSame('TX202600123', $payment->reference_key);
        Storage::disk('local')->assertExists($payment->proof_path);
        $this->assertStringStartsWith('payment-proofs/', $payment->proof_path);

        $page = $this->actingAs($user)->get(route('payment.show', $payment))
            ->assertOk()
            ->assertSee('Paiement en attente de validation')
            ->assertSee('Prévenir sur WhatsApp');

        $message = rawurlencode("Nom : Kossi Amegah\nCompte : +22890112233\nFormule : Intensif\nMontant : 9\u{202F}000\u{00A0}FCFA");
        $this->assertStringContainsString('https://wa.me/22872240231?text=', $page->getContent());
        $this->assertStringContainsString($message, $page->getContent());
        $this->assertStringContainsString(rawurlencode('Référence : TX-2026-00123'), $page->getContent());
    }

    public function test_same_transaction_reference_cannot_be_sent_twice(): void
    {
        $first = User::factory()->create();
        $this->actingAs($first)->post('/abonnement/essentiel', $this->form())->assertRedirect();

        // Même référence, saisie autrement, par un autre apprenant.
        $second = User::factory()->create();
        $this->actingAs($second)
            ->from('/abonnement/essentiel?pays=TG')
            ->post('/abonnement/essentiel', $this->form(['reference' => 'tx 2026 00123']))
            ->assertRedirect('/abonnement/essentiel?pays=TG')
            ->assertSessionHasErrors(['reference_key' => 'Cette référence de transaction a déjà été envoyée.']);

        $this->assertSame(1, Payment::query()->count());
    }

    public function test_a_learner_cannot_have_two_pending_payments(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/abonnement/essentiel', $this->form())->assertRedirect();
        $pending = Payment::query()->sole();

        $this->actingAs($user)
            ->post('/abonnement/semaine', $this->form(['reference' => 'AUTRE-REF-99']))
            ->assertRedirect(route('payment.show', $pending))
            ->assertSessionHas('status');

        $this->assertSame(1, Payment::query()->count());

        // Les pages de paiement renvoient vers le paiement en attente.
        $this->actingAs($user)->get('/abonnement')->assertRedirect(route('payment.show', $pending));
        $this->actingAs($user)->get('/abonnement/semaine?pays=TG')->assertRedirect(route('payment.show', $pending));
    }

    public function test_database_also_forbids_two_pending_payments(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/abonnement/essentiel', $this->form())->assertRedirect();

        $this->expectException(UniqueConstraintViolationException::class);

        $user->payments()->create(Payment::query()->sole()->only([
            'plan', 'plan_name', 'daily_minutes', 'duration_days', 'amount', 'currency',
            'country', 'operator', 'operator_name', 'recipient_number', 'payer_phone', 'proof_path',
        ]) + ['reference' => 'X-1', 'reference_key' => 'X1']);
    }

    public function test_proof_must_be_a_light_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/abonnement/essentiel', $this->form(['proof' => UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf')]))
            ->assertSessionHasErrors(['proof' => 'La capture doit être une image (JPG, PNG ou WebP).']);

        $this->actingAs($user)
            ->post('/abonnement/essentiel', $this->form(['proof' => UploadedFile::fake()->image('enorme.jpg')->size(6000)]))
            ->assertSessionHasErrors(['proof' => 'La capture est trop lourde : 5 Mo au maximum.']);

        $this->actingAs($user)
            ->post('/abonnement/essentiel', $this->form(['operator' => 'wave']))
            ->assertSessionHasErrors('operator');

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_proof_is_private_and_invisible_to_other_learners(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/abonnement/essentiel', $this->form())->assertRedirect();
        $payment = Payment::query()->sole();

        $other = User::factory()->create();

        // Ni la capture, ni la page du paiement d'un autre.
        $this->actingAs($other)->get(route('admin.payments.proof', $payment))->assertForbidden();
        $this->actingAs($other)->get(route('payment.show', $payment))->assertNotFound();
        // Même son propriétaire ne peut pas l'ouvrir : réservée à l'administrateur.
        $this->actingAs($owner)->get(route('admin.payments.proof', $payment))->assertForbidden();
        // Aucune URL publique (le disque privé n'accepte que des liens signés, jamais créés ici).
        $this->assertContains($this->get('/storage/'.$payment->proof_path)->status(), [403, 404]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.payments.proof', $payment))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
