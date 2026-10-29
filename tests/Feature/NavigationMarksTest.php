<?php

namespace Tests\Feature;

use App\Support\Guides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Repères de navigation des pages publiques (refonte, point 4) : « Vous êtes ici » (fil d'Ariane visible), « Revenir en
 * haut de la page » en bas des pages longues, sommaire cliquable en haut de chaque guide.
 */
class NavigationMarksTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_say_where_you_are_and_lead_back_to_the_top(): void
    {
        foreach (['/formules' => 'Formules', '/a-propos' => 'À propos', '/comment-ca-marche' => 'Comment ça marche',
            '/devenir-ambassadeur' => 'Devenir ambassadeur', '/confidentialite' => 'Confidentialité'] as $uri => $name) {
            $this->get($uri)->assertOk()
                ->assertSee('<span class="sr-only">Vous êtes ici :</span>', false)
                ->assertSee('<span aria-current="page">'.$name.'</span>', false)
                ->assertSee('Revenir en haut de la page')
                ->assertSee('href="#contenu"', false);
        }

        $this->get('/apprendre-anglais/togo')->assertSee('Vous êtes ici')->assertSee('Revenir en haut de la page');
    }

    public function test_short_pages_and_the_home_page_stay_as_they_are(): void
    {
        foreach (['/', '/inscription', '/contact'] as $uri) {
            $this->get($uri)->assertOk()->assertDontSee('Vous êtes ici')->assertDontSee('Revenir en haut de la page');
        }
    }

    public function test_each_guide_starts_with_a_clickable_summary_of_its_parts(): void
    {
        foreach (Guides::slugs() as $slug) {
            $html = $this->get('/guides/'.$slug)->assertOk()->assertSee('Dans ce guide')->assertSee('Revenir en haut de la page')->getContent();

            preg_match('/<nav class="card guide-toc".*?<\/nav>/s', $html, $toc);
            preg_match_all('/href="#([^"]+)"/', $toc[0], $links);
            preg_match_all('/<h2 id="(partie-[^"]+)"/', $html, $parts);

            $this->assertGreaterThan(1, count($links[1]), $slug);
            $this->assertSame($parts[1], $links[1], $slug);
            $this->assertSame(count($links[1]), count(array_unique($links[1])), $slug);
        }
    }
}
