<?php

namespace Tests\Feature\Videos;

use App\Models\PresentationVideo;
use App\Models\User;
use App\Support\Videos\PresentationScripts;
use App\Support\Videos\PresentationVideos;
use App\Support\Videos\VideoSettings;
use Database\Seeders\PresentationDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vidéos de présentation de Teacher Rose (étape 4 de « Teacher Rose partout ») : la grande et les six courtes, mêmes
 * écrans que celles de Teacher Joe, dites par elle (son nom, accords au féminin, son modèle 3D, sa voix, ton au
 * féminin), comptes de démonstration avec elle. Celles de Teacher Joe ne changent pas.
 */
class PresentationRoseTest extends TestCase
{
    use RefreshDatabase;

    private const KEYS = ['talkami', 'compte', 'evaluation', 'formules', 'paiement', 'seance', 'mes-cours'];

    public function test_one_long_and_six_short_videos_said_by_teacher_rose(): void
    {
        foreach (self::KEYS as $key) {
            $this->assertContains('rose-'.$key, PresentationScripts::keys());
            $this->assertSame('rose', PresentationScripts::teacher('rose-'.$key)->key);
            $this->assertSame('joe', PresentationScripts::teacher($key)->key);

            $rose = PresentationScripts::script('rose-'.$key, 'talkami.net');
            $joe = PresentationScripts::script($key, 'talkami.net');
            $spoken = implode(' ', array_column($rose['segments'], 'spoken'));

            // Mêmes écrans, même nombre de phrases.
            $this->assertSame(array_column($joe['segments'], 'scene'), array_column($rose['segments'], 'scene'));
            $this->assertStringNotContainsString('Teacher Joe', $spoken.' '.$rose['caption'].' '.$rose['title']);
            $this->assertDoesNotMatchRegularExpression('/le professeur d\'anglais|J\'ai été conçu et programmé/u', $spoken);
        }

        $intro = implode(' ', array_column(PresentationScripts::script('rose-talkami', 'talkami.net')['segments'], 'spoken'));
        $this->assertStringContainsString('Je suis Teacher Rose, la professeure d\'anglais de Talkami.', $intro);
        $this->assertStringContainsString('J\'ai été conçue et programmée par Joseph Ahiankonou', $intro);

        // Les vidéos de Teacher Joe : inchangées.
        $joe = implode(' ', array_column(PresentationScripts::script('talkami', 'talkami.net')['segments'], 'spoken'));
        $this->assertStringContainsString('Je suis Teacher Joe, le professeur d\'anglais de Talkami.', $joe);
        $this->assertNotContains('rose-questions', PresentationScripts::keys());
    }

    public function test_the_rendering_job_uses_her_model_her_tone_and_demo_accounts_with_her(): void
    {
        VideoSettings::saveSite('talkami.net');
        $videos = app(PresentationVideos::class);
        $videos->all();

        $rose = PresentationVideo::query()->where('key', 'rose-paiement')->firstOrFail();
        $rose->update(['script' => PresentationScripts::script('rose-paiement'), 'timings' => ['duration' => 30, 'segments' => []]]);
        $job = $videos->job($rose);

        $this->assertSame('rose', $job['teacher']);
        $this->assertSame('/teacher3d/models/teacher-rose.glb', $job['avatar']['url']);
        $this->assertStringContainsString('comme une professeure chaleureuse', $rose->voiceStyle());
        $this->assertSame('talkami-presentation-rose-paiement.mp4', $rose->downloadName());

        $joe = PresentationVideo::query()->where('key', 'paiement')->firstOrFail();
        $joe->update(['script' => PresentationScripts::script('paiement'), 'timings' => ['duration' => 30, 'segments' => []]]);
        $this->assertSame('joe', $videos->job($joe)['teacher']);
        $this->assertArrayNotHasKey('url', $videos->job($joe)['avatar']);
        $this->assertSame(config('videos.presentations.voice_style'), $joe->voiceStyle());
    }

    public function test_back_to_teacher_joe_alone_removes_her_versions(): void
    {
        config(['teachers.list.rose.ready' => false]);

        $this->assertEmpty(array_filter(PresentationScripts::keys(), fn ($key) => str_starts_with($key, 'rose-')));
    }

    public function test_demo_accounts_choose_her_only_for_her_videos(): void
    {
        config(['videos.presentations.demo_teacher' => 'rose']);
        $this->seed(PresentationDemoSeeder::class);

        $this->assertSame(0, User::query()->where('role', 'learner')->where(fn ($q) => $q->whereNull('teacher')->orWhere('teacher', '!=', 'rose'))->count());
        $this->assertSame(0, User::query()->where('role', 'admin')->where('teacher', 'rose')->count());
    }
}
