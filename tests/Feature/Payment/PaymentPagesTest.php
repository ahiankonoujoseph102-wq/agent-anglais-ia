<?php

namespace Tests\Feature\Payment;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pages de paiement pensées pour un premier paiement en ligne : étapes claires,
 * bouton de capture en français, aide WhatsApp.
 */
class PaymentPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_plans_page_explains_how_payment_works(): void
    {
        $this->actingAs(User::factory()->create())->get('/abonnement')->assertOk()
            ->assertSee('Choisissez votre formule')
            ->assertSee('sans carte bancaire')
            ->assertSee('Copier le numéro');
    }

    public function test_capture_field_is_a_french_button_with_help_for_a_first_payment(): void
    {
        $this->actingAs(User::factory()->create())->get('/abonnement/essentiel?pays=TG&operateur=flooz')->assertOk()
            ->assertSee('J\'ai payé, j\'envoie ma capture', false)
            ->assertSee('Aucune capture choisie')
            ->assertSee('class="file-pick-input"', false)
            ->assertSee('C\'est votre premier paiement par téléphone ?', false)
            ->assertSee('vérifiez que le nom affiché est bien celui indiqué ci-dessus')
            ->assertSee('class="btn btn-whatsapp btn-block"', false);
    }
}
