<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit du 1er novembre 2026 : bandeau de phrases qui défile sur l'accueil, questions sur les formules aussi sur
 * « Formules », questionnaire qui descend jusqu'à « Continuer », Club des abonnés plus court.
 */
class SiteAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_has_a_scrolling_band_of_english_phrases_that_respects_reduced_motion(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('class="phrase-ticker"', $html);
        foreach ((array) config('platform.phrase_ticker') as [$english, $french]) {
            $this->assertStringContainsString(e($english), $html);
        }
        // La copie de la boucle est cachée aux lecteurs d'écran ; le bandeau vient après le bouton orange du haut.
        $this->assertSame(1, preg_match_all('/class="ticker-list"\s+aria-hidden="true"/', $html));
        $this->assertLessThan(strpos($html, 'class="phrase-ticker"'), strpos($html, 'hero-cta'));

        $css = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('@keyframes ticker { to { transform: translateX(-50%); } }', $css);
        $this->assertMatchesRegularExpression('/prefers-reduced-motion: reduce\)\s*\{\s*\.ticker-track \{ animation: none; \}/', $css);
    }

    public function test_the_plans_page_answers_which_plan_to_choose(): void
    {
        $this->get('/formules')->assertOk()
            ->assertSee('Quelle formule choisir ?')
            ->assertSee('À quoi sert la formule Semaine ?')
            ->assertSee('Pourquoi la formule Essentiel est-elle recommandée ?')
            ->assertSee('Pour qui est la formule Intensif ?');
    }

    public function test_choosing_a_goal_brings_the_continue_button_into_view(): void
    {
        $this->assertStringContainsString('data-then-submit', file_get_contents(resource_path('views/program/questionnaire.blade.php')));
        $this->assertStringContainsString("'thenSubmit' in choice.dataset", file_get_contents(public_path('js/app.js')));
    }
}
