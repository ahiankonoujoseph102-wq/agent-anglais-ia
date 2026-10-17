<?php

namespace Tests\Feature\Payment;

use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentCountry;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ConfiguresPayments;
use Tests\TestCase;

/**
 * Dès le déploiement, sans rien saisir dans les Réglages : les numéros et titulaires
 * par défaut de config/payment.php sont proposés. Les Réglages les remplacent ; un
 * champ vidé revient à la valeur par défaut.
 */
class DefaultPaymentMethodsTest extends TestCase
{
    use ConfiguresPayments;
    use RefreshDatabase;

    public function test_every_operator_with_a_default_number_is_offered_without_any_setting(): void
    {
        $user = User::factory()->create();
        $offered = [];

        $this->actingAs($user)->get('/abonnement/essentiel')
            ->assertOk()
            ->assertSee('Togo')
            ->assertSee('Burkina Faso')
            ->assertSee('Bénin')
            ->assertSee('Côte d&#039;Ivoire', false)
            ->assertDontSee('pas encore ouvert');

        foreach (config('payment.countries') as $code => $country) {
            foreach ($country['operators'] as $key => $operator) {
                $this->assertNotEmpty($operator['number'], "Numéro par défaut manquant : {$operator['name']}");
                $displayed = PaymentCountry::find($code)->operator($key);

                $this->actingAs($user)->get("/abonnement/essentiel?pays={$code}&operateur={$key}")
                    ->assertOk()
                    ->assertSee($operator['name'])
                    ->assertSee($displayed->displayNumber())
                    ->assertSee($operator['holder'])
                    ->assertSee('data-copy="'.$displayed->localNumber().'"', false)
                    ->assertSee('Envoyer ma capture');

                $offered[] = $operator['name'];
            }
        }

        $this->assertSame(['Mixx by Yas', 'Flooz', 'Orange Money', 'Wave', 'MTN', 'Moov', 'Celtiis', 'Orange Money', 'MTN Mobile Money', 'Wave'], $offered);
        $this->assertCount(4, PaymentCountry::available());
    }

    public function test_a_setting_replaces_the_default_and_an_empty_field_restores_it(): void
    {
        $user = User::factory()->create();
        $default = PaymentCountry::find('TG')->operator('flooz');

        SiteSettings::save([
            SiteSettings::operatorNumberKey('TG', 'flooz') => '+228 90 00 00 22',
            SiteSettings::operatorHolderKey('TG', 'flooz') => 'Titulaire Fictif Flooz',
        ]);

        $this->actingAs($user)->get('/abonnement/essentiel?pays=TG&operateur=flooz')
            ->assertSee('+228 90 00 00 22')
            ->assertSee('Titulaire Fictif Flooz')
            // Le numéro par défaut peut aussi être un téléphone du pied de page : on vise le bouton « Copier ».
            ->assertSee('data-copy="90000022"', false)
            ->assertDontSee('data-copy="'.$default->localNumber().'"', false)
            ->assertDontSee($default->holder);

        SiteSettings::save([
            SiteSettings::operatorNumberKey('TG', 'flooz') => '',
            SiteSettings::operatorHolderKey('TG', 'flooz') => '',
        ]);

        $this->actingAs($user)->get('/abonnement/essentiel?pays=TG&operateur=flooz')
            ->assertSee($default->displayNumber())
            ->assertSee('data-copy="'.$default->localNumber().'"', false)
            ->assertSee($default->holder)
            ->assertDontSee('+228 90 00 00 22');
    }

    public function test_a_capture_paid_to_the_default_number_opens_the_provisional_access(): void
    {
        config(['filesystems.private_disk' => 'local']);
        Storage::fake('local');
        $this->freezeSecond();
        $local = PaymentCountry::find('TG')->operator('flooz')->localNumber();
        $this->fakeProofReader($this->proofReading(['recipient_number' => $local]));

        $user = User::factory()->create();
        $this->actingAs($user)->post('/abonnement/essentiel', [
            'country' => 'TG',
            'operator' => 'flooz',
            'proof' => $this->proofImage(),
        ])->assertRedirect();

        $payment = Payment::query()->sole();
        $this->assertTrue($payment->checksPassed());
        $this->assertSame(PaymentCountry::find('TG')->operator('flooz')->displayNumber(), $payment->recipient_number);
        $this->assertNotNull($user->fresh()->activeSubscription);
    }
}
