<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accueil plus vivant, sans bibliothèque : cartes qui glissent de côté (alterné), cadre orange décalé sur les photos
 * des publics, « En bref » avant l'appel final ; aucun bouton orange de plus, tout coupé avec « réduire les animations ».
 */
class HomeDynamicTest extends TestCase
{
    use RefreshDatabase;

    public function test_steps_and_audience_cards_slide_in_from_the_side(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertSame(4, substr_count($html, '<li class="card reveal reveal-side"'));
        $this->assertSame(4, substr_count($html, '<section class="students audience reveal reveal-side"'));

        $css = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('.js .reveal.reveal-side { transform: translateX(-22px); }', $css);
        $this->assertStringContainsString('.js .reveal.reveal-side:nth-child(even) { transform: translateX(22px); }', $css);
        $this->assertStringContainsString('.js .reveal.reveal-side, .js .reveal.reveal-side:nth-child(even) { opacity: 1; transform: none; }', $css);
        $this->assertStringContainsString('.audience .photo-frame { box-shadow: 5px 5px 0 var(--c-accent);', $css);
    }

    public function test_in_short_block_comes_before_the_final_call_with_verified_facts_only(): void
    {
        config(['platform.assessment.max_minutes' => 5]);
        $html = html_entity_decode($this->get('/')->assertOk()->getContent(), ENT_QUOTES);

        $block = substr($html, strpos($html, 'class="in-short'));
        $block = substr($block, 0, strpos($block, 'class="cta-band'));
        foreach (['En bref', 'Évaluation orale de 5 minutes', 'Gratuite, une par compte', 'Depuis votre téléphone', 'Votre niveau, de A1 à C2'] as $fact) {
            $this->assertStringContainsString($fact, $block);
        }
        $this->assertStringNotContainsString('btn', $block);
        foreach (['places', 'limité', '100 %', 'garanti'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $block);
        }
    }
}
