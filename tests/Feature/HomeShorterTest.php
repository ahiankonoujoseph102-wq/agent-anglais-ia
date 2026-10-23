<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accueil plus court : un bouton orange là où il compte, prix et moyens de paiement une seule fois, cartes des
 * publics et questions fréquentes repliées, sans rien perdre des informations ni des données structurées.
 */
class HomeShorterTest extends TestCase
{
    use RefreshDatabase;

    /** Contenu visible de la page (sans les données structurées ni les scripts). */
    private function visible(): string
    {
        $html = $this->get('/')->assertOk()->getContent();
        $main = substr($html, strpos($html, '<main'), strpos($html, '</main>') - strpos($html, '<main'));

        return preg_replace('#<script\b.*?</script>#s', '', $main);
    }

    public function test_orange_buttons_only_at_the_top_on_the_plans_and_at_the_end(): void
    {
        $main = $this->visible();

        // Premier écran, une par formule, appel final.
        $this->assertSame(1 + count(Plan::all()) + 1, substr_count($main, 'btn-accent'));
    }

    public function test_prices_and_payment_methods_appear_once(): void
    {
        $main = $this->visible();

        foreach (Plan::all() as $plan) {
            $this->assertSame(1, substr_count($main, '>'.$plan->formattedPrice().'<'), $plan->name);
        }

        foreach (['Mixx by Yas', 'Flooz', 'Celtiis', 'MTN Mobile Money'] as $operator) {
            $this->assertSame(1, substr_count($main, '>'.$operator.'<'), $operator);
        }

        // Chaque pays mène à sa page, depuis la liste des moyens de paiement.
        foreach (['togo', 'benin', 'burkina-faso', 'cote-d-ivoire'] as $slug) {
            $this->assertStringContainsString('class="pay-country" href="'.route('country', $slug).'"', $main);
        }
    }

    public function test_plan_cards_are_compact_on_the_home_page_and_full_under_the_verdict(): void
    {
        $main = $this->visible();
        $this->assertSame(count(Plan::all()), substr_count($main, 'plan-compact'));
        $this->assertStringContainsString('<summary>Ce qui est compris</summary>', $main);
        $this->assertStringContainsString('15 minutes par jour · 7 jours', $main);

        // Sous le verdict de l'évaluation, la carte complète ne change pas.
        $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->assertDontSee('plan-compact')->assertSee('Valable 7 jours');
    }

    public function test_six_questions_first_the_others_folded_and_all_in_the_structured_data(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $faq = substr($html, strpos($html, '<div class="faq reveal">'));
        $faq = substr($faq, 0, strpos($faq, 'Voir les autres questions'));
        $this->assertSame(6, substr_count($faq, '<details>'));

        preg_match('#<script type="application/ld\+json">(\{"@context":"https://schema.org","@type":"FAQPage".*?)</script>#s', $html, $json);
        $data = json_decode($json[1], true);
        $this->assertCount(15, $data['mainEntity']);
        $this->assertNotContains('Voir les autres questions', array_column($data['mainEntity'], 'name'));
    }

    public function test_footer_shows_one_phone_number_per_line(): void
    {
        config(['platform.contact.phones' => ['+228 72 24 02 31', '+228 79 74 21 27']]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<span class="footer-phones">\s*<a href="tel:\+22872240231">\+228 72 24 02 31</a>\s*<a href="tel:\+22879742127">\+228 79 74 21 27</a>\s*</span>#', $html);
        $this->assertStringContainsString('.footer-phones { display: grid; }', file_get_contents(public_path('css/app.css')));
    }

    public function test_swiped_audience_cards_keep_their_own_height_and_the_recording_line_is_short(): void
    {
        $css = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString("@media (max-width: 719.98px) {\n    .audiences > .audience { align-self: start; }", $css);

        $line = html_entity_decode($this->get('/')->getContent(), ENT_QUOTES);
        $line = substr($line, strpos($line, '<li id="enregistrer">'));
        $line = strip_tags(substr($line, 0, strpos($line, '</li>')));
        $this->assertLessThan(200, mb_strlen(trim(preg_replace('/\s+/', ' ', $line))));
    }
}
