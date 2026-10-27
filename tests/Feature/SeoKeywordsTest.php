<?php

namespace Tests\Feature;

use App\Support\CountryPage;
use App\Support\Plan;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Référencement sur talkami.net : chaque page publique vise ses propres recherches (marque, fondateur,
 * « cours d'anglais en ligne », pays et grandes villes), avec des textes vrais, tirés de la configuration.
 */
class SeoKeywordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://talkami.net']);
    }

    public function test_each_public_page_targets_its_own_searches(): void
    {
        $expected = [
            '/' => ['Talkami', 'Teacher Joe', 'Cours d\'anglais en ligne'],
            '/a-propos' => ['Joseph Ahiankonou', 'fondateur'],
            '/formules' => ['prix', 'cours d\'anglais en ligne'],
            '/comment-ca-marche' => ['Apprendre l\'anglais sur téléphone'],
            '/apprendre-anglais/togo' => ['Lomé', 'Togo'],
            '/apprendre-anglais/benin' => ['Cotonou', 'Bénin'],
            '/apprendre-anglais/burkina-faso' => ['Ouagadougou', 'Burkina Faso'],
            '/apprendre-anglais/cote-d-ivoire' => ['Abidjan', 'Côte d\'Ivoire'],
        ];

        foreach ($expected as $path => $words) {
            preg_match('/<title>(.*?)<\/title>/s', $this->get($path)->assertOk()->getContent(), $title);
            $title = html_entity_decode($title[1], ENT_QUOTES);

            $this->assertLessThanOrEqual(60, mb_strlen($title), $path);
            foreach ($words as $word) {
                $this->assertStringContainsString($word, $title, $path);
            }
        }
    }

    public function test_the_about_page_tells_the_true_story_of_the_founder_without_artificial_intelligence(): void
    {
        $cheapest = collect(Plan::all())->sortBy('price')->first();

        $this->get('/a-propos')->assertOk()
            ->assertSeeInOrder(['<h1>Joseph Ahiankonou et l\'histoire de Talkami</h1>', 'Pourquoi Talkami existe'], false)
            ->assertSeeText('Joseph Ahiankonou voulait apprendre à parler anglais. Un cours privé lui coûtait 350 000 FCFA pour six mois.')
            ->assertSee("350\u{202F}000\u{00A0}FCFA", false)
            ->assertSeeText('à partir de '.$cheapest->formattedPrice().' '.$cheapest->periodLabel())
            ->assertSee('href="'.config('platform.contact.tiktok').'"', false)
            ->assertDontSee('intelligence artificielle')
            ->assertDontSee(' IA');
    }

    public function test_no_public_page_speaks_of_artificial_intelligence_except_privacy(): void
    {
        foreach (array_diff(Seo::sitemapUrls(), [Seo::url('confidentialite')]) as $url) {
            $html = mb_strtolower(strip_tags($this->get($url)->getContent()));

            $this->assertStringNotContainsString('intelligence artificielle', $html, $url);
            $this->assertDoesNotMatchRegularExpression('/\bia\b/u', $html, $url);
        }
    }

    public function test_the_founder_and_the_platform_accounts_are_linked_only_where_they_are_shown(): void
    {
        $about = $this->jsonLd($this->get('/a-propos')->getContent());
        $person = collect($about)->firstWhere('@type', 'Person');
        $organization = collect($about)->firstWhere('@type', 'Organization');

        $this->assertSame('Fondateur de Talkami', $person['jobTitle']);
        $this->assertSame($organization['@id'], $person['worksFor']['@id']);
        $this->assertSame($person['@id'], $organization['founder']['@id']);
        $this->assertSame(['https://www.tiktok.com/@talkami8', config('platform.contact.whatsapp_channel')], $organization['sameAs']);

        // L'accueil ne montre pas ces comptes : ses données structurées non plus.
        $home = collect($this->jsonLd($this->get('/')->getContent()))->firstWhere('@type', 'Organization');
        $this->assertArrayNotHasKey('sameAs', $home);
    }

    public function test_each_country_page_shows_its_own_payment_methods_and_the_real_prices(): void
    {
        foreach (CountryPage::all() as $slug => $page) {
            $html = $this->get('/apprendre-anglais/'.$slug)->assertOk()->getContent();

            $this->assertStringContainsString('Moyens de paiement '.$page->in.' '.$page->name, html_entity_decode(strip_tags($html), ENT_QUOTES), $slug);
            foreach ($page->country->operators as $operator) {
                $this->assertStringContainsString('<span class="tag">'.e($operator->name).'</span>', $html, $slug);
            }

            // Une question sur le prix, avec les vrais prix, affichée ET dans les données structurées.
            $answers = implode(' ', array_column($page->faq, 'answer'));
            foreach (Plan::all() as $plan) {
                $this->assertStringContainsString($plan->name.' à '.$plan->formattedPrice().' '.$plan->periodLabel(), $answers, $slug);
            }
            $faq = collect($this->jsonLd($html))->firstWhere('@type', 'FAQPage');
            $this->assertCount(count($page->faq), $faq['mainEntity'], $slug);
        }
    }

    public function test_the_country_pages_link_to_the_video_page_and_the_story(): void
    {
        $this->get('/apprendre-anglais/benin')
            ->assertSee('href="'.route('how').'"', false)
            ->assertSee('href="'.route('about').'"', false);
        $this->get('/a-propos')->assertSee('href="'.route('plans').'"', false)->assertSee('href="'.route('how').'"', false);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonLd(string $html): array
    {
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $scripts);

        return array_map(fn (string $json) => json_decode($json, true), $scripts[1]);
    }
}
