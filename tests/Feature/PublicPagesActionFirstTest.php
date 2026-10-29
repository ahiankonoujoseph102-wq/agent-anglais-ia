<?php

namespace Tests\Feature;

use App\Models\Ambassador;
use App\Models\User;
use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Refonte, étape 4, point 1 : le bouton principal est visible sans défiler à 360 × 640 sur les pages publiques
 * longues (« À propos », « Comment ça marche », « Devenir ambassadeur », guides, pages pays) et le formulaire
 * d'inscription est plus court (pays replié sur le Togo, e-mail et code ambassadeur repliés). Mesures dans Chromium :
 * inscription 1 016 → 566, « À propos » 3 922 → 253, guides 3 228 à 4 981 → 278 à 490, ambassadeur 2 335 → 485,
 * Bénin 565 → 440 (haut du bouton, en pixels).
 */
class PublicPagesActionFirstTest extends TestCase
{
    use RefreshDatabase;

    private const BUTTON = 'Passer mon évaluation gratuite';

    /** Le bouton orange de l'évaluation arrive avant le premier titre de section (h2) de la page. */
    private function assertButtonBeforeContent(string $url): void
    {
        $html = $this->get($url)->assertOk()->getContent();
        $main = substr($html, strpos($html, '<main'));

        $this->assertNotFalse(strpos($main, 'btn btn-accent btn-lg'), $url);
        $this->assertLessThan(strpos($main, '<h2'), strpos($main, self::BUTTON), 'Bouton trop bas : '.$url);
    }

    public function test_long_public_pages_show_the_assessment_button_before_their_content(): void
    {
        foreach (['/a-propos', '/comment-ca-marche', '/guides'] as $url) {
            $this->assertButtonBeforeContent($url);
        }

        foreach (Guides::slugs() as $slug) {
            $this->assertButtonBeforeContent('/guides/'.$slug);
        }

        // Pages pays : déjà dans l'accroche ; la réassurance de durée vient de la configuration.
        $this->assertButtonBeforeContent('/apprendre-anglais/benin');
        $this->get('/a-propos')->assertSee('Évaluation orale gratuite de '.config('platform.assessment.max_minutes').' minutes : votre niveau d\'anglais, de A1 à C2.', false);
    }

    public function test_a_visitor_goes_to_the_registration_from_the_top_button(): void
    {
        $this->get('/guides')->assertSee('href="'.route('register').'"', false);
    }

    public function test_the_ambassador_page_offers_its_action_in_the_first_screen(): void
    {
        $html = $this->get('/devenir-ambassadeur')->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'id="ambassadeur-recoit"'), strpos($html, 'href="#candidature"'));
        $this->assertStringContainsString('Poser ma candidature', $html);

        // Toutes les places prises : être prévenu.
        config(['platform.ambassadors.places' => 0]);
        $this->get('/devenir-ambassadeur')->assertSee('Être prévenu quand une place se libère');

        // Un ambassadeur va à son espace.
        $user = User::factory()->create();
        Ambassador::query()->forceCreate(['user_id' => $user->id, 'code' => 'AFI27', 'status' => 'active']);
        config(['platform.ambassadors.places' => 10]);
        $this->actingAs($user)->get('/devenir-ambassadeur')->assertSee('Ouvrir mon espace ambassadeur');
    }

    public function test_the_registration_form_is_short_and_keeps_every_field(): void
    {
        $html = $this->get('/inscription')->assertOk()->getContent();

        // Pays replié (Togo choisi d'avance), e-mail et code ambassadeur repliés ensemble ; tous les champs restent envoyés.
        $this->assertStringContainsString('<details class="optional-field country-field" >', $html);
        $this->assertStringContainsString('Pas au Togo ? Choisir votre pays', $html);
        $this->assertStringContainsString('<option value="TG" selected', $html);
        $this->assertStringContainsString('Facultatif : e-mail, code ambassadeur', $html);
        foreach (['name="name"', 'name="country"', 'name="phone"', 'name="password"', 'name="email"', 'name="ambassador_code"'] as $field) {
            $this->assertStringContainsString($field, $html);
        }
        // Les champs obligatoires passent avant les champs repliés, et le bouton juste après.
        $this->assertLessThan(strpos($html, 'name="email"'), strpos($html, 'name="password"'));
        $this->assertLessThan(strpos($html, 'Créer mon compte</button>'), strpos($html, 'name="ambassador_code"'));
    }

    public function test_another_country_or_an_error_opens_the_folded_fields(): void
    {
        $this->post('/inscription', ['name' => 'Kofi', 'country' => 'CM', 'phone' => '', 'password' => 'x', 'email' => 'pas-une-adresse'])
            ->assertSessionHasErrors();

        $html = $this->get('/inscription')->getContent();
        $this->assertStringContainsString('<details class="optional-field country-field"  open', $html);
        $this->assertStringContainsString('<details class="optional-field ambassador-field"  open', $html);
        $this->assertStringContainsString('<option value="CM" selected', $html);
    }

    public function test_registration_still_works_with_the_folded_country(): void
    {
        $this->post('/inscription', ['name' => 'Afi Mensah', 'country' => 'TG', 'phone' => '90 12 34 56', 'password' => 'mot-de-passe-solide'])
            ->assertRedirect(route('dashboard'));

        $this->assertSame('+22890123456', User::firstOrFail()->phone);
    }
}
