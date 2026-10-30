<?php

namespace Tests\Feature\Videos;

use App\Mail\VideoReadyMail;
use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Models\User;
use App\Support\MailQuota;
use App\Support\Videos\LessonVideos;
use App\Support\Videos\VideoMailer;
use App\Support\Videos\VideoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Vidéos de promotion et du Club envoyées par e-mail au fondateur, puis retirées du stockage : jamais avant que
 * l'e-mail soit parti, jamais les vidéos d'avant ce changement.
 */
class VideoMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local', ['serve' => true]);
        config([
            'filesystems.private_disk' => 'local',
            'teachers.list.rose.ready' => false,
            'mail.default' => 'smtp',
            'mail.from.address' => 'equipe@example.test',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.username' => 'utilisateur-smtp',
            'mail.mailers.smtp.password' => 'mot-de-passe-smtp',
        ]);
    }

    /** Une vidéo prête, fabriquée par GitHub : vidéo, version e-mail et voix dans son dossier. */
    private function readyVideo(string $kind = LessonVideo::PROMO, bool $withMail = true): LessonVideo
    {
        $video = LessonVideo::query()->create([
            'kind' => $kind,
            'topic_key' => $kind === LessonVideo::CLUB ? 'club-'.today()->toDateString() : 'telephone-repondre',
            'theme' => $kind === LessonVideo::CLUB ? 'dialogue' : null,
            'day' => today()->toDateString(),
            'slot' => $kind === LessonVideo::CLUB ? 0 : 1,
            'level' => 'A2',
            'title' => 'Répondre au téléphone en anglais',
            'status' => LessonVideo::RENDERING,
            'script' => ['question' => ['spoken' => 'Comment répondez-vous au téléphone ?', 'subtitle' => '…']],
            'caption' => "Répondez au téléphone en anglais sans stress.\n\n#anglais #talkami",
            'audio_path' => 'videos/'.today()->toDateString().'/x/voice.wav',
        ]);
        $video->update(['audio_path' => $video->folder().'/voice.wav']);

        $disk = Storage::disk('local');
        $disk->put($video->audio_path, 'voix');
        $disk->put(LessonVideos::videoPath($video), str_repeat('v', 2000));

        if ($withMail) {
            $disk->put($video->mailPath(), str_repeat('m', 500));
        }

        app(LessonVideos::class)->markReady($video->fresh(), 130.0);

        return $video->fresh();
    }

    public function test_a_ready_video_is_mailed_with_its_light_version_then_removed_from_storage(): void
    {
        Mail::fake();
        VideoSettings::saveKeepDays(0);
        $video = $this->readyVideo();

        $this->assertSame($video->mailPath(), $video->mail_path);
        $this->assertSame(500, $video->mail_size_bytes);

        $this->artisan('videos:mail')->assertSuccessful();

        Mail::assertSent(VideoReadyMail::class, function (VideoReadyMail $mail) use ($video) {
            $html = $mail->render();

            return $mail->hasTo('amitalk62@gmail.com')
                && $mail->withAttachment
                && $mail->attachmentPath === $video->mailPath()
                && str_contains($mail->envelope()->subject, 'Vidéo de promotion')
                && str_contains($html, 'Texte à publier avec la vidéo')
                && str_contains($html, 'Répondez au téléphone en anglais sans stress.')
                && str_contains($html, 'Comment répondez-vous au téléphone ?')
                && str_contains($html, $video->title);
        });

        $video->refresh();
        $this->assertSame(LessonVideo::MAIL_SENT, $video->mail_status);
        $this->assertNotNull($video->purged_at);
        $this->assertNull($video->video_path);
        $this->assertNull($video->audio_path);
        $this->assertSame([], Storage::disk('local')->allFiles($video->folder()));
        // Le texte à publier et le script restent dans la page « Vidéos ».
        $this->assertNotNull($video->caption);
        $this->assertSame(1, MailQuota::videosToday());

        // Une seule fois.
        $this->artisan('videos:mail')->assertSuccessful();
        Mail::assertSentCount(1);
    }

    public function test_the_full_quality_stays_for_the_chosen_days_with_a_link_then_is_removed(): void
    {
        Mail::fake();
        $this->assertSame(7, VideoSettings::keepDays());
        $video = $this->readyVideo(LessonVideo::CLUB);

        $this->artisan('videos:mail')->assertSuccessful();
        Mail::assertSent(VideoReadyMail::class, fn (VideoReadyMail $mail) => $mail->link !== null && $mail->keepUntil !== null
            && str_contains($mail->envelope()->subject, 'Vidéo du Club') && str_contains($mail->render(), 'Télécharger en pleine qualité'));

        $video->refresh();
        // La version e-mail est effacée tout de suite, la vidéo et sa voix restent jusqu'à la fin de la garde.
        $this->assertNull($video->mail_path);
        $this->assertFalse(Storage::disk('local')->exists($video->mailPath()));
        $this->assertTrue(Storage::disk('local')->exists($video->video_path));
        $this->assertNull($video->purged_at);

        $this->travel(6)->days();
        $this->artisan('videos:mail')->assertSuccessful();
        $this->assertNull($video->fresh()->purged_at);

        $this->travel(2)->days();
        $this->artisan('videos:mail')->assertSuccessful();
        $this->assertNotNull($video->fresh()->purged_at);
        $this->assertSame([], Storage::disk('local')->allFiles($video->folder()));
    }

    public function test_nothing_is_removed_when_the_mail_fails_and_it_is_tried_again(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('552 Mail size too large'));
        $video = $this->readyVideo();

        $this->artisan('videos:mail')->assertSuccessful();

        $video->refresh();
        $this->assertNull($video->mail_status);
        $this->assertSame(1, $video->mail_attempts);
        $this->assertStringContainsString('552', (string) $video->mail_error);
        $this->assertNull($video->purged_at);
        $this->assertTrue(Storage::disk('local')->exists($video->video_path));
        $this->assertTrue(Storage::disk('local')->exists($video->mailPath()));

        // Après tous les essais : « en échec », toujours rien d'effacé, « Renvoyer l'e-mail ».
        $video->update(['mail_attempts' => 4, 'next_mail_at' => null]);
        app(VideoMailer::class)->run();
        $video->refresh();
        $this->assertSame(LessonVideo::MAIL_FAILED, $video->mail_status);
        $this->assertTrue(Storage::disk('local')->exists($video->video_path));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.videos.index'))->assertOk()->assertSee('E-mail en échec')->assertSee('Renvoyer l\'e-mail', false);
        $this->actingAs($admin)->post(route('admin.videos.mail', $video))->assertRedirect();
        $this->assertNull($video->fresh()->mail_status);
        $this->assertSame(0, $video->fresh()->mail_attempts);
    }

    public function test_without_a_light_version_the_mail_carries_the_link_only(): void
    {
        Mail::fake();
        $video = $this->readyVideo(LessonVideo::PROMO, withMail: false);

        app(VideoMailer::class)->run();

        Mail::assertSent(VideoReadyMail::class, fn (VideoReadyMail $mail) => ! $mail->withAttachment && $mail->attachments() === [] && $mail->link !== null);
        $this->assertSame(LessonVideo::MAIL_SENT, $video->fresh()->mail_status);
    }

    public function test_nothing_is_sent_or_removed_while_mail_is_not_set_up(): void
    {
        Mail::fake();
        config(['mail.mailers.smtp.password' => null]);
        VideoSettings::saveKeepDays(0);
        $video = $this->readyVideo();

        $this->artisan('videos:mail')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNull($video->fresh()->purged_at);
        $this->assertTrue(Storage::disk('local')->exists($video->video_path));
    }

    public function test_videos_ready_before_the_change_are_never_mailed(): void
    {
        Mail::fake();
        $video = $this->readyVideo();
        $video->update(['mail_status' => LessonVideo::MAIL_SKIPPED]);

        app(VideoMailer::class)->run();

        Mail::assertNothingSent();
        $this->assertTrue(Storage::disk('local')->exists($video->video_path));
    }

    public function test_the_admin_page_shows_the_address_and_the_keep_setting(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $video = $this->readyVideo();
        app(VideoMailer::class)->run();

        $this->actingAs($admin)->get(route('admin.videos.index'))->assertOk()
            ->assertSee('Envoi des vidéos par e-mail')
            ->assertSee('am*******@gmail.com')
            ->assertDontSee('amitalk62@gmail.com')
            ->assertSee('Envoyée par e-mail le');

        $this->actingAs($admin)->put(route('admin.videos.keep-days'), ['keep_days' => 0])->assertRedirect(route('admin.videos.index').'#videos-email');
        $this->assertSame(0, VideoSettings::keepDays());
        $this->actingAs($admin)->put(route('admin.videos.keep-days'), ['keep_days' => 30])->assertSessionHasErrors('keep_days');
        $this->actingAs(User::factory()->create())->put(route('admin.videos.keep-days'), ['keep_days' => 1])->assertForbidden();

        // « Refaire la vidéo » d'une vidéo effacée : une nouvelle voix est préparée, puis elle repart par e-mail.
        VideoSettings::saveKeepDays(0);
        $other = $this->readyVideo(LessonVideo::CLUB);
        app(VideoMailer::class)->run();
        $this->assertNotNull($video->fresh()->purged_at);
        $this->assertTrue(app(LessonVideos::class)->redo($video->fresh()));
        $this->assertSame(LessonVideo::SCRIPTED, $video->fresh()->status);
        $this->assertNotNull($other->fresh()->purged_at);
    }

    public function test_github_receives_the_light_version_target(): void
    {
        $video = LessonVideo::query()->create(['kind' => LessonVideo::PROMO, 'topic_key' => 'a', 'day' => today()->toDateString(), 'slot' => 1, 'level' => 'A2', 'title' => 'T', 'status' => LessonVideo::VOICED, 'script' => ['segments' => []]]);
        $job = app(LessonVideos::class)->job($video);

        $this->assertSame((int) floor(3.8 * 1024 * 1024), $job['mail']['max_bytes']);
        $this->assertSame([540, 960, 48], [$job['mail']['width'], $job['mail']['height'], $job['mail']['audio_kbps']]);
        $this->assertSame('PUT', $job['mail']['upload']['method']);
    }

    public function test_the_storage_cleanup_only_lists_until_the_founder_confirms(): void
    {
        $disk = Storage::disk('local');
        $old = $this->readyVideo();
        $old->update(['mail_status' => LessonVideo::MAIL_SKIPPED]);
        $pending = $this->readyVideo(LessonVideo::CLUB);
        $disk->put('videos/2026-10-01/999/perdu.mp4', str_repeat('o', 100));
        $disk->put('catalogue/talkami-catalogue-des-ecrans.mp4', str_repeat('c', 300));

        $site = PresentationVideo::query()->create(['key' => 'evaluation', 'title' => 'Évaluation', 'status' => LessonVideo::READY, 'video_path' => 'presentations/evaluation/tiktok.mp4', 'light_path' => 'presentations/evaluation/whatsapp.mp4', 'web_path' => 'presentations/evaluation/site.mp4', 'poster_path' => 'presentations/evaluation/apercu.jpg', 'audio_path' => 'presentations/evaluation/voice.wav']);
        $waiting = PresentationVideo::query()->create(['key' => 'rose-evaluation', 'title' => 'Évaluation avec Teacher Rose', 'status' => LessonVideo::READY, 'video_path' => 'presentations/rose-evaluation/tiktok.mp4', 'light_path' => 'presentations/rose-evaluation/whatsapp.mp4']);
        foreach (['evaluation/tiktok.mp4', 'evaluation/whatsapp.mp4', 'evaluation/site.mp4', 'evaluation/apercu.jpg', 'evaluation/voice.wav', 'rose-evaluation/tiktok.mp4', 'rose-evaluation/whatsapp.mp4'] as $file) {
            $disk->put('presentations/'.$file, 'x');
        }
        config(['teachers.list.rose.ready' => true]);

        // Simulation : rien n'est effacé.
        $this->artisan('videos:storage')->assertSuccessful()->expectsOutputToContain('Rien n\'a été effacé')->expectsOutputToContain('SUPPRIMER 8 FICHIERS');
        $this->assertTrue($disk->exists($old->video_path));

        // Mauvaise phrase : rien n'est effacé.
        $this->artisan('videos:storage', ['--delete' => true, '--confirm' => 'oui'])->assertFailed();
        $this->assertTrue($disk->exists($old->video_path));

        $this->artisan('videos:storage', ['--delete' => true, '--confirm' => 'SUPPRIMER 8 FICHIERS'])->assertSuccessful();

        $this->assertSame([], $disk->allFiles($old->folder()));
        $this->assertNotNull($old->fresh()->purged_at);
        $this->assertFalse($disk->exists('videos/2026-10-01/999/perdu.mp4'));
        $this->assertFalse($disk->exists('catalogue/talkami-catalogue-des-ecrans.mp4'));
        // Gardés : la vidéo pas encore envoyée, la version « site » et la voix, la version WhatsApp qui attend sa version « site ».
        $this->assertTrue($disk->exists($pending->video_path));
        $this->assertTrue($disk->exists('presentations/evaluation/site.mp4'));
        $this->assertTrue($disk->exists('presentations/evaluation/apercu.jpg'));
        $this->assertTrue($disk->exists('presentations/evaluation/voice.wav'));
        $this->assertTrue($disk->exists('presentations/rose-evaluation/whatsapp.mp4'));
        $this->assertFalse($disk->exists('presentations/evaluation/tiktok.mp4'));
        $this->assertNull($site->fresh()->video_path);
        $this->assertNull($site->fresh()->light_path);
        $this->assertSame('presentations/rose-evaluation/whatsapp.mp4', $waiting->fresh()->light_path);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.videos.index'))->assertOk();
    }
}
