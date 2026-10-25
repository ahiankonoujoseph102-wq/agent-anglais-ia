<?php

namespace Tests\Feature\Videos;

use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Models\User;
use App\Support\Videos\PresentationScripts;
use App\Support\Videos\PresentationVideos;
use App\Support\Videos\SiteVideos;
use App\Support\Videos\VideoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Une vidéo de présentation prête le reste : « Préparer les vidéos pas encore prêtes » (toutes), la préparation de
 * chaque minute et les mises à jour ne la remettent jamais en « Voix prête ». Correction du 25 octobre 2026.
 */
class PresentationReadyStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['filesystems.private_disk' => 'local']);
        VideoSettings::saveSite('talkami.laravel.cloud');
    }

    /** Toutes les vidéos de présentation prêtes, avec leurs deux fichiers et leur voix. */
    private function readyVideos(): void
    {
        foreach (app(PresentationVideos::class)->all() as $video) {
            $script = PresentationScripts::script($video->key);
            $full = PresentationVideos::path($video, false);
            $light = PresentationVideos::path($video, true);
            Storage::disk('local')->put($full, 'mp4');
            Storage::disk('local')->put($light, 'mp4');
            Storage::disk('local')->put($video->folder().'/voice.wav', 'wav');
            $video->update([
                'status' => LessonVideo::READY, 'script' => $script, 'script_hash' => PresentationScripts::hash($script),
                'audio_path' => $video->folder().'/voice.wav', 'video_path' => $full, 'light_path' => $light, 'ready_at' => now(),
            ]);
        }
    }

    public function test_preparing_all_videos_never_touches_a_ready_one(): void
    {
        $this->readyVideos();
        $before = PresentationVideo::query()->count();

        $this->assertSame(0, app(PresentationVideos::class)->request());
        app(PresentationVideos::class)->prepare(microtime(true) + 60);

        $this->assertSame($before, PresentationVideo::query()->where('status', LessonVideo::READY)->count());
        $this->assertSame(0, PresentationVideo::query()->where('status', LessonVideo::VOICED)->count());

        // La vidéo du site peut toujours être préparée à partir de ces versions.
        $this->assertCount(count(SiteVideos::keys()), app(SiteVideos::class)->waiting());
    }

    public function test_the_admin_button_says_nothing_was_redone(): void
    {
        $this->readyVideos();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.videos.presentations.request'))
            ->assertRedirect()
            ->assertSessionHas('status', 'Toutes les vidéos de présentation sont déjà prêtes ou en fabrication : rien n\'a été refait. Pour en refaire une, touchez « Refaire cette vidéo » sous elle.');
        $this->assertSame(0, PresentationVideo::query()->where('status', LessonVideo::VOICED)->count());
    }

    public function test_redoing_one_video_still_works_and_leaves_the_others_ready(): void
    {
        $this->readyVideos();

        $this->assertSame(1, app(PresentationVideos::class)->request('paiement'));
        $this->assertSame(LessonVideo::VOICED, PresentationVideo::query()->where('key', 'paiement')->value('status'));
        $this->assertSame(0, PresentationVideo::query()->where('key', '!=', 'paiement')->where('status', '!=', LessonVideo::READY)->count());
    }

    public function test_a_video_not_yet_made_is_still_prepared_by_the_button(): void
    {
        $this->readyVideos();
        PresentationVideo::query()->where('key', 'compte')->update(['status' => LessonVideo::PLANNED, 'video_path' => null, 'light_path' => null, 'ready_at' => null]);

        $this->assertSame(1, app(PresentationVideos::class)->request());
        $this->assertSame(LessonVideo::VOICED, PresentationVideo::query()->where('key', 'compte')->value('status'));
    }

    public function test_the_migration_puts_back_ready_the_videos_reset_by_mistake(): void
    {
        $this->readyVideos();
        // État trouvé en production : toutes remises en « Voix prête », anciens fichiers encore là.
        PresentationVideo::query()->update(['status' => LessonVideo::VOICED]);
        // Une vidéo dont les fichiers ont disparu reste à fabriquer ; une jamais faite aussi.
        $gone = PresentationVideo::query()->where('key', 'formules')->firstOrFail();
        Storage::disk('local')->delete($gone->light_path);
        PresentationVideo::query()->where('key', 'compte')->update(['video_path' => null, 'light_path' => null, 'ready_at' => null]);

        $migration = require database_path('migrations/2026_11_08_000001_restore_ready_presentation_videos.php');
        $migration->up();

        $this->assertSame(LessonVideo::VOICED, PresentationVideo::query()->where('key', 'formules')->value('status'));
        $this->assertSame(LessonVideo::VOICED, PresentationVideo::query()->where('key', 'compte')->value('status'));
        $this->assertSame(LessonVideo::READY, PresentationVideo::query()->where('key', 'evaluation')->value('status'));
        $this->assertSame(LessonVideo::READY, PresentationVideo::query()->where('key', 'talkami')->value('status'));
        $this->assertSame(PresentationVideo::query()->count() - 2, PresentationVideo::query()->where('status', LessonVideo::READY)->count());

        // Relancée (nouveau déploiement) : rien ne change.
        $migration->up();
        $this->assertSame(PresentationVideo::query()->count() - 2, PresentationVideo::query()->where('status', LessonVideo::READY)->count());
    }
}
