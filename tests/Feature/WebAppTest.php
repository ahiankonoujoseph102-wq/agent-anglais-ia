<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Application installable : manifeste, page hors connexion et service worker.
 */
class WebAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_describes_an_installable_app_from_configuration(): void
    {
        $response = $this->get('/manifest.webmanifest')->assertOk();

        $this->assertStringStartsWith('application/manifest+json', $response->headers->get('Content-Type'));
        $this->assertSame([], $response->headers->getCookies(), 'Le manifeste ne doit pas ouvrir de session.');

        $manifest = $response->json();
        $this->assertSame(config('platform.name'), $manifest['name']);
        $this->assertNotEmpty($manifest['short_name']);
        $this->assertSame('fr', $manifest['lang']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/espace', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame(config('platform.brand.theme_color'), $manifest['theme_color']);
        $this->assertSame(config('platform.brand.background_color'), $manifest['background_color']);

        $sizes = [];

        foreach ($manifest['icons'] as $icon) {
            $sizes[$icon['sizes'].' '.$icon['purpose']] = true;
            $file = public_path(Str::after($icon['src'], url('/').'/'));
            $this->assertFileExists($file);
            [$width, $height] = getimagesize($file);
            $this->assertSame($icon['sizes'], "{$width}x{$height}");
        }

        $this->assertArrayHasKey('192x192 any', $sizes);
        $this->assertArrayHasKey('512x512 any', $sizes);
        $this->assertArrayHasKey('512x512 maskable', $sizes);
    }

    public function test_manifest_name_follows_the_platform_configuration(): void
    {
        config(['platform.name' => 'Professeur Ama', 'platform.brand.short_name' => 'Ama']);

        $this->get('/manifest.webmanifest')
            ->assertJsonPath('name', 'Professeur Ama')
            ->assertJsonPath('short_name', 'Ama');
    }

    public function test_pages_link_the_manifest_and_the_service_worker(): void
    {
        $this->get('/')
            ->assertSee('<link rel="manifest" href="'.route('manifest').'">', false)
            ->assertSee('data-sw="'.asset('sw.js').'"', false);
    }

    public function test_logo_comes_from_the_platform_configuration(): void
    {
        config(['platform.name' => 'Professeur Ama', 'platform.brand.short_name' => 'Ama', 'platform.brand.tagline' => 'Parlez dès aujourd\'hui']);

        $response = $this->get('/contact')->assertOk();

        // Logo horizontal dans l'en-tête, logo blanc dans le pied de page, nom de la plateforme en texte de remplacement.
        $response->assertSee('alt="Professeur Ama"', false)
            ->assertSee(config('platform.brand.logo.header'), false)
            ->assertSee(config('platform.brand.logo.footer'), false)
            ->assertSeeText('Parlez dès aujourd\'hui')
            ->assertDontSee('Talkami');
    }

    public function test_brand_name_comes_from_configuration_and_the_four_logos_exist(): void
    {
        $this->assertSame('Talkami', config('platform.name'));
        $this->assertSame('Talkami', config('platform.brand.short_name'));

        $this->get('/')->assertOk()->assertSee('<title>'.config('platform.name'), false);

        foreach (['logo-horizontal', 'logo-vertical', 'icone', 'logo-blanc'] as $logo) {
            foreach (["{$logo}.png", "{$logo}.webp", "{$logo}-640.webp"] as $file) {
                $this->assertFileExists(public_path("brand/{$file}"));
            }

            $this->assertFileExists(base_path("docs/brand/reference/{$logo}.png"));

            // PNG à fond transparent (canal alpha).
            $png = file_get_contents(public_path("brand/{$logo}.png"));
            $this->assertSame(6, ord($png[25]), "{$logo}.png doit avoir un fond transparent.");
        }

        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertFileExists(public_path('icons/apple-touch-icon.png'));
    }

    public function test_offline_page_contains_no_personal_data(): void
    {
        $user = User::factory()->create(['name' => 'Kossiwa Amegah']);

        $response = $this->actingAs($user)->get('/hors-connexion')->assertOk();

        $response->assertSee('Vous êtes hors connexion')
            ->assertDontSee('Kossiwa Amegah')
            ->assertDontSee('csrf-token', false)
            ->assertDontSee('Déconnexion');
        $this->assertSame([], $response->headers->getCookies(), 'La page hors connexion ne doit pas ouvrir de session.');
    }

    public function test_service_worker_never_caches_personal_pages(): void
    {
        $worker = file_get_contents(public_path('sw.js'));

        // Dossiers mis en cache : uniquement des fichiers statiques de public/.
        $this->assertMatchesRegularExpression('/const STATIC_PATHS = \\[([^\\]]*)\\];/', $worker);
        preg_match('/const STATIC_PATHS = \\[([^\\]]*)\\];/', $worker, $match);
        preg_match_all("/'([^']+)'/", $match[1], $paths);
        $staticPaths = $paths[1];
        $this->assertSame(['/css/', '/js/', '/fonts/', '/icons/', '/images/', '/brand/', '/teacher3d/'], $staticPaths);

        foreach ($staticPaths as $path) {
            $this->assertDirectoryExists(public_path(trim($path, '/')), "{$path} doit être un dossier de fichiers statiques.");
        }

        // Aucune page réservée aux comptes (espace, paiements, séances, administration…) dans ces dossiers.
        $personal = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteDefinition $route) => in_array('auth', $route->gatherMiddleware(), true))
            ->map(fn (RouteDefinition $route) => '/'.ltrim($route->uri(), '/'));

        $this->assertNotEmpty($personal);

        foreach ($personal as $uri) {
            foreach ($staticPaths as $path) {
                $this->assertFalse(Str::startsWith($uri.'/', $path), "La page {$uri} ne doit pas pouvoir être mise en cache.");
            }
        }

        // Les pages passent toujours par le réseau, sans copie en cache ; les envois (POST) ne sont pas interceptés.
        $this->assertStringContainsString("if (request.method !== 'GET') {\n        return;", $worker);
        $this->assertStringContainsString("if (request.mode === 'navigate') {\n        event.respondWith(pageResponse(request));", $worker);
        preg_match('/async function pageResponse\(request\) \{(.*?)\n\}/s', $worker, $page);
        $this->assertNotEmpty($page, 'pageResponse introuvable');
        $this->assertStringNotContainsString('.put(', $page[1]);
        $this->assertStringNotContainsString('.add(', $page[1]);

        // Seule page gardée : la page « hors connexion », sans donnée personnelle.
        $this->assertSame(2, substr_count($worker, 'cache.put('));
        $this->assertStringContainsString("const OFFLINE_URL = '/hors-connexion';", $worker);
    }
}
