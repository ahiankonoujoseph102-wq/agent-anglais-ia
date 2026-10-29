<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Refonte, point 5 : fond léger des pages publiques autres que l'accueil. Deux formes floues en CSS, aucune image ni
 * requête en plus, immobiles sur téléphone ; apparitions douces sous le haut de page (jamais sur le bouton du haut).
 */
class PublicPagesDecorTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_other_than_home_get_the_light_background(): void
    {
        foreach (['/formules', '/a-propos', '/comment-ca-marche', '/guides', '/apprendre-anglais/togo', '/contact', '/confidentialite', '/devenir-ambassadeur'] as $url) {
            $this->assertMatchesRegularExpression('/<body[^>]*class="[^"]*\bpage-public\b/', $this->get($url)->assertOk()->getContent(), $url);
        }

        $this->assertDoesNotMatchRegularExpression('/<body[^>]*\bpage-public\b/', $this->get('/')->getContent());
        $this->assertDoesNotMatchRegularExpression('/<body[^>]*\bpage-public\b/', $this->actingAs(User::factory()->create())->get('/espace')->getContent());
    }

    public function test_background_is_css_only_and_still_on_phones(): void
    {
        $css = file_get_contents(public_path('css/app.css'));
        $block = substr($css, strpos($css, 'body.page-public main {'));

        // Aucune image, aucune requête en plus.
        $this->assertStringNotContainsString('url(', $block);
        // L'animation n'existe qu'à partir de 900 pixels et sans « réduire les animations ».
        $animated = substr($block, strpos($block, '@media (min-width: 900px) and (prefers-reduced-motion: no-preference)'));
        $this->assertStringContainsString('animation: drift', $animated);
        $this->assertStringNotContainsString('animation', substr($block, 0, strpos($block, '@media (min-width: 900px) and (prefers-reduced-motion: no-preference)')));
    }

    public function test_top_of_the_page_never_waits_for_an_appearance(): void
    {
        // Le haut de page (titre et bouton orange) n'apparaît jamais en différé.
        $this->assertDoesNotMatchRegularExpression('/class="[^"]*page-hero[^"]*reveal/', $this->get('/devenir-ambassadeur')->getContent());
        $this->assertStringContainsString('section reveal', $this->get('/devenir-ambassadeur')->getContent());
    }
}
