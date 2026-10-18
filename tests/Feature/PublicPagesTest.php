<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CountryPage;
use App\Support\Seo;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pages publiques : navigation d'origine (aucune barre du bas pour un visiteur), aucun lien interne cassé,
 * pages par pays utiles et différentes, page « À propos », page 404 qui propose la suite.
 */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    /** Pages publiques vues par un visiteur, adresse inconnue (404) comprise. */
    private const PAGES = [
        '/', '/formules', '/a-propos', '/apprendre-anglais/togo', '/apprendre-anglais/benin', '/apprendre-anglais/burkina-faso',
        '/apprendre-anglais/cote-d-ivoire', '/contact', '/inscription', '/confidentialite', '/connexion', '/mot-de-passe-oublie', '/adresse-inconnue',
    ];

    /**
     * @return list<string> adresses des liens d'un bloc HTML
     */
    private static function links(string $html): array
    {
        preg_match_all('/<a\s[^>]*href="([^"]+)"/', $html, $matches);

        return array_map(fn (string $href) => html_entity_decode($href, ENT_QUOTES), $matches[1]);
    }

    public function test_the_navigation_of_visitors_is_unchanged_on_every_public_page(): void
    {
        foreach (self::PAGES as $path) {
            $html = $this->get($path)->getContent();

            // Aucune barre du bas pour un visiteur : seulement la navigation d'origine de l'en-tête.
            $this->assertStringNotContainsString('class="tabbar', $html, $path);
            $this->assertStringNotContainsString('has-tabbar', $html, $path);
            preg_match('/<header class="site-header">(.*?)<\/header>/s', $html, $header);
            $this->assertSame([route('home'), route('login'), route('register')], self::links($header[1]), $path);
        }
    }

    public function test_the_whatsapp_group_link_replaces_the_number_on_the_not_found_page(): void
    {
        config(['platform.contact.whatsapp_group' => 'https://chat.whatsapp.com/InvitationDuGroupe']);

        $this->get('/adresse-inconnue')->assertSee('href="https://chat.whatsapp.com/InvitationDuGroupe"', false);

        // Une valeur qui n'est pas un lien de groupe WhatsApp n'est jamais affichée.
        config(['platform.contact.whatsapp_group' => 'javascript:alert(1)']);
        $this->get('/adresse-inconnue')->assertDontSee('javascript:alert', false);
    }

    public function test_no_internal_link_of_a_public_page_is_broken(): void
    {
        $base = rtrim(config('app.url'), '/');
        $checked = [];

        foreach (self::PAGES as $path) {
            $html = $this->get($path)->getContent();

            foreach (self::links($html) as $href) {
                // Lien vers une partie de la même page : l'ancre doit y exister.
                if (str_starts_with($href, '#')) {
                    $this->assertStringContainsString('id="'.substr($href, 1).'"', $html, "Ancre absente sur {$path} : {$href}");

                    continue;
                }

                if (! str_starts_with($href, $base.'/') && ! str_starts_with($href, '/')) {
                    continue; // tel:, mailto:, WhatsApp
                }

                [$url, $anchor] = array_pad(explode('#', $href, 2), 2, null);
                $key = $url.'#'.$anchor;

                if (isset($checked[$key])) {
                    continue;
                }

                $checked[$key] = true;
                $response = $this->get($url);
                $this->assertLessThan(400, $response->status(), "Lien cassé sur {$path} : {$href}");

                // Une ancre doit exister sur la page d'arrivée.
                if ($anchor !== null && $anchor !== '' && $response->status() === 200) {
                    $this->assertStringContainsString('id="'.$anchor.'"', $response->getContent(), "Ancre absente sur {$path} : {$href}");
                }
            }
        }

        $this->assertGreaterThanOrEqual(10, count($checked));
    }

    public function test_the_home_page_and_the_footer_link_to_the_new_pages(): void
    {
        $html = $this->get('/')->getContent();

        foreach (CountryPage::slugs() as $slug) {
            $this->assertSame(2, substr_count($html, 'href="'.route('country', $slug).'"'), "Accueil et pied de page vers {$slug}.");
        }

        preg_match('/<footer class="site-footer">(.*?)<\/footer>/s', $html, $footer);
        foreach ([route('about'), route('contact'), route('privacy')] as $url) {
            $this->assertContains($url, self::links($footer[1]));
        }

        // Une page pays renvoie aux trois autres.
        $togo = $this->get('/apprendre-anglais/togo')->getContent();
        preg_match('/<ul class="country-links">(.*?)<\/ul>/s', $togo, $others);
        $this->assertSame([route('country', 'benin'), route('country', 'burkina-faso'), route('country', 'cote-d-ivoire')], self::links($others[1]));
    }

    public function test_each_country_page_has_its_own_text_and_its_real_payment_methods(): void
    {
        $expected = [
            'togo' => ['Mixx by Yas', 'Flooz', 'Lomé'],
            'benin' => ['MTN', 'Moov', 'Celtiis', 'Mes cours'],
            'burkina-faso' => ['Orange Money', 'Wave', 'métier'],
            'cote-d-ivoire' => ['Orange Money', 'MTN Mobile Money', 'Wave', 'attestation de niveau'],
        ];
        $texts = [];

        foreach ($expected as $slug => $words) {
            $html = $this->get('/apprendre-anglais/'.$slug)->assertOk()->getContent();

            foreach ($words as $word) {
                $this->assertStringContainsString($word, html_entity_decode($html, ENT_QUOTES), "{$slug} : {$word}");
            }

            foreach (['T-Money', 'certificat', 'diplôme', 'fait les devoirs', 'donne les réponses', 'tous vos cours', 'correction écrite', ' min ', 'témoignage', 'apprenants satisfaits'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, str_replace('ne fait pas les devoirs', '', html_entity_decode(strip_tags($html), ENT_QUOTES)), "{$slug} : « {$forbidden} »");
            }

            $page = CountryPage::find($slug);
            $texts[$slug] = $page->lead.' '.implode(' ', array_column($page->sections, 'text')).' '.implode(' ', array_column($page->faq, 'answer'));
        }

        // Des textes vraiment différents : jamais la même section ni la même question d'une page à l'autre.
        $sections = array_merge(...array_map(fn (CountryPage $page) => array_column($page->sections, 'text'), array_values(CountryPage::all())));
        $questions = array_merge(...array_map(fn (CountryPage $page) => array_column($page->faq, 'question'), array_values(CountryPage::all())));
        $this->assertSame($sections, array_values(array_unique($sections)));
        $this->assertSame($questions, array_values(array_unique($questions)));

        foreach ($texts as $slug => $text) {
            foreach ($texts as $other => $otherText) {
                if ($slug < $other) {
                    similar_text($text, $otherText, $percent);
                    $this->assertLessThan(60, $percent, "{$slug} et {$other} se ressemblent trop.");
                }
            }
        }

        $this->get('/apprendre-anglais/france')->assertNotFound();
    }

    public function test_country_pages_use_real_photos_from_the_configuration(): void
    {
        foreach (CountryPage::all() as $page) {
            $this->assertNotNull(config('photos.'.$page->photo), $page->slug);
            $this->assertFileExists(public_path('images/photos/'.$page->photo.'-480.webp'));
        }
    }

    public function test_the_not_found_page_offers_the_next_step(): void
    {
        SiteSettings::save(['contact.whatsapp' => '+228 90 00 00 99']);

        $this->get('/adresse-inconnue')
            ->assertNotFound()
            ->assertSeeText('Page introuvable')
            ->assertSee(route('home'), false)
            ->assertSee(route('register'), false)
            ->assertSee(route('plans'), false)
            ->assertSee('https://wa.me/22890000099', false)
            ->assertSee(route('contact'), false);

        $this->actingAs(User::factory()->create())->get('/adresse-inconnue')->assertSee(route('dashboard'), false)->assertSee(route('assessment.show'), false);
    }

    public function test_the_new_public_pages_are_indexable_and_in_the_sitemap(): void
    {
        $sitemap = $this->get('/sitemap.xml')->getContent();

        foreach (['/formules', '/a-propos', '/apprendre-anglais/togo', '/apprendre-anglais/benin', '/apprendre-anglais/burkina-faso', '/apprendre-anglais/cote-d-ivoire'] as $path) {
            $this->assertStringContainsString('<loc>'.Seo::url($path).'</loc>', $sitemap);
            $this->assertStringContainsString('content="index, follow"', $this->get($path)->getContent());
        }
    }
}
