<?php

namespace Tests\Feature;

use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Models\User;
use App\Support\Guides;
use App\Support\Seo;
use App\Support\Videos\SiteVideos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Données structurées de chaque page publique (refonte, point 3) : « WebPage » (Contact : « ContactPage », « À propos » :
 * « AboutPage ») et fil d'Ariane (Accueil › page) ; vidéos de « Comment ça marche » (« VideoObject »). Jamais sur une
 * page privée, jamais deux fils d'Ariane, aucun type interdit (note, avis…).
 */
class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://talkami.net']);
    }

    /** @return list<array<string, mixed>> */
    private function jsonLd(string $uri): array
    {
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $this->get($uri)->assertOk()->rawContent(), $blocks);

        return array_map(fn (string $json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $blocks[1]);
    }

    /** @return array<string, mixed>|null */
    private function ofType(array $blocks, string $type): ?array
    {
        return collect($blocks)->firstWhere('@type', $type);
    }

    public function test_the_pages_missing_in_the_audit_now_describe_themselves_with_a_breadcrumb(): void
    {
        foreach (['/comment-ca-marche' => ['WebPage', 'Comment ça marche'], '/devenir-ambassadeur' => ['WebPage', 'Devenir ambassadeur'],
            '/contact' => ['ContactPage', 'Contact'], '/inscription' => ['WebPage', 'Inscription']] as $uri => [$type, $crumb]) {
            $blocks = $this->jsonLd($uri);
            $page = $this->ofType($blocks, $type);

            $this->assertNotNull($page, "{$uri} : {$type} attendu");
            $this->assertSame(Seo::url(ltrim($uri, '/')), $page['url']);
            $this->assertSame(['@id' => Seo::url().'#site'], $page['isPartOf']);
            $this->assertNotSame('', $page['name']);

            $trail = $this->ofType($blocks, 'BreadcrumbList');
            $this->assertSame(['Accueil', $crumb], array_column($trail['itemListElement'], 'name'), $uri);
            $this->assertSame([Seo::url(), Seo::url(ltrim($uri, '/'))], array_column($trail['itemListElement'], 'item'));
        }
    }

    public function test_every_public_page_has_exactly_one_breadcrumb_except_the_home_page(): void
    {
        $pages = ['/formules', '/a-propos', '/aide-aux-cours-d-anglais', '/preparer-un-entretien-en-anglais', '/confidentialite',
            '/apprendre-anglais/togo', '/guides', '/guides/'.Guides::slugs()[0]];

        foreach ($pages as $uri) {
            $trails = array_filter($this->jsonLd($uri), fn (array $block) => $block['@type'] === 'BreadcrumbList');
            $this->assertCount(1, $trails, $uri);
        }

        $this->assertNull($this->ofType($this->jsonLd('/'), 'BreadcrumbList'));
        $this->assertSame('AboutPage', $this->ofType($this->jsonLd('/a-propos'), 'AboutPage')['@type']);

        // Page pays : son titre dans le fil d'Ariane.
        $trail = $this->ofType($this->jsonLd('/apprendre-anglais/togo'), 'BreadcrumbList');
        $this->assertStringContainsString('Togo', $trail['itemListElement'][1]['name']);
    }

    public function test_how_it_works_describes_its_ready_videos(): void
    {
        Storage::fake('local');
        config(['filesystems.private_disk' => 'local']);
        $video = PresentationVideo::query()->create(['key' => 'evaluation', 'title' => 'Votre évaluation orale gratuite', 'status' => LessonVideo::READY, 'duration_seconds' => 95, 'ready_at' => now()]);
        Storage::disk('local')->put(SiteVideos::path($video, 'video'), str_repeat('v', 1048576));
        Storage::disk('local')->put(SiteVideos::path($video, 'apercu'), 'jpeg');
        $this->assertTrue(app(SiteVideos::class)->markReady($video->fresh()));

        $object = $this->ofType($this->jsonLd('/comment-ca-marche'), 'VideoObject');

        $this->assertSame('Votre évaluation orale gratuite', $object['name']);
        $this->assertSame(Seo::url('comment-ca-marche/evaluation/apercu'), $object['thumbnailUrl']);
        $this->assertSame(Seo::url('comment-ca-marche/evaluation/video'), $object['contentUrl']);
        $this->assertSame('PT1M35S', $object['duration']);
        $this->assertArrayHasKey('uploadDate', $object);
        $this->assertContains($object['@type'], Seo::SCHEMA_TYPES);
    }

    public function test_private_pages_have_no_structured_data(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertSame([], $this->jsonLd('/espace'));
    }
}
