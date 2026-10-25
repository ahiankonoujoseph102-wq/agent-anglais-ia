<?php

namespace Tests\Feature;

use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Support\Videos\PresentationVideos;
use App\Support\Videos\SiteVideos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Page « Comment ça marche » : vidéos de présentation en version « site », chargées seulement au toucher sur
 * « Regarder » ; API de GitHub Actions (tools/presentation/site-version.mjs) ; lien d'une ligne sur l'accueil.
 */
class HowItWorksTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'jeton-de-test-des-videos-assez-long-1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['filesystems.private_disk' => 'local', 'videos.token' => self::TOKEN]);
    }

    private function readyVideo(string $key, bool $site = false): PresentationVideo
    {
        $video = PresentationVideo::query()->create(['key' => $key, 'title' => $key === 'talkami' ? 'Talkami en 5 minutes' : 'Votre évaluation orale gratuite', 'status' => LessonVideo::READY, 'duration_seconds' => 95]);
        $light = $video->folder().'/'.$video->downloadName(true);
        Storage::disk('local')->put($light, 'mp4-whatsapp');
        $video->update(['light_path' => $light, 'video_path' => $video->folder().'/'.$video->downloadName()]);

        if ($site) {
            Storage::disk('local')->put(SiteVideos::path($video, 'video'), str_repeat('v', 3 * 1048576));
            Storage::disk('local')->put(SiteVideos::path($video, 'apercu'), 'jpeg');
            $this->assertTrue(app(SiteVideos::class)->markReady($video));
        }

        return $video->fresh();
    }

    private function api(string $method, string $uri, array $data = [])
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.self::TOKEN, 'Accept' => 'application/json'])->json($method, $uri, $data);
    }

    public function test_the_page_shows_the_short_video_first_then_the_long_one_without_loading_any_video(): void
    {
        $this->readyVideo('talkami', true);
        $this->readyVideo('evaluation', true);

        $html = $this->get('/comment-ca-marche')->assertOk()->assertHeaderMissing('X-Robots-Tag')->getContent();

        $this->assertLessThan(strpos($html, 'Talkami en 5 minutes'), strpos($html, 'Votre évaluation orale gratuite'));
        $this->assertSame(2, substr_count($html, 'data-watch'));
        $this->assertStringContainsString('href="'.route('how.video', 'evaluation').'"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('Regarder', $html);
        $this->assertStringContainsString('environ 3 mégaoctets', $html);
        $this->assertStringContainsString("Rien n'est téléchargé avant que vous touchiez « Regarder ».", $html);
        $this->assertStringContainsString('js/watch-video.js', $html);
        // Aucune vidéo dans la page avant le toucher : ni balise vidéo, ni adresse du bucket.
        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringNotContainsString('-site.mp4', $html);
        $this->assertSame(1, substr_count($html, 'btn-accent'));
        $this->assertDoesNotMatchRegularExpression('/\b(Gemini|OpenAI|ChatGPT|intelligence artificielle)\b/i', strip_tags($html));
    }

    public function test_without_a_site_version_the_page_shows_only_the_steps(): void
    {
        $this->readyVideo('evaluation');

        $this->get('/comment-ca-marche')->assertOk()
            ->assertSee('En quatre étapes')
            ->assertDontSee('data-watch', false)
            ->assertDontSee('watch-video.js', false);
        $this->get('/comment-ca-marche/evaluation/video')->assertNotFound();
        $this->get('/comment-ca-marche/evaluation/apercu')->assertNotFound();
    }

    public function test_poster_and_video_are_served_only_for_ready_site_videos(): void
    {
        $this->readyVideo('evaluation', true);
        $this->readyVideo('paiement', false);

        $this->get('/comment-ca-marche/evaluation/apercu')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertContains($this->get('/comment-ca-marche/evaluation/video')->getStatusCode(), [200, 302]);
        $this->get('/comment-ca-marche/paiement/video')->assertNotFound();
        $this->get('/comment-ca-marche/inconnue/video')->assertNotFound();
    }

    public function test_github_gets_jobs_uploads_both_files_and_marks_the_site_version_ready(): void
    {
        $video = $this->readyVideo('evaluation');
        $this->readyVideo('paiement');

        $this->getJson('/api/presentations/site/travaux')->assertUnauthorized();
        $this->api('GET', '/api/presentations/site/travaux?apercu=1')->assertOk()->assertJson(['waiting' => 1]);
        $jobs = $this->api('GET', '/api/presentations/site/travaux')->assertOk()->json('jobs');

        $this->assertCount(1, $jobs);
        $this->assertSame('evaluation', $jobs[0]['key']);
        $this->assertSame([480, 854], [$jobs[0]['width'], $jobs[0]['height']]);
        $this->assertArrayHasKey('video', $jobs[0]['upload']);
        $this->assertArrayHasKey('poster', $jobs[0]['upload']);

        $this->api('POST', route('api.presentations.site.done', $video, false))->assertStatus(422);

        $put = fn (string $file, string $body) => $this->call('PUT', route('api.presentations.site.upload', ['presentation' => $video, 'file' => $file]), [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN, 'CONTENT_LENGTH' => (string) strlen($body)], $body);
        $put('video', 'mp4-du-site')->assertOk();
        $put('apercu', 'jpeg')->assertOk();
        $this->api('POST', route('api.presentations.site.done', $video, false))->assertOk()->assertJson(['ready' => true]);

        $video->refresh();
        $this->assertSame(SiteVideos::path($video, 'video'), $video->web_path);
        $this->assertSame(SiteVideos::path($video, 'apercu'), $video->poster_path);
        $this->api('GET', '/api/presentations/site/travaux?apercu=1')->assertJson(['waiting' => 0]);
    }

    public function test_a_redone_video_loses_its_old_site_version(): void
    {
        $video = $this->readyVideo('evaluation', true);
        $video->update(['status' => LessonVideo::RENDERING]);
        Storage::disk('local')->put($video->video_path, 'mp4-tiktok');

        $this->assertTrue(app(PresentationVideos::class)->markReady($video->fresh(), 95.0));

        $video->refresh();
        $this->assertNull($video->web_path);
        $this->assertNull($video->poster_path);
        Storage::disk('local')->assertMissing(SiteVideos::path($video, 'video'));
        $this->get('/comment-ca-marche')->assertDontSee('data-watch', false);
    }

    public function test_the_home_page_links_to_the_page_with_one_line_of_text(): void
    {
        $this->get('/')->assertOk()->assertSee('<p class="hero-how"><a href="'.route('how').'">Voir comment ça marche</a></p>', false);
    }
}
