<?php

namespace Tests\Feature;

use App\Support\Guides;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guides gratuits (/guides) et pages d'arrivée « Aide aux cours d'anglais » et « Préparer un entretien en anglais » :
 * pages publiques indexées, chacune menant à l'évaluation gratuite, textes vrais et sans formule interdite.
 */
class GuidesTest extends TestCase
{
    use RefreshDatabase;

    /** Mots interdits sur ces pages (règles du site : marques, promesses, autres pays, vocabulaire imposé). */
    private const FORBIDDEN = '/intelligence artificielle|\bIA\b|Gemini|OpenAI|ChatGPT|garanti|sans engagement|sans risque|100 ?%|carte bancaire|certificat|diplôme|TOEFL|IELTS|Ghana|Accra|États-Unis|Etats-Unis|Angleterre|Royaume-Uni|donne les réponses|tous vos cours|correction écrite/iu';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://talkami.net']);
    }

    /**
     * @return list<string>
     */
    private function pages(): array
    {
        return ['/guides', '/aide-aux-cours-d-anglais', '/preparer-un-entretien-en-anglais', ...array_map(fn (string $slug) => '/guides/'.$slug, Guides::slugs())];
    }

    public function test_there_are_ten_guides_in_the_proposed_order(): void
    {
        $this->assertSame([
            'se-presenter-en-anglais', 'phrases-anglaises-du-quotidien', 'anglais-pour-debutants', 'ameliorer-son-anglais-oral',
            'entretien-en-anglais-questions', 'parler-anglais-sur-telephone', 'erreurs-anglais-francophones',
            'parler-anglais-couramment', 'anglais-au-travail-accueillir-un-client', 'anglais-americain-ou-britannique',
        ], Guides::slugs());
    }

    public function test_every_page_is_indexed_leads_to_the_free_assessment_and_says_nothing_forbidden(): void
    {
        $titles = [];

        foreach ($this->pages() as $path) {
            $html = $this->get($path)->assertOk()->assertHeaderMissing('X-Robots-Tag')->getContent();

            $this->assertStringContainsString('<meta name="robots" content="index, follow">', $html, $path);
            $this->assertSame(1, substr_count($html, '<h1'), $path);
            $this->assertStringContainsString('Passer mon évaluation gratuite', $html, $path);
            $this->assertStringContainsString('href="'.route('register').'"', $html, $path);

            preg_match('/<title>(.*?)<\/title>/s', $html, $title);
            preg_match('/name="description" content="([^"]*)"/', $html, $description);
            $this->assertLessThanOrEqual(60, mb_strlen(html_entity_decode($title[1], ENT_QUOTES)), $path);
            $this->assertLessThanOrEqual(155, mb_strlen(html_entity_decode($description[1], ENT_QUOTES)), $path);
            $titles[] = $title[1];

            $text = html_entity_decode(strip_tags(preg_replace('/<(script|style)\b.*?<\/\1>/s', '', $html)), ENT_QUOTES);
            $this->assertDoesNotMatchRegularExpression(self::FORBIDDEN, $text, $path);
            // « fait les devoirs » : seulement dans la négation.
            $this->assertDoesNotMatchRegularExpression('/(?<!ne )fait les devoirs/u', $text, $path);
        }

        $this->assertSame($titles, array_unique($titles));
    }

    public function test_each_guide_is_an_article_with_its_breadcrumb_and_related_guides(): void
    {
        foreach (Guides::all() as $slug => $guide) {
            $html = $this->get('/guides/'.$slug)->getContent();
            preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $scripts);
            $blocks = collect($scripts[1])->map(fn ($json) => json_decode($json, true));

            $article = $blocks->firstWhere('@type', 'Article');
            $this->assertSame($guide->heading, $article['headline'], $slug);
            $this->assertSame('https://talkami.net/guides/'.$slug, $article['url']);
            $this->assertSame('Talkami', $article['author']['name']);
            $this->assertSame(['Accueil', 'Guides', $guide->heading], array_column($blocks->firstWhere('@type', 'BreadcrumbList')['itemListElement'], 'name'));

            $this->assertStringContainsString('lang="en-US"', $html, $slug);
            $this->assertCount(3, $guide->relatedGuides(), $slug);
            foreach ($guide->relatedGuides() as $related) {
                $this->assertStringContainsString('href="'.route('guides.show', $related->slug).'"', $html);
            }
        }

        $index = $this->get('/guides')->getContent();
        foreach (Guides::slugs() as $slug) {
            $this->assertStringContainsString('href="'.route('guides.show', $slug).'"', $index);
        }
    }

    public function test_the_numbers_in_the_titles_are_true(): void
    {
        $this->assertSame(50, substr_count($this->get('/guides/phrases-anglaises-du-quotidien')->getContent(), 'class="phrase"'));

        preg_match_all('/<ol class="errors"[^>]*>(.*?)<\/ol>/s', $this->get('/guides/erreurs-anglais-francophones')->getContent(), $lists);
        $this->assertSame(20, array_sum(array_map(fn (string $list) => substr_count($list, '<li>'), $lists[1])));

        $this->assertSame(10, preg_match_all('/<h2>\d+\. /', $this->get('/guides/entretien-en-anglais-questions')->getContent()));
    }

    public function test_landing_pages_show_their_questions_as_structured_data_and_never_promise(): void
    {
        foreach (['/aide-aux-cours-d-anglais', '/preparer-un-entretien-en-anglais'] as $path) {
            $html = $this->get($path)->getContent();
            preg_match_all('/<summary>.*?<span>(.*?)<\/span><\/summary>/s', $html, $summaries);
            preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $scripts);
            $faq = collect($scripts[1])->map(fn ($json) => json_decode($json, true))->firstWhere('@type', 'FAQPage');

            $this->assertNotEmpty($summaries[1], $path);
            $this->assertSame(array_map(fn ($text) => html_entity_decode(trim($text), ENT_QUOTES), $summaries[1]), array_column($faq['mainEntity'], 'name'), $path);
        }

        $this->get('/aide-aux-cours-d-anglais')
            ->assertSeeText('Uniquement pour les cours d\'anglais')
            ->assertSeeText('il ne fait pas les devoirs à votre place')
            ->assertSeeText('document de révision');

        $this->get('/preparer-un-entretien-en-anglais')
            ->assertSeeText('Préparer un entretien (bourse, admission, visa, stage, embauche)')
            ->assertSeeText('personne ne peut le promettre honnêtement');
    }

    public function test_the_new_pages_are_in_the_sitemap_and_in_the_footer(): void
    {
        $urls = Seo::sitemapUrls();

        foreach ($this->pages() as $path) {
            $this->assertContains('https://talkami.net'.$path, $urls);
        }

        $this->get('/contact')
            ->assertSee('href="'.route('guides.index').'"', false)
            ->assertSee('href="'.route('courses-help').'"', false)
            ->assertSee('href="'.route('interview').'"', false);

        $this->get('/guides/inconnu')->assertNotFound();
    }
}
