<?php

namespace Tests\Feature\Payment;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ConfiguresPayments;
use Tests\TestCase;

/**
 * Paiement le plus simple possible : pays choisi d'avance d'après l'indicatif du téléphone,
 * moyen de paiement choisi d'office s'il est seul, mots compris de tous (« moyen de paiement »).
 */
class PaymentFlowTest extends TestCase
{
    use ConfiguresPayments;
    use RefreshDatabase;

    private function page(User $user, string $query = ''): string
    {
        return $this->actingAs($user)->get('/abonnement/essentiel'.$query)->assertOk()->getContent();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function dialCodes(): array
    {
        return [
            'Togo' => ['+22890123456', 'TG'],
            'Burkina Faso' => ['+22670123456', 'BF'],
            'Bénin' => ['+2290190123456', 'BJ'],
            'Côte d\'Ivoire' => ['+2250709123456', 'CI'],
        ];
    }

    #[DataProvider('dialCodes')]
    public function test_the_country_is_chosen_from_the_phone_dial_code(string $phone, string $code): void
    {
        $html = $this->page(User::factory()->create(['phone' => $phone]));

        // Le pays est marqué, ses moyens de paiement sont visibles tout de suite.
        $this->assertMatchesRegularExpression('/data-pay-country="'.$code.'"\s+class="btn"\s+aria-current="true"/', $html);
        $this->assertMatchesRegularExpression('/data-pay-methods-for="'.$code.'"\s*>/', $html);
        $this->assertStringContainsString('<section class="card pay-section" id="moyen" data-pay-methods >', $html);
        $this->assertStringContainsString('Choisi d\'après votre numéro de téléphone', $html);
        $this->assertStringContainsString('Par quel moyen voulez-vous payer ?', $html);
    }

    public function test_an_unknown_dial_code_preselects_nothing(): void
    {
        $html = $this->page(User::factory()->create(['phone' => '+33612345678']));

        $this->assertStringNotContainsString('aria-current="true"', $html);
        $this->assertStringContainsString('id="moyen" data-pay-methods  hidden', $html);
        $this->assertStringNotContainsString('Choisi d\'après votre numéro de téléphone', $html);
        $this->assertSame('', $this->visiblePayment($html));
    }

    public function test_an_explicit_choice_wins_over_the_phone(): void
    {
        $html = $this->page(User::factory()->create(['phone' => '+22890123456']), '?pays=CI');

        $this->assertMatchesRegularExpression('/data-pay-country="CI"\s+class="btn"\s+aria-current="true"/', $html);
        $this->assertStringNotContainsString('Choisi d\'après votre numéro de téléphone', $html);
    }

    public function test_a_single_payment_method_is_chosen_automatically(): void
    {
        // Sans valeurs par défaut : au Bénin, seul Celtiis a un numéro (Réglages fictifs).
        $this->withoutDefaults();
        $this->configurePayments();
        $html = $this->page(User::factory()->create(['phone' => '+2290190123456']));

        $block = $this->visiblePayment($html);
        $this->assertStringContainsString('Payez avec Celtiis', $block);
        $this->assertStringContainsString('data-copy="0100000033"', $block);
        $this->assertStringContainsString('name="operator" value="celtiis"', $html);
        // Sans JavaScript, le pays seul mène directement au bloc de paiement.
        $this->assertStringContainsString('pays=BJ#paiement', $html);
        $this->assertStringContainsString('pays=TG#moyen', $html);
    }

    public function test_the_payment_block_shows_amount_number_steps_and_capture_button_in_order(): void
    {
        $html = $this->page(User::factory()->create(), '?pays=TG&moyen=flooz');
        $block = $this->visiblePayment($html);

        $positions = array_map(fn (string $text) => mb_strpos($block, $text), [
            'Togo · Flooz', 'Montant exact à envoyer', 'Numéro sur lequel envoyer l\'argent', 'Copier le numéro', 'Ouvrez Flooz',
        ]);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'ordre : choix, montant, numéro, copier, étapes');

        $this->assertMatchesRegularExpression('/<label for="proof" class="file-pick">\s*<span class="btn btn-accent[^"]*">.*J\'ai payé, j\'envoie ma capture/s', $html);
        $this->assertStringContainsString('Modifier', $block);
        // Sans JavaScript : chaque moyen de paiement recharge la page sur le bloc de paiement.
        $this->assertStringContainsString('moyen=mixx#paiement', $html);
    }

    public function test_learner_pages_never_say_operateur(): void
    {
        $user = User::factory()->create();
        $pages = [
            $this->get('/')->getContent(),
            $this->page($user),
            $this->page($user, '?pays=TG'),
            $this->page($user, '?pays=CI&moyen=wave'),
            $this->actingAs($user)->get('/abonnement')->getContent(),
            $this->actingAs($user)->get('/espace')->getContent(),
            $this->actingAs($user)->from('/abonnement/essentiel?pays=TG')->post('/abonnement/essentiel', ['country' => 'TG'])->assertSessionHasErrors('operator')->baseResponse->getSession()->get('errors')->first('operator'),
        ];

        $payment = $user->payments()->create([
            'plan' => 'essentiel', 'plan_name' => 'Essentiel', 'daily_minutes' => 15, 'duration_days' => 30,
            'amount' => 5000, 'currency' => 'FCFA', 'country' => 'TG', 'operator' => 'flooz', 'operator_name' => 'Flooz',
            'recipient_number' => '+228 90 00 00 22', 'proof_path' => 'payment-proofs/essai.png',
        ]);
        $pages[] = $this->actingAs($user)->get(route('payment.show', $payment))->getContent();

        foreach ($pages as $html) {
            $text = urldecode(html_entity_decode(strip_tags($html)));
            $this->assertDoesNotMatchRegularExpression('/op[ée]rateur/iu', $text);
            $this->assertDoesNotMatchRegularExpression('/op[ée]rateur/iu', urldecode($html), 'aussi dans les liens (WhatsApp)');
        }

        $this->assertStringContainsString('Pays et moyen de paiement', $pages[1]);
        $this->assertInstanceOf(Payment::class, $payment);
    }
}
