<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accueil, cartes des publics sur téléphone : elles défilent toutes seules (public/js/audience-carousel.js), quatre
 * onglets montrent la carte en cours et un bouton sous les cartes suit la carte affichée. Sans JavaScript : la phrase
 * « faites glisser » et le bouton de chaque carte restent.
 */
class AudienceCarouselTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_has_the_carousel_its_tabs_and_the_following_button(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-audience-carousel', $html);
        $this->assertStringContainsString('js/audience-carousel.js?v=', $html);

        // Un onglet par carte, vers son titre (sans JavaScript, l'ancre mène à la carte).
        foreach (['etudiants' => 'Étudiant', 'eleves' => 'Élève', 'parents' => 'Parent', 'pros' => 'Pro'] as $id => $label) {
            $this->assertMatchesRegularExpression('#<a class="audience-tab" href="\#'.$id.'" data-audience-tab>'.$label.'<#u', $html);
            $this->assertStringContainsString('id="'.$id.'"', $html);
        }

        // Le bouton qui suit : plein (bleu nuit), jamais un bouton orange de plus ; au départ, celui de la première carte.
        $this->assertMatchesRegularExpression('#<a class="btn btn-lg btn-block audience-follow" href="[^"]*/inscription"#', $html);
        $this->assertStringNotContainsString('btn-accent audience-follow', $html);

        // Sans JavaScript : la phrase et le bouton de chaque carte restent dans la page.
        $this->assertStringContainsString('faites glisser pour voir chaque profil', $html);
        $this->assertSame(4, substr_count($html, 'audience-cta"'));
    }

    public function test_the_following_button_follows_the_first_card_for_a_learner(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#audience-follow" href="[^"]*/evaluation"#', $html);
    }

    public function test_the_script_stops_on_touch_and_respects_reduced_motion(): void
    {
        $js = file_get_contents(public_path('js/audience-carousel.js'));
        $css = file_get_contents(public_path('css/app.css'));

        foreach (['pointerdown', 'touchstart', 'wheel', 'keydown', 'focusin'] as $event) {
            $this->assertStringContainsString("'".$event."'", $js);
        }

        $this->assertStringContainsString('prefers-reduced-motion: reduce', $js);
        $this->assertStringContainsString('visibilitychange', $js);
        $this->assertStringContainsString('IntersectionObserver', $js);

        // Seulement transform et opacity pour la barre et le fondu du bouton.
        $this->assertMatchesRegularExpression('#@keyframes audience-fill \{ to \{ transform: scaleX\(1\); \} \}#', $css);
        $this->assertStringContainsString('.audience-tab .audience-tab-bar { animation: none !important; }', $css);
    }
}
