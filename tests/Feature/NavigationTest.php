<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Navigation : menu compact (téléphone, tablette), navigation complète (ordinateur),
 * barre du bas de l'apprenant, page courante signalée.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8"?>'.$html);

        return new \DOMXPath($document);
    }

    /**
     * @return list<string>
     */
    private function labels(\DOMXPath $xpath, string $query): array
    {
        $labels = [];

        foreach ($xpath->query($query) as $node) {
            $labels[] = trim(preg_replace('/\s+/', ' ', $node->textContent));
        }

        return $labels;
    }

    public function test_learner_gets_a_compact_menu_a_full_navigation_and_the_bottom_bar(): void
    {
        $xpath = $this->xpath($this->actingAs(User::factory()->create())->get('/espace')->assertOk()->getContent());
        // « Mes cours » est dans le Menu (complément facultatif), jamais dans la barre du bas.
        $expected = ['Mon espace', 'Mon programme', 'Mes séances', 'Mes cours', 'Mon compte', 'Aide', 'Déconnexion'];

        // Menu compact sans JavaScript (<details>) et navigation complète : mêmes liens.
        $this->assertSame(1, $xpath->query('//details[@data-menu]/summary')->length);
        $this->assertSame($expected, $this->labels($xpath, '//nav[contains(@class,"menu-panel")]//a | //nav[contains(@class,"menu-panel")]//button'));
        $this->assertSame($expected, $this->labels($xpath, '//nav[contains(@class,"nav-desktop")]//a | //nav[contains(@class,"nav-desktop")]//button'));

        // Barre du bas et page courante signalée partout.
        $this->assertSame(['Mon espace', 'Programme', 'Séances', 'Aide'], $this->labels($xpath, '//nav[@class="tabbar"]/a'));
        $this->assertSame(['Mon espace', 'Mon espace', 'Mon espace'], $this->labels($xpath, '//a[@aria-current="page"]'));
    }

    public function test_admin_sees_the_administration_link_and_readable_tabs(): void
    {
        $admin = User::factory()->admin()->create();

        $xpath = $this->xpath($this->actingAs($admin)->get('/espace')->getContent());
        $this->assertSame('Administration', $this->labels($xpath, '//nav[contains(@class,"menu-panel")]//a')[0]);

        $xpath = $this->xpath($this->actingAs($admin)->get('/admin/inscrits')->assertOk()->getContent());
        $this->assertCount(10, $this->labels($xpath, '//nav[@class="tabs"]/a'));
        $this->assertContains('Inscrits', $this->labels($xpath, '//nav[@class="tabs"]/a[@aria-current="page"]'));
        // Pas de barre du bas dans l'administration.
        $this->assertSame(0, $xpath->query('//nav[@class="tabbar"]')->length);
    }

    public function test_guests_see_login_and_registration_only(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Connexion')
            ->assertSee('Inscription')
            ->assertDontSee('data-menu', false)
            ->assertDontSee('class="tabbar"', false);
    }
}
