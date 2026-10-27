<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Models\Setting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\Videos\VideoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Changement de domaine : talkami.net est l'adresse officielle (APP_URL), l'ancienne adresse
 * talkami.laravel.cloud redirige ses pages en 301 vers la même page, sans jamais gêner les envois,
 * l'API des workflows GitHub, les adresses signées ni l'application installée.
 */
class DomainChangeTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'https://talkami.laravel.cloud';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://talkami.net']);
    }

    public function test_the_old_address_is_redirected_by_default(): void
    {
        $this->assertContains('talkami.laravel.cloud', config('app.redirect_from_hosts'));
    }

    public function test_every_absolute_address_of_a_public_page_uses_talkami_net(): void
    {
        foreach (['/', '/formules', '/a-propos', '/apprendre-anglais/togo', '/contact'] as $path) {
            $html = $this->get('https://talkami.net'.$path)->assertOk()->getContent();

            $this->assertStringContainsString('<link rel="canonical" href="https://talkami.net', $html, $path);
            $this->assertMatchesRegularExpression('#property="og:url" content="https://talkami\.net#', $html, $path);
            $this->assertMatchesRegularExpression('#property="og:image" content="https://talkami\.net/#', $html, $path);
            $this->assertMatchesRegularExpression('#name="twitter:image" content="https://talkami\.net/#', $html, $path);
            $this->assertStringNotContainsString('laravel.cloud', $html, $path);
        }
    }

    public function test_the_sitemap_robots_and_manifest_use_talkami_net(): void
    {
        $sitemap = $this->get('https://talkami.net/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>https://talkami.net/</loc>', $sitemap);
        $this->assertStringNotContainsString('laravel.cloud', $sitemap);

        config(['app.env' => 'production']);
        $this->app['env'] = 'production';
        $robots = $this->get('https://talkami.net/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Sitemap: https://talkami.net/sitemap.xml', $robots);

        $this->assertStringNotContainsString('laravel.cloud', $this->get('https://talkami.net/manifest.webmanifest')->assertOk()->getContent());
    }

    public function test_old_links_already_shared_open_the_same_page_on_talkami_net(): void
    {
        $user = User::factory()->create();
        $attestation = $user->attestations()->create(['level' => Level::A1, 'code' => 'TA-A1-ABCD-EFGH', 'issued_at' => now()->subMonth()]);

        $links = [
            // Lien partagé sur WhatsApp ou TikTok, avec ses paramètres.
            '/?utm_source=whatsapp&utm_medium=partage' => '/?utm_source=whatsapp&utm_medium=partage',
            '/comment-ca-marche#questions' => '/comment-ca-marche',
            '/apprendre-anglais/benin?source=tiktok' => '/apprendre-anglais/benin?source=tiktok',
            // Attestation déjà délivrée : son PDF imprime l'ancienne adresse de vérification.
            '/attestation/verifier/'.$attestation->code => '/attestation/verifier/'.$attestation->code,
            // Liens des e-mails déjà envoyés (document de révision, invitation, alerte de paiement).
            '/mes-cours' => '/mes-cours',
            '/espace' => '/espace',
            '/admin/paiements' => '/admin/paiements',
        ];

        foreach ($links as $old => $new) {
            $this->get(self::OLD.$old)->assertStatus(301)->assertRedirect('https://talkami.net'.$new);
            $this->call('HEAD', self::OLD.$old)->assertStatus(301)->assertRedirect('https://talkami.net'.$new);
        }

        // Et la page d'arrivée s'ouvre.
        $this->get('https://talkami.net/attestation/verifier/'.$attestation->code)->assertOk()->assertSee('A1');
    }

    public function test_automatic_services_and_submissions_keep_working_on_the_old_address(): void
    {
        // API des workflows GitHub (vidéos de leçon, présentations, vidéos du site, catalogue) : jamais redirigée.
        config(['videos.upload_token' => 'jeton-de-test']);
        foreach (['/api/videos/travaux', '/api/presentations/travaux', '/api/presentations/site/travaux', '/api/catalogue/travail'] as $path) {
            $this->assertNotSame(301, $this->get(self::OLD.$path)->getStatusCode(), $path);
            $this->assertNotSame(301, $this->withToken('jeton-de-test')->get(self::OLD.$path)->getStatusCode(), $path);
        }
        $this->assertNotSame(301, $this->put(self::OLD.'/api/catalogue/fichier/video')->getStatusCode());

        // Envois : jamais redirigés.
        $this->assertNotSame(301, $this->post(self::OLD.'/connexion', [])->getStatusCode());
        $this->assertNotSame(301, $this->put(self::OLD.'/compte', [])->getStatusCode());
        $this->assertNotSame(301, $this->delete(self::OLD.'/admin/inscrits/1/evaluation')->getStatusCode());
        $this->assertNotSame(301, $this->patch(self::OLD.'/compte', [])->getStatusCode());

        // Contrôle de santé, application installée (service worker, manifeste, page hors connexion).
        $this->get(self::OLD.'/up')->assertOk();
        $this->get(self::OLD.'/manifest.webmanifest')->assertOk();
        $this->get(self::OLD.'/hors-connexion')->assertOk();
        $this->assertNotSame(301, $this->get(self::OLD.'/sw.js')->getStatusCode());

        // Adresse signée (téléchargement, vidéo) : sa signature porte sur l'adresse d'origine.
        $this->assertNotSame(301, $this->get(self::OLD.'/comment-ca-marche/talkami/video?expires=1&signature=abc')->getStatusCode());
    }

    public function test_nothing_is_redirected_while_app_url_is_still_the_old_address(): void
    {
        config(['app.url' => self::OLD]);

        $this->get(self::OLD.'/formules')->assertOk();
    }

    public function test_in_production_every_generated_link_uses_app_url_even_from_the_old_address(): void
    {
        $this->app['env'] = 'production';
        config(['app.force_https' => true]);
        (new AppServiceProvider($this->app))->boot();

        // Lien imprimé sur le PDF d'une attestation, liens des e-mails : toujours talkami.net.
        $this->assertSame('https://talkami.net/attestation/verifier/TA-A1-ABCD-EFGH', route('attestation.verify', 'TA-A1-ABCD-EFGH'));
        $this->assertStringStartsWith('https://talkami.net/', route('courses.index'));
    }

    public function test_the_video_site_moves_to_talkami_net_only_from_the_old_address(): void
    {
        $migration = require database_path('migrations/2026_11_09_000001_move_video_site_to_talkami_net.php');

        VideoSettings::saveSite('talkami.laravel.cloud');
        $migration->up();
        $this->assertSame('talkami.net', VideoSettings::site());

        Setting::put(VideoSettings::SITE, 'autre-adresse.com');
        $migration->up();
        $this->assertSame('autre-adresse.com', VideoSettings::site());
    }
}
