<?php

namespace Tests\Feature\Teachers;

use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Support\Videos\SiteVideos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Teacher Rose visible dès l'arrivée, sans toucher à la marque : petite pastille dans le coin de l'image du haut de
 * l'accueil (titre et Teacher Joe inchangés), et UNE vidéo d'elle sur « Comment ça marche » (son évaluation).
 */
class RoseOnFirstScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['filesystems.private_disk' => 'local']);
    }

    private function siteVideo(string $key, string $title): void
    {
        $video = PresentationVideo::query()->create(['key' => $key, 'title' => $title, 'status' => LessonVideo::READY, 'duration_seconds' => 95]);
        $light = $video->folder().'/'.$video->downloadName(true);
        Storage::disk('local')->put($light, 'mp4-whatsapp');
        $video->update(['light_path' => $light, 'video_path' => $video->folder().'/'.$video->downloadName()]);
        Storage::disk('local')->put(SiteVideos::path($video, 'video'), str_repeat('v', 1048576));
        Storage::disk('local')->put(SiteVideos::path($video, 'apercu'), 'jpeg');
        $this->assertTrue(app(SiteVideos::class)->markReady($video));
    }

    public function test_the_home_page_keeps_teacher_joe_and_adds_a_small_badge_for_teacher_rose(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $hero = substr($html, strpos($html, 'class="hero-media'), 2000);

        // Titre et image de Teacher Joe inchangés.
        $this->assertStringContainsString('Parlez anglais avec Teacher Joe, chaque jour', strip_tags($html));
        $this->assertStringContainsString('images/teacher-joe.webp', $hero);
        // Pastille de Teacher Rose dans l'image, vers la section des professeurs (qui existe).
        $this->assertStringContainsString('<a class="hero-alt" href="#professeurs">', $hero);
        $this->assertStringContainsString('images/teacher-rose.webp', $hero);
        $this->assertStringContainsString('ou Teacher Rose,<br>au choix', $hero);
        $this->assertStringContainsString('id="professeurs"', $html);
        // Toujours un seul bouton orange dans le haut de page.
        $this->assertSame(1, substr_count(substr($html, strpos($html, 'class="hero'), strpos($html, 'class="hero-media') - strpos($html, 'class="hero')), 'btn-accent'));
    }

    public function test_back_to_teacher_joe_alone_removes_the_badge(): void
    {
        config(['teachers.list.rose.ready' => false]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('hero-alt', $html);
        $this->assertStringNotContainsString('teacher-rose.webp', $html);
    }

    public function test_the_badge_adds_no_animation_and_stays_in_the_picture(): void
    {
        $css = file_get_contents(public_path('css/app.css'));
        $block = substr($css, strpos($css, '.hero-alt {'));
        $block = substr($block, 0, strpos($block, "\n}\n") ?: strlen($block));

        $this->assertStringContainsString('position: absolute', $block);
        $this->assertStringNotContainsString('animation', $block);
    }

    public function test_how_it_works_shows_teacher_rose_evaluation_next_to_teacher_joe_ones(): void
    {
        $this->siteVideo('evaluation', 'Votre évaluation orale gratuite');
        $this->siteVideo('rose-evaluation', 'Votre évaluation orale gratuite');
        $this->siteVideo('talkami', 'Talkami en 5 minutes');

        $html = $this->get('/comment-ca-marche')->assertOk()->getContent();

        $this->assertStringContainsString('Votre évaluation orale gratuite avec Teacher Rose', $html);
        $this->assertStringContainsString('La même évaluation orale gratuite, avec Teacher Rose', $html);
        // Juste après celle de Teacher Joe, avant « Talkami en 5 minutes ».
        $this->assertLessThan(strpos($html, 'id="rose-evaluation"'), strpos($html, 'id="evaluation"'));
        $this->assertLessThan(strpos($html, 'id="talkami"'), strpos($html, 'id="rose-evaluation"'));
        // Version allégée : aperçu différé, aucune vidéo avant le toucher sur « Regarder ».
        $this->assertSame(3, substr_count($html, 'data-watch'));
        $this->assertStringContainsString('href="'.route('how.video', 'rose-evaluation').'"', $html);
        $this->assertStringNotContainsString('<video', $html);
        $this->get(route('how.poster', 'rose-evaluation'))->assertOk();
    }

    public function test_teacher_rose_video_is_hidden_until_ready_or_when_she_is_not_offered(): void
    {
        $this->siteVideo('evaluation', 'Votre évaluation orale gratuite');

        // Pas encore prête : rien.
        $this->assertStringNotContainsString('rose-evaluation', $this->get('/comment-ca-marche')->getContent());

        $this->siteVideo('rose-evaluation', 'Votre évaluation orale gratuite');
        config(['teachers.list.rose.ready' => false]);

        // Teacher Rose plus proposée : sa vidéo disparaît, l'adresse aussi.
        $this->assertStringNotContainsString('rose-evaluation', $this->get('/comment-ca-marche')->getContent());
        $this->assertNotContains('rose-evaluation', SiteVideos::keys());
        $this->get(route('how.poster', 'rose-evaluation'))->assertNotFound();
    }
}
