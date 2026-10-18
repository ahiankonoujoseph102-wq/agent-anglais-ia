<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Référencement : pages publiques indexées avec titre, description, canonique, Open Graph et Twitter tirés
 * de APP_URL ; données structurées Organization et FAQPage ; sitemap.xml et robots.txt ; noindex partout ailleurs.
 */
class SeoTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Le domaine définitif : les adresses doivent le suivre, quelle que soit l'adresse de la requête.
        config(['app.url' => 'https://talkami.net']);
    }

    private function meta(string $html, string $attribute, string $name): ?string
    {
        return preg_match('/<meta '.$attribute.'="'.preg_quote($name, '/').'" content="([^"]*)">/', $html, $match) ? html_entity_decode($match[1], ENT_QUOTES) : null;
    }

    public function test_public_pages_have_unique_titles_descriptions_canonical_and_share_tags_from_app_url(): void
    {
        $titles = [];
        $descriptions = [];

        foreach (['/' => 'https://talkami.net/', '/contact' => 'https://talkami.net/contact', '/inscription' => 'https://talkami.net/inscription'] as $path => $canonical) {
            $html = $this->get($path)->assertOk()->assertHeaderMissing('X-Robots-Tag')->getContent();

            $this->assertStringContainsString('<html lang="fr">', $html);
            $this->assertSame('index, follow', $this->meta($html, 'name', 'robots'));
            $this->assertStringContainsString('<link rel="canonical" href="'.$canonical.'">', $html);
            $this->assertSame($canonical, $this->meta($html, 'property', 'og:url'));
            $this->assertSame('https://talkami.net/images/partage-talkami.jpg', $this->meta($html, 'property', 'og:image'));
            $this->assertSame('https://talkami.net/images/partage-talkami.jpg', $this->meta($html, 'name', 'twitter:image'));
            $this->assertSame('summary_large_image', $this->meta($html, 'name', 'twitter:card'));
            $this->assertSame('fr_FR', $this->meta($html, 'property', 'og:locale'));
            $this->assertStringNotContainsString('localhost', $this->meta($html, 'property', 'og:url'));

            preg_match('/<title>([^<]*)<\/title>/', $html, $title);
            $titles[] = $title[1];
            $descriptions[] = $this->meta($html, 'name', 'description');
            $this->assertSame(html_entity_decode($title[1], ENT_QUOTES), $this->meta($html, 'property', 'og:title'));
        }

        $this->assertCount(3, array_unique($titles), 'Un titre par page.');
        $this->assertCount(3, array_unique($descriptions), 'Une description par page.');
        $this->assertSame('Talkami · Apprendre à parler anglais depuis votre téléphone', html_entity_decode($titles[0], ENT_QUOTES));
    }

    public function test_the_share_image_is_a_1200_by_630_jpeg(): void
    {
        $size = getimagesize(public_path(Seo::SHARE_IMAGE));

        $this->assertSame([1200, 630], [$size[0], $size[1]]);
        $this->assertSame('image/jpeg', $size['mime']);
        $this->assertLessThan(200 * 1024, filesize(public_path(Seo::SHARE_IMAGE)));
    }

    public function test_the_home_page_describes_the_organization_and_its_real_questions(): void
    {
        $html = $this->get('/')->getContent();
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $blocks);
        $data = array_map(fn (string $json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $blocks[1]);
        $types = array_column($data, '@type');

        $this->assertSame(['Organization', 'FAQPage'], $types);

        $organization = $data[0];
        $this->assertSame(config('platform.name'), $organization['name']);
        $this->assertSame('https://talkami.net/', $organization['url']);
        $this->assertStringStartsWith('https://talkami.net/brand/', $organization['logo']);
        $this->assertFileExists(public_path(substr($organization['logo'], strlen('https://talkami.net/'))));

        // Exactement les questions affichées sur la page, dans le même ordre.
        preg_match_all('/<summary>.*?<span>(.*?)<\/span><\/summary>/s', $html, $summaries);
        $shown = array_map(fn (string $text) => html_entity_decode(trim($text), ENT_QUOTES), $summaries[1]);
        $this->assertSame($shown, array_column($data[1]['mainEntity'], 'name'));
        $this->assertGreaterThanOrEqual(5, count($shown));

        foreach ($data[1]['mainEntity'] as $question) {
            $this->assertNotSame('', $question['acceptedAnswer']['text']);
            $this->assertStringNotContainsString('<', $question['acceptedAnswer']['text']);
        }
    }

    public function test_private_pages_are_never_indexed(): void
    {
        $learner = User::factory()->create();
        Subscription::factory()->for($learner)->create();
        $this->buildProgram($learner);
        $admin = User::factory()->admin()->create();

        $pages = [
            [$learner, route('dashboard')],
            [$learner, route('program.show')],
            [$learner, route('lessons.history')],
            [$learner, route('lesson.show')],
            [$learner, route('payment.plans')],
            [$learner, route('courses.index')],
            [$learner, route('account.edit')],
            [$learner, route('welcome.show', ['reecouter' => 1])],
            [$admin, route('admin.payments.pending')],
            [$admin, route('admin.users.index')],
            [$admin, route('admin.voice-diagnostic.index')],
            [null, route('login')],
            [null, route('password.forgot')],
            [null, '/page-qui-n-existe-pas'],
        ];

        foreach ($pages as [$user, $url]) {
            $user ? $this->actingAs($user) : auth()->logout();
            $response = $this->get($url);
            $html = $response->getContent();

            $this->assertSame('noindex, nofollow', $this->meta($html, 'name', 'robots'), $url);
            $this->assertStringNotContainsString('rel="canonical"', $html, $url);
            $this->assertStringNotContainsString('og:image', $html, $url);
            // Une adresse inconnue (404) ne passe par aucune route : la balise suffit, un 404 n'est jamais indexé.
            if ($response->status() !== 404) {
                $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'), $url);
            }
        }
    }

    public function test_the_sitemap_lists_only_the_public_pages_from_app_url(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));
        $xml = simplexml_load_string($response->getContent());
        $urls = [];

        foreach ($xml->url as $url) {
            $urls[] = (string) $url->loc;
        }

        $this->assertSame(['https://talkami.net/', 'https://talkami.net/contact', 'https://talkami.net/inscription'], $urls);
    }

    public function test_robots_txt_closes_private_pages_and_points_to_the_sitemap(): void
    {
        config(['app.env' => 'production']);
        $this->app['env'] = 'production';

        $text = $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->getContent();

        $this->assertStringContainsString("User-agent: *\n", $text);

        foreach (['/admin', '/espace', '/bienvenue', '/abonnement', '/paiements', '/seance', '/mes-cours', '/programme'] as $path) {
            $this->assertStringContainsString("Disallow: {$path}\n", $text);
        }

        $this->assertStringContainsString("Allow: /\n", $text);
        $this->assertStringContainsString('Sitemap: https://talkami.net/sitemap.xml', $text);
        $this->assertStringNotContainsString("Disallow: /\n", $text);
    }

    public function test_robots_txt_closes_everything_outside_production(): void
    {
        $text = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString("Disallow: /\n", $text);
        $this->assertStringNotContainsString('Allow: /', $text);
    }

    public function test_no_static_robots_file_hides_the_generated_one(): void
    {
        // Sur Laravel Cloud, un fichier de public/ passe avant les routes.
        $this->assertFileDoesNotExist(public_path('robots.txt'));
        $this->assertFileDoesNotExist(public_path('sitemap.xml'));
    }
}
