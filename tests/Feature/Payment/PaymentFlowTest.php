<?php

namespace Tests\Feature\Payment;

use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentCountry;
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

    public function test_the_country_of_the_account_is_preselected_even_with_a_foreign_number(): void
    {
        $html = $this->page(User::factory()->create(['phone' => '+33612345678', 'country' => 'BJ']));

        $this->assertMatchesRegularExpression('/data-pay-country="BJ"\s+class="btn"\s+aria-current="true"/', $html);
        $this->assertStringContainsString('Choisi d\'après le pays de votre compte.', $html);
    }

    public function test_without_a_payment_country_nothing_is_preselected(): void
    {
        // Compte d'un pays de paiement dont le pays a disparu des moyens proposés (aucun numéro) : rien n'est choisi d'office.
        config(['payment.countries.BJ.operators' => array_map(fn ($operator) => ['number' => ''] + $operator, config('payment.countries.BJ.operators'))]);
        $html = $this->page(User::factory()->create(['phone' => '+22997123456', 'country' => 'BJ']));

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
        $this->assertStringContainsString('Bénin · Celtiis', $block);
        $this->assertStringContainsString('avec Celtiis', $block);
        $this->assertStringContainsString('data-copy="0100000033"', $block);
        $this->assertStringContainsString('name="operator" value="celtiis"', $html);
        // Sans JavaScript, le pays seul mène directement au bloc de paiement.
        $this->assertStringContainsString('pays=BJ#paiement', $html);
        $this->assertStringContainsString('pays=TG#moyen', $html);
    }

    public function test_the_payment_block_shows_three_numbered_steps_in_order(): void
    {
        $html = $this->page(User::factory()->create(), '?pays=TG&moyen=flooz');
        $block = $this->visiblePayment($html);

        // ① le numéro, ② le montant avec le moyen choisi, puis ③ (commun à tous les moyens) le bouton de la capture.
        $positions = array_map(fn (string $text) => mb_strpos($html, $text, mb_strpos($html, 'data-pay-block="TG.flooz"')), [
            'Togo · Flooz', 'Étape 1 sur 3 : </span>Copiez le numéro', '+228 79 16 23 90', 'Au nom de', 'Copier le numéro',
            'Étape 2 sur 3 : </span>Envoyez', "7\u{202F}500\u{00A0}FCFA", 'avec Flooz',
            'Vérifiez le nom affiché, puis validez avec votre code secret. Faites une capture de la confirmation.',
            'Étape 3 sur 3 : </span><span class="pay-when-empty">Revenez ici et touchez ce bouton', 'class="pay-arrow"', 'J\'ai payé, j\'envoie ma capture',
        ]);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'ordre : choix, ① numéro, ② montant, ③ flèche et bouton');

        $this->assertStringContainsString('Copiez le numéro', $block);
        $this->assertStringContainsString('Modifier', $block);
        // Le montant est dit en très gros, dans le titre de l'étape 2.
        $this->assertMatchesRegularExpression('/Envoyez <strong class="pay-big">7\x{202F}500\x{00A0}FCFA<\/strong> <span class="pay-with">avec Flooz<\/span>/u', $block);
        // Un seul bouton orange visible à la fois : « Envoyer ma capture » n'apparaît qu'une fois la capture choisie.
        $this->assertMatchesRegularExpression('/<label for="proof" class="file-pick">\s*<span class="btn btn-accent[^"]*" data-proof-pick>.*J\'ai payé, j\'envoie ma capture/s', $html);
        $this->assertStringContainsString('.js .pay-form:not([data-has-file]) .pay-submit { display: none; }', file_get_contents(public_path('css/app.css')));
        // Sans JavaScript : chaque moyen de paiement recharge la page sur le bloc de paiement.
        $this->assertStringContainsString('moyen=mixx#paiement', $html);
        // Plus de longue liste de consignes ni de promesse d'accès immédiat.
        $this->assertStringNotContainsString('Ouvrez Flooz sur votre téléphone', $html);
        $this->assertStringNotContainsString('accès immédiat', mb_strtolower($html));
    }

    public function test_every_payment_country_and_method_has_the_three_steps(): void
    {
        $this->configurePayments();
        $html = $this->page(User::factory()->create());

        foreach (PaymentCountry::available() as $country) {
            foreach ($country->operators as $method) {
                $start = mb_strpos($html, 'data-pay-block="'.$country->code.'.'.$method->key.'"');
                $this->assertNotFalse($start, $country->name.' · '.$method->name);
                $block = mb_substr($html, $start, mb_strpos($html, 'class="pay-step-text"', $start) - $start);
                $this->assertStringContainsString('Copiez le numéro', $block);
                $this->assertStringContainsString('data-copy="'.$method->localNumber().'"', $block);
                $this->assertStringContainsString('avec '.$method->name, $block);
            }
        }

        // L'étape 3 est commune : une seule flèche, un seul champ de capture.
        $this->assertSame(1, substr_count($html, 'class="pay-arrow"'));
        $this->assertSame(1, substr_count($html, 'id="proof"'));
    }

    public function test_the_arrow_and_the_highlight_stop_when_the_phone_asks_for_less_motion(): void
    {
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: no-preference\) \{\s*\.pay-arrow \.icon \{ animation:/', $css);
        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: no-preference\) \{\s*\.pick-first\.is-highlighted \{ animation:/', $css);
        // Aucune animation de la flèche en dehors de cette condition.
        $this->assertSame(1, preg_match_all('/\.pay-arrow \.icon \{[^}]*animation/', $css));
    }

    public function test_the_install_bar_never_covers_the_payment_button(): void
    {
        $html = $this->page(User::factory()->create(), '?pays=TG&moyen=mixx');

        $this->assertStringNotContainsString('id="install"', $html);
        // Pas de barre ni de bouton fixés en bas pour le paiement : le bouton reste dans la page.
        $this->assertDoesNotMatchRegularExpression('/\.pay[a-z-]*[^{]*\{[^}]*position: (fixed|sticky)/', file_get_contents(public_path('css/app.css')));
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
