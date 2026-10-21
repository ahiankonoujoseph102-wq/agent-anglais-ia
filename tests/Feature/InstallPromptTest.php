<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Proposition d'installer l'application : bandeau sur les pages de l'apprenant (jamais pendant une séance), calendrier
 * de rappel dans public/js/install-reminder.js, lien toujours présent dans le Menu et le pied de page, page d'explications.
 */
class InstallPromptTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    private function subscriber(): User
    {
        $user = User::factory()->level(Level::A1)->create(['welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A1);

        return $user;
    }

    public function test_the_install_page_explains_android_and_iphone_without_javascript(): void
    {
        foreach ([null, User::factory()->create()] as $user) {
            $response = $user ? $this->actingAs($user)->get('/installer-l-application') : $this->get('/installer-l-application');
            $response->assertOk()
                ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
                ->assertSeeText("Installer l'application")
                ->assertSeeText('Installez Talkami pour retrouver Teacher Joe en un geste')
                ->assertSeeText('Sur Android')
                ->assertSeeText("« Installer l'application » ou « Ajouter à l'écran d'accueil »")
                ->assertSeeText('Sur iPhone et iPad')
                ->assertSeeText('Touchez « Partager »')
                ->assertSeeText("Touchez « Sur l'écran d'accueil »")
                // Bouton du navigateur : caché tant que le navigateur ne propose rien.
                ->assertSee('data-install-button data-install-now hidden', false);
        }

        $this->assertNotContains(url('/installer-l-application'), Seo::sitemapUrls());
    }

    public function test_the_install_link_is_always_in_the_menu_and_the_footer(): void
    {
        $learner = $this->actingAs(User::factory()->create())->get('/espace')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<nav class="menu-panel".*?href="'.preg_quote(route('install'), '/').'" data-install-link/s', $learner);
        $this->assertMatchesRegularExpression('/<footer.*?href="'.preg_quote(route('install'), '/').'" data-install-link>Installer l\'application<\/a>/s', $learner);

        // Visiteur : en bas de page aussi, visible même sans JavaScript (plus de bouton caché).
        $this->get('/')->assertSee('href="'.route('install').'" data-install-link>Installer l\'application</a>', false)
            ->assertDontSee('footer-install', false);
    }

    public function test_the_banner_is_on_learner_pages_but_never_during_a_session_nor_in_the_administration(): void
    {
        $user = $this->subscriber();

        foreach (['/', '/espace', '/programme', '/mes-seances', '/mes-cours', '/compte'] as $path) {
            $this->actingAs($user)->get($path)->assertOk()->assertSee('<div class="install" id="install" hidden>', false);
        }

        // Séance avec Teacher Joe, page de bienvenue, page d'installation, administration : jamais.
        $this->actingAs($user)->get('/seance')->assertOk()->assertDontSee('id="install"', false);
        $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->assertDontSee('id="install"', false);
        $this->actingAs($user)->get('/installer-l-application')->assertOk()->assertDontSee('id="install"', false);
        $this->actingAs(User::factory()->admin()->create())->get('/admin/paiements')->assertOk()->assertDontSee('id="install"', false);
    }

    public function test_the_banner_keeps_iphone_help_and_the_page_knows_an_installed_application(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/espace')->assertOk()->getContent();

        $this->assertStringContainsString('Sur iPhone : touchez « Partager »', $html);
        $this->assertStringContainsString('<button type="button" class="install-close" data-install-close title="Fermer">', $html);
        // Calendrier de rappel versionné, message après l'installation (nom tiré de la configuration).
        $this->assertMatchesRegularExpression('/data-install-reminder="[^"]*js\/install-reminder\.js\?v=[0-9a-f]+"/', $html);
        $this->assertStringContainsString("data-installed-message=\"Talkami est installée : retrouvez Teacher Joe sur votre écran d'accueil.\"", $html);
        // Application ouverte depuis l'écran d'accueil : classe posée dès le <head>, liens et invitations cachés.
        $this->assertStringContainsString("document.documentElement.classList.add('is-standalone')", $html);
        $this->assertStringContainsString('.is-standalone [data-install-link], .is-standalone [data-install-link-item], .is-standalone [data-install-hint]', file_get_contents(public_path('css/app.css')));
    }
}
