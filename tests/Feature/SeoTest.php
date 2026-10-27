<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Support\Plan;
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

    /** Pages publiques indexées et leur adresse canonique (domaine définitif). */
    private const PUBLIC_PAGES = [
        '/' => 'https://talkami.net/',
        '/formules' => 'https://talkami.net/formules',
        '/a-propos' => 'https://talkami.net/a-propos',
        '/comment-ca-marche' => 'https://talkami.net/comment-ca-marche',
        '/apprendre-anglais/togo' => 'https://talkami.net/apprendre-anglais/togo',
        '/apprendre-anglais/benin' => 'https://talkami.net/apprendre-anglais/benin',
        '/apprendre-anglais/burkina-faso' => 'https://talkami.net/apprendre-anglais/burkina-faso',
        '/apprendre-anglais/cote-d-ivoire' => 'https://talkami.net/apprendre-anglais/cote-d-ivoire',
        '/contact' => 'https://talkami.net/contact',
        '/inscription' => 'https://talkami.net/inscription',
        '/confidentialite' => 'https://talkami.net/confidentialite',
    ];

    public function test_public_pages_have_unique_titles_descriptions_canonical_and_share_tags_from_app_url(): void
    {
        $titles = [];
        $descriptions = [];

        foreach (self::PUBLIC_PAGES as $path => $canonical) {
            $html = $this->get($path)->assertOk()->assertHeaderMissing('X-Robots-Tag')->getContent();

            $this->assertStringContainsString('<html lang="fr">', $html);
            $this->assertSame('index, follow', $this->meta($html, 'name', 'robots'));
            $this->assertStringContainsString('<link rel="canonical" href="'.$canonical.'">', $html);
            $this->assertSame($canonical, $this->meta($html, 'property', 'og:url'));
            // Les pages pays ont leur propre image (ShareAndPreviewTest) ; les autres, celle de l'accueil.
            $image = str_starts_with($path, '/apprendre-anglais/') ? 'images/partage/pays-'.basename($path).'.jpg' : 'images/partage-talkami.jpg';
            $this->assertSame('https://talkami.net/'.$image, $this->meta($html, 'property', 'og:image'));
            $this->assertSame('https://talkami.net/'.$image, $this->meta($html, 'name', 'twitter:image'));
            $this->assertSame('summary_large_image', $this->meta($html, 'name', 'twitter:card'));
            $this->assertSame('fr_FR', $this->meta($html, 'property', 'og:locale'));
            $this->assertStringNotContainsString('localhost', $this->meta($html, 'property', 'og:url'));

            preg_match('/<title>([^<]*)<\/title>/', $html, $title);
            $title = html_entity_decode($title[1], ENT_QUOTES);
            $description = $this->meta($html, 'name', 'description');
            $this->assertLessThanOrEqual(60, mb_strlen($title), "Titre trop long ({$path}) : {$title}");
            $this->assertGreaterThanOrEqual(30, mb_strlen($title), "Titre trop court ({$path}) : {$title}");
            $this->assertLessThanOrEqual(155, mb_strlen($description), "Description trop longue ({$path}) : {$description}");
            $this->assertGreaterThanOrEqual(100, mb_strlen($description), "Description trop courte ({$path}) : {$description}");
            $this->assertDoesNotMatchRegularExpression('/\b(min|max|env|svp|tél)\b\.?/iu', $title.' '.$description, "Abréviation ({$path})");
            $this->assertSame($title, $this->meta($html, 'property', 'og:title'));
            $this->assertSame(1, substr_count($html, '<h1'), "Un seul titre principal ({$path}).");

            $titles[] = $title;
            $descriptions[] = $description;
        }

        $this->assertCount(count(self::PUBLIC_PAGES), array_unique($titles), 'Un titre par page.');
        $this->assertCount(count(self::PUBLIC_PAGES), array_unique($descriptions), 'Une description par page.');
        $this->assertSame('Talkami · Cours d\'anglais en ligne avec Teacher Joe', $titles[0]);
    }

    public function test_the_google_search_console_tag_appears_only_when_the_variable_is_filled(): void
    {
        $this->assertStringNotContainsString('google-site-verification', $this->get('/')->getContent());

        config(['services.google.site_verification' => 'code-de-verification-123']);
        $this->assertSame('code-de-verification-123', $this->meta($this->get('/')->getContent(), 'name', 'google-site-verification'));
    }

    public function test_an_old_address_is_redirected_permanently_to_the_same_page_on_app_url(): void
    {
        config(['app.redirect_from_hosts' => ['talkami.laravel.cloud']]);

        $this->get('https://talkami.laravel.cloud/apprendre-anglais/togo?source=whatsapp')
            ->assertStatus(301)
            ->assertRedirect('https://talkami.net/apprendre-anglais/togo?source=whatsapp');
        $this->get('https://talkami.laravel.cloud/sitemap.xml')->assertStatus(301);
        // Le contrôle de santé et les envois ne sont jamais redirigés ; une autre adresse non plus.
        $this->get('https://talkami.laravel.cloud/up')->assertOk();
        $this->post('https://talkami.laravel.cloud/connexion', [])->assertStatus(302)->assertRedirect();
        $this->get('https://talkami.net/apprendre-anglais/togo')->assertOk();

        config(['app.redirect_from_hosts' => []]);
        $this->get('https://talkami.laravel.cloud/contact')->assertOk();
    }

    public function test_the_share_image_is_a_1200_by_630_jpeg(): void
    {
        $size = getimagesize(public_path(Seo::SHARE_IMAGE));

        $this->assertSame([1200, 630], [$size[0], $size[1]]);
        $this->assertSame('image/jpeg', $size['mime']);
        $this->assertLessThan(200 * 1024, filesize(public_path(Seo::SHARE_IMAGE)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonLdOf(string $html): array
    {
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $blocks);

        return array_map(fn (string $json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $blocks[1]);
    }

    /**
     * Toutes les valeurs « @type » d'un bloc, à toute profondeur, et toutes ses clés.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private static function typesAndKeys(array $data): array
    {
        $types = [];
        $keys = [];

        array_walk_recursive($data, function ($value, $key) use (&$types, &$keys) {
            $keys[] = (string) $key;

            if ($key === '@type') {
                $types[] = $value;
            }
        });

        // Clés des sous-objets (array_walk_recursive ne passe que par les feuilles).
        $walk = function (array $node) use (&$walk, &$keys) {
            foreach ($node as $key => $value) {
                $keys[] = (string) $key;

                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($data);

        return [$types, array_values(array_unique($keys))];
    }

    public function test_the_structured_data_of_every_public_page_is_valid_and_never_invented(): void
    {
        foreach (array_keys(self::PUBLIC_PAGES) as $path) {
            foreach ($this->jsonLdOf($this->get($path)->getContent()) as $block) {
                $this->assertSame('https://schema.org', $block['@context'], $path);
                [$types, $keys] = self::typesAndKeys($block);

                foreach ($types as $type) {
                    $this->assertContains($type, Seo::SCHEMA_TYPES, "Type inattendu {$type} ({$path}).");
                }

                // Ni note, ni avis, ni nombre d'apprenants, ni témoignage.
                foreach (['aggregateRating', 'review', 'reviews', 'ratingValue', 'reviewCount', 'numberOfEmployees', 'interactionStatistic', 'award'] as $forbidden) {
                    $this->assertNotContains($forbidden, $keys, "{$forbidden} interdit ({$path}).");
                }

                // Toute adresse est sur APP_URL (sauf les liens schema.org et les comptes publics de sameAs).
                array_walk_recursive($block, function ($value) use ($path) {
                    if (is_string($value) && str_starts_with($value, 'http') && ! str_starts_with($value, 'https://schema.org') && ! in_array($value, Seo::profiles(), true)) {
                        $this->assertStringStartsWith('https://talkami.net/', $value, $path);
                    }
                });
            }
        }
    }

    public function test_the_home_page_describes_the_organization_the_site_the_real_prices_and_its_real_questions(): void
    {
        $html = $this->get('/')->getContent();
        $data = $this->jsonLdOf($html);

        $this->assertSame(['Organization', 'WebSite', 'Service', 'FAQPage'], array_column($data, '@type'));

        [$organization, $website, $service, $faq] = $data;
        $this->assertSame(config('platform.name'), $organization['name']);
        $this->assertSame('https://talkami.net/', $organization['url']);
        $this->assertStringStartsWith('https://talkami.net/brand/', $organization['logo']);
        $this->assertFileExists(public_path(substr($organization['logo'], strlen('https://talkami.net/'))));
        $this->assertSame('Joseph Ahiankonou', $organization['founder']['name']);
        $this->assertSame('https://talkami.net/', $website['url']);
        $this->assertSame('fr', $website['inLanguage']);

        // Les vrais prix de la configuration, en francs CFA (XOF), une offre par formule.
        $expected = array_values(array_map(fn ($plan) => [$plan->name, (string) $plan->price], Plan::all()));
        $this->assertSame($expected, array_map(fn (array $offer) => [$offer['name'], $offer['price']], $service['offers']));
        $this->assertSame([['Semaine', '2000'], ['Essentiel', '7500'], ['Intensif', '14000']], $expected);
        $this->assertSame(['XOF'], array_values(array_unique(array_column($service['offers'], 'priceCurrency'))));
        $this->assertSame(['Togo', 'Burkina Faso', 'Bénin', 'Côte d\'Ivoire'], array_column($service['areaServed'], 'name'));

        // Exactement les questions affichées sur la page, dans le même ordre.
        preg_match_all('/<summary>.*?<span>(.*?)<\/span><\/summary>/s', $html, $summaries);
        $shown = array_map(fn (string $text) => html_entity_decode(trim($text), ENT_QUOTES), $summaries[1]);
        $this->assertSame($shown, array_column($faq['mainEntity'], 'name'));
        $this->assertGreaterThanOrEqual(5, count($shown));

        foreach ($faq['mainEntity'] as $question) {
            $this->assertNotSame('', $question['acceptedAnswer']['text']);
            $this->assertStringNotContainsString('<', $question['acceptedAnswer']['text']);
        }
    }

    public function test_the_about_page_describes_the_founder_and_the_plans_page_the_offers(): void
    {
        $person = $this->jsonLdOf($this->get('/a-propos')->assertSeeText('Joseph Ahiankonou')->getContent())[0];

        $this->assertSame('Person', $person['@type']);
        $this->assertSame('Joseph Ahiankonou', $person['name']);
        $this->assertSame('Fondateur de Talkami', $person['jobTitle']);
        $this->assertSame(['@type' => 'PostalAddress', 'addressLocality' => 'Lomé', 'addressCountry' => 'TG'], $person['address']);
        $this->assertSame('Talkami', $person['worksFor']['name']);
        // Présentation écrite par le fondateur lui-même, et sa photo.
        $this->assertSame("J'ai créé Talkami pour que chaque francophone puisse enfin oser parler anglais, depuis son téléphone, sans gêne.", $person['description']);
        $this->assertSame(Seo::url('images/fondateur-joseph-ahiankonou.webp'), $person['image']);

        // Sans présentation : rien d'inventé.
        config(['platform.founder.bio' => null]);
        $person = $this->jsonLdOf($this->get('/a-propos')->getContent())[0];
        $this->assertArrayNotHasKey('description', $person, 'Aucune présentation inventée tant que founder.bio est vide.');

        $offers = $this->jsonLdOf($this->get('/formules')->assertSeeText('7 500 FCFA')->getContent())[0];
        $this->assertSame('Service', $offers['@type']);
        $this->assertCount(3, $offers['offers']);
    }

    public function test_each_country_page_shows_its_own_questions_as_structured_data(): void
    {
        foreach (['togo', 'benin', 'burkina-faso', 'cote-d-ivoire'] as $slug) {
            $html = $this->get('/apprendre-anglais/'.$slug)->getContent();
            preg_match_all('/<summary>.*?<span>(.*?)<\/span><\/summary>/s', $html, $summaries);
            $faq = collect($this->jsonLdOf($html))->firstWhere('@type', 'FAQPage');

            $this->assertSame(array_map(fn (string $text) => html_entity_decode(trim($text), ENT_QUOTES), $summaries[1]), array_column($faq['mainEntity'], 'name'), $slug);
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

        $this->assertSame(array_values(self::PUBLIC_PAGES), $urls);
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
