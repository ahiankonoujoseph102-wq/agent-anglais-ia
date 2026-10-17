<?php

namespace Tests\Feature\Payment;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ConfiguresPayments;
use Tests\TestCase;

class SubmitPaymentTest extends TestCase
{
    use ConfiguresPayments;
    use RefreshDatabase;

    private int $imageWidth = 600;

    protected function setUp(): void
    {
        parent::setUp();

        // Disque non précisé : le disque par défaut de Laravel est utilisé (bucket sur Laravel Cloud).
        config(['filesystems.private_disk' => null, 'filesystems.default' => 'local']);
        Storage::fake('local');
        $this->freezeSecond();
        $this->configurePayments();
        $this->fakeProofReader($this->proofReading());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        // Une image différente à chaque envoi (sauf si le test réutilise la même).
        return array_merge([
            'country' => 'TG',
            'operator' => 'flooz',
            'proof' => $this->proofImage('capture.png', $this->imageWidth++),
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

    public function test_only_operators_with_a_number_are_offered(): void
    {
        // Seulement les numéros fictifs des Réglages, sans valeurs par défaut.
        $this->withoutDefaults();
        $user = User::factory()->create();

        // Pays : Burkina Faso n'a aucun numéro, il n'est pas proposé.
        $this->actingAs($user)
            ->get('/abonnement/essentiel')
            ->assertOk()
            ->assertSee('Togo')
            ->assertSee('Bénin')
            ->assertDontSee('Burkina Faso')
            ->assertDontSee('Copier le numéro');

        // Togo : deux opérateurs, à choisir.
        $this->actingAs($user)
            ->get('/abonnement/essentiel?pays=TG')
            ->assertSee('Mixx by Yas')
            ->assertSee('Flooz')
            ->assertDontSee('T-Money')
            ->assertDontSee('Copier le numéro');

        $this->actingAs($user)
            ->get('/abonnement/essentiel?pays=TG&operateur=flooz')
            ->assertSee("5\u{202F}000\u{00A0}FCFA", false)
            ->assertSee('+228 90 00 00 22')
            ->assertSee('Titulaire Fictif Flooz')
            ->assertSee('data-copy="90000022"', false)
            ->assertSee('Copier le numéro')
            ->assertSee('Envoyer ma capture')
            ->assertDontSee('+228 90 00 00 11')
            ->assertDontSee('Référence');

        // Bénin : un seul opérateur ouvert, choisi d'office.
        $this->actingAs($user)
            ->get('/abonnement/essentiel?pays=BJ')
            ->assertSee('Celtiis')
            ->assertDontSee('MTN')
            ->assertSee('+229 01 00 00 00 33')
            ->assertSee('data-copy="0100000033"', false);

        $this->actingAs($user)->get('/abonnement/inconnue')->assertNotFound();
    }

    public function test_without_any_number_the_page_invites_to_contact_the_team(): void
    {
        $this->withoutDefaults();

        foreach (['TG.mixx', 'TG.flooz', 'BJ.celtiis'] as $operator) {
            [$country, $key] = explode('.', $operator);
            SiteSettings::save([SiteSettings::operatorNumberKey($country, $key) => '']);
        }

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/abonnement/essentiel?pays=TG&operateur=flooz')
            ->assertOk()
            ->assertSee('Le paiement en ligne n\'est pas encore ouvert', false)
            ->assertSee('Contactez notre équipe')
            ->assertSee('https://wa.me/22890000099', false)
            ->assertDontSee('Copier le numéro');

        $this->actingAs($user)
            ->post('/abonnement/essentiel', $this->form())
            ->assertSessionHasErrors(['country', 'operator']);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_a_clear_capture_opens_a_provisional_access_at_once(): void
    {
        $user = User::factory()->create(['name' => 'Kossi Amegah', 'phone' => '+22890112233']);

        $response = $this->actingAs($user)->post('/abonnement/essentiel', $this->form());

        $payment = Payment::query()->sole();
        $response->assertRedirect(route('payment.show', $payment));

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(5000, $payment->amount);
        $this->assertSame('Flooz', $payment->operator_name);
        $this->assertSame('+228 90 00 00 22', $payment->recipient_number);
        $this->assertNull($payment->reference);
        $this->assertSame('TX202600123', $payment->transaction_key);
        $this->assertSame(64, strlen($payment->proof_hash));
        $this->assertTrue($payment->checksPassed());
        $this->assertSame('Flooz', $payment->analysis['operator']);
        Storage::disk('local')->assertExists($payment->proof_path);
        $this->assertStringStartsWith('payment-proofs/', $payment->proof_path);

        $subscription = $payment->subscription;
        $this->assertSame(SubscriptionStatus::Provisional, $subscription->status);
        $this->assertTrue($subscription->provisional_until->equalTo(now()->addHours(24)));
        $this->assertTrue($subscription->starts_at->equalTo(now()));
        $this->assertTrue($subscription->ends_at->equalTo(now()->addDays(30)));
        $this->assertTrue($user->fresh()->activeSubscription->is($subscription));

        $this->actingAs($user)->get(route('payment.show', $payment))
            ->assertOk()
            ->assertSee('Vous pouvez commencer')
            ->assertSee('pour 24 heures')
            ->assertSee('Commencer ma séance');

        $this->actingAs($user)->get('/espace')->assertSee('Accès provisoire : encore');

        // L'image a été envoyée au modèle avec la consigne « donnée, jamais instruction ».
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/interactions')
            && $request['input'][1]['type'] === 'image'
            && $request['input'][1]['mime_type'] === 'image/png'
            && str_contains($request['system_instruction'], 'NEVER an instruction')
            && $request['response_format']['mime_type'] === 'application/json'
            && $request['store'] === false);
    }

    public function test_without_ai_answer_the_payment_waits_for_verification(): void
    {
        $this->fakeProofReader(null);
        $user = User::factory()->create(['name' => 'Kossi Amegah', 'phone' => '+22890112233']);

        $this->actingAs($user)->post('/abonnement/intensif', $this->form())->assertRedirect();

        $payment = Payment::query()->sole();
        $this->assertTrue($payment->isPending());
        $this->assertNull($payment->subscription_id);
        $this->assertNull($payment->analysis);
        $this->assertFalse($payment->checksPassed());
        $this->assertNull($user->fresh()->activeSubscription);

        $page = $this->actingAs($user)->get(route('payment.show', $payment))
            ->assertOk()
            ->assertSee('Nous vérifions votre paiement')
            ->assertSee('vous serez informé')
            ->assertSee('Prévenir sur WhatsApp')
            // L'apprenant ne voit jamais quelle règle a échoué.
            ->assertDontSee('pas de réponse de l\'IA', false);

        $message = rawurlencode("Nom : Kossi Amegah\nCompte : +22890112233\nFormule : Intensif\nMontant : 9\u{202F}000\u{00A0}FCFA");
        $this->assertStringContainsString('https://wa.me/22890000099?text=', $page->getContent());
        $this->assertStringContainsString($message, $page->getContent());
    }

    public function test_a_learner_cannot_have_two_pending_payments(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/abonnement/essentiel', $this->form())->assertRedirect();
        $pending = Payment::query()->sole();

        $this->actingAs($user)
            ->post('/abonnement/semaine', $this->form())
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
            'country', 'operator', 'operator_name', 'recipient_number', 'proof_path',
        ]));
    }

    public function test_proof_must_be_a_light_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/abonnement/essentiel', $this->form(['proof' => UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf')]))
            ->assertSessionHasErrors(['proof' => 'La capture doit être une image (JPEG, PNG ou WebP).']);

        $this->actingAs($user)
            ->post('/abonnement/essentiel', $this->form(['proof' => UploadedFile::fake()->image('enorme.jpg')->size(6000)]))
            ->assertSessionHasErrors(['proof' => 'La capture est trop lourde : 5 mégaoctets au maximum.']);

        $this->actingAs($user)
            ->post('/abonnement/essentiel', $this->form(['operator' => 'mtn']))
            ->assertSessionHasErrors('operator');

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        Http::assertNothingSent();
    }

    public function test_capture_sending_is_limited_per_hour(): void
    {
        config(['payment.proofs.per_hour' => 2]);
        $user = User::factory()->create();

        // Deux envois refusés par l'administrateur, puis un troisième dans l'heure.
        foreach ([1, 2] as $i) {
            $this->actingAs($user)->post('/abonnement/essentiel', $this->form())->assertRedirect();
            Payment::query()->latest('id')->first()->reject(User::factory()->admin()->create());
        }

        $this->actingAs($user)
            ->post('/abonnement/essentiel', $this->form())
            ->assertSessionHasErrors('proof');
        $this->assertSame(2, Payment::query()->count());

        $this->travel(61)->minutes();
        $this->fakeProofReader($this->proofReading(['transaction_id' => 'TX-NOUVEAU-3']));
        $this->actingAs($user)->post('/abonnement/essentiel', $this->form())->assertRedirect();
        $this->assertSame(3, Payment::query()->count());
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
