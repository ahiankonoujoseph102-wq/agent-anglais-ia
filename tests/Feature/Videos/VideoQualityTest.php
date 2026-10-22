<?php

namespace Tests\Feature\Videos;

use App\Models\LessonVideo;
use App\Models\User;
use App\Support\PrivateStorage;
use App\Support\Speech\SpeechProviders;
use App\Support\Videos\LessonVideos;
use App\Support\Videos\VideoEnding;
use App\Support\Videos\VideoSettings;
use App\Support\Videos\VideoTopics;
use App\Support\Videos\VideoVoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Qualité des vidéos : fin imposée (question du jour, abonnement, adresse du site), adresse du site réglée dans la
 * page « Vidéos » (sinon fabrication bloquée), « Refaire la vidéo » (même sujet, même script, même voix), mesures des
 * lèvres et du son, essai comparatif des voix, nom du professeur en filigrane.
 */
class VideoQualityTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'jeton-de-test-des-videos-assez-long-1234567890';

    /** Nombre de demandes de synthèse vocale reçues. */
    private int $speechRequests = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Sleep::fake();
        config([
            'filesystems.private_disk' => 'local',
            'services.gemini.key' => 'cle-gemini',
            'services.openai.key' => null,
            'services.text.providers' => ['gemini'],
            'videos.voice.providers' => ['gemini'],
            'videos.token' => self::TOKEN,
        ]);

        Http::fake([
            '*/interactions' => function (Request $request) {
                if (($request['response_format']['type'] ?? null) === 'audio') {
                    $this->speechRequests++;

                    return Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'audio', 'mime_type' => 'audio/l16;codec=pcm;rate=24000', 'data' => base64_encode(str_repeat("\x10\x00", 12000))]]]]]);
                }

                $text = json_encode(['question' => ['spoken' => 'Écrivez en commentaire, en anglais : I am from…', 'subtitle' => 'Écrivez en commentaire : [en]I am from…[/en]']]);

                return Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $text]]]]]);
            },
        ]);
    }

    /** Une vidéo prête, faite avant la règle de fin : 4 parties de leçon puis l'ancienne phrase de fin. */
    private function oldReadyVideo(): LessonVideo
    {
        $video = LessonVideo::query()->create([
            'topic_key' => 'se-presenter', 'day' => today()->toDateString(), 'slot' => 1, 'level' => 'A1', 'title' => 'Se présenter',
            'status' => LessonVideo::READY, 'voice_provider' => 'gemini', 'voice_name' => 'Orus',
            'script' => ['title' => 'Se présenter', 'segments' => [
                ['spoken' => 'Un.', 'subtitle' => 'Un.'], ['spoken' => 'Deux.', 'subtitle' => 'Deux.'],
                ['spoken' => 'Trois.', 'subtitle' => 'Trois.'], ['spoken' => 'Quatre.', 'subtitle' => 'Quatre.'],
                ['spoken' => 'Ancienne fin.', 'subtitle' => 'Ancienne fin.', 'closing' => true],
            ]],
            'caption' => 'Ancienne légende', 'hashtags' => '#anglais',
        ]);

        // Voix d'origine : 5 parties d'une seconde, séparées de 0,45 seconde.
        $second = str_repeat("\x20\x00", 24000);
        $gap = str_repeat("\0", (int) (48000 * 0.45));
        $timings = [];
        $data = '';

        for ($i = 0; $i < 5; $i++) {
            $data .= $i > 0 ? $gap : '';
            $start = strlen($data) / 48000;
            $data .= $second;
            $timings[] = ['start' => round($start, 3), 'end' => round(strlen($data) / 48000, 3)];
        }

        $video->update(['audio_path' => $video->folder().'/voice.wav', 'timings' => ['segments' => $timings, 'duration' => $timings[4]['end']], 'video_path' => $video->folder().'/ancienne.mp4']);
        Storage::disk('local')->put($video->audio_path, VideoVoice::wav($data, 24000, 1));
        Storage::disk('local')->put($video->video_path, 'ancienne-video');

        return $video->fresh();
    }

    public function test_without_a_site_address_nothing_is_prepared_nor_rendered_and_the_reason_is_shown(): void
    {
        app(LessonVideos::class)->plan(today(), 1);
        app(LessonVideos::class)->prepare(3600);
        $this->assertSame(LessonVideo::PLANNED, LessonVideo::query()->value('status'));
        Http::assertNothingSent();

        $this->getJson(route('api.videos.jobs', ['apercu' => 1]), ['Authorization' => 'Bearer '.self::TOKEN])
            ->assertOk()->assertJson(['waiting' => 0])->assertJsonPath('blocked', fn ($message) => str_contains($message, 'adresse du site'));

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.videos.index'))
            ->assertOk()->assertSee('Fabrication bloquée')->assertSee('Adresse du site dans les vidéos');
    }

    public function test_the_site_address_is_saved_from_the_videos_page_and_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.videos.site'), ['site' => 'https://Talkami.net/accueil'])->assertRedirect();
        $this->assertSame('talkami.net', VideoSettings::site());

        $this->put(route('admin.videos.site'), ['site' => 'pas une adresse'])->assertSessionHasErrors('site');
        $this->assertSame('talkami.net', VideoSettings::site());

        $this->put(route('admin.videos.site'), ['site' => ''])->assertRedirect();
        $this->assertNull(VideoSettings::site());
        $this->assertNotNull(VideoSettings::blocked());
    }

    public function test_the_ending_is_question_then_subscribe_then_the_site(): void
    {
        $ending = VideoEnding::segments(['spoken' => 'Écrivez votre prénom.', 'subtitle' => 'Écrivez votre prénom.'], 'talkami.net');

        $this->assertSame(['question', 'subscribe', 'closing'], array_column($ending, 'kind'));
        $this->assertSame('Et pour aller plus loin, évaluez gratuitement votre niveau d\'anglais sur talkami.net.', $ending[2]['subtitle']);
        $this->assertStringEndsWith('talkami point net.', $ending[2]['spoken']);

        // Environ 10 à 12 secondes à un débit calme : avec une question de 12 mots au plus, une trentaine de mots en tout.
        $words = fn (string $text) => count(preg_split('/\s+/u', trim($text)));
        $this->assertLessThanOrEqual(26, $words($ending[1]['spoken']) + $words($ending[2]['spoken']));
    }

    public function test_redo_keeps_the_topic_the_script_and_the_voice_and_only_redoes_the_ending(): void
    {
        VideoSettings::saveSite('talkami.laravel.cloud');
        VideoSettings::saveVoice('gemini', 'Charon');
        $video = $this->oldReadyVideo();
        $remaining = VideoTopics::remaining();

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.videos.redo', $video))->assertRedirect();
        $this->assertSame(LessonVideo::SCRIPTED, $video->fresh()->status);

        app(LessonVideos::class)->prepare(3600);
        $video->refresh();

        $this->assertSame(LessonVideo::VOICED, $video->status);
        $this->assertSame($remaining, VideoTopics::remaining());
        $this->assertSame('se-presenter', $video->topic_key);
        // Même voix qu'à l'origine (pas la voix choisie depuis) ; seules les 3 parties de la fin sont dites.
        $this->assertSame(['gemini', 'Orus'], [$video->voice_provider, $video->voice_name]);
        $this->assertSame(3, $this->speechRequests);
        Http::assertSent(fn (Request $request) => ($request['response_format']['type'] ?? null) === 'audio' && $request['generation_config']['speech_config'][0]['voice'] === 'Orus');

        // Corps gardé (4 parties), nouvelle fin (3 parties), heures recalculées.
        $this->assertSame(['Un.', 'Deux.', 'Trois.', 'Quatre.'], array_column(array_slice($video->script['segments'], 0, 4), 'spoken'));
        $this->assertSame(['question', 'subscribe', 'closing'], array_column(array_slice($video->script['segments'], 4), 'kind'));
        $this->assertCount(7, $video->timings['segments']);
        $this->assertEqualsWithDelta(4 + 3 * 0.45 + 3 * 0.45 + 3 * 0.5, $video->timings['duration'], 0.01);
        $this->assertTrue(VideoEnding::isCurrent($video->script));

        // L'ancienne vidéo reste visible jusqu'à l'arrivée de la nouvelle.
        $this->assertTrue(Storage::disk('local')->exists($video->video_path));
    }

    public function test_redo_today_and_github_receives_the_new_render_with_the_watermark(): void
    {
        VideoSettings::saveSite('talkami.laravel.cloud');
        $video = $this->oldReadyVideo();
        LessonVideo::query()->create(['topic_key' => 'saluer', 'day' => today()->subDay()->toDateString(), 'slot' => 1, 'level' => 'A1', 'title' => 'Hier', 'status' => LessonVideo::READY]);

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.videos.redo-today'))->assertRedirect();
        $this->assertSame(LessonVideo::READY, LessonVideo::query()->where('title', 'Hier')->value('status'));

        app(LessonVideos::class)->prepare(3600);
        $job = $this->getJson(route('api.videos.jobs'), ['Authorization' => 'Bearer '.self::TOKEN])->assertOk()->json('jobs.0');

        $this->assertSame($video->id, $job['id']);
        $this->assertSame(config('avatar.model.name'), $job['render']['watermark']);
        $this->assertSame('classroom', $job['render']['scene']);
        $this->assertSame(['question', 'subscribe', 'closing'], array_column(array_slice($job['segments'], -3), 'kind'));

        // Nouvelle vidéo déposée, avec ses mesures.
        Storage::disk('local')->put(LessonVideos::videoPath($video), 'nouvelle-video');
        $this->postJson($job['done_url'], ['duration' => 30, 'lips_correlation' => 0.86, 'lips_open' => 0.32, 'loudness_lufs' => -14.2, 'true_peak_db' => -1.3], ['Authorization' => 'Bearer '.self::TOKEN])->assertOk();
        $video->refresh();
        $this->assertSame(LessonVideo::READY, $video->status);
        $this->assertSame(0.86, $video->lips_correlation);

        $this->get(route('admin.videos.index'))->assertSee('Lèvres : accord 0,86')->assertSee('Son : -14,2 LUFS, crête -1,3 dB');
    }

    public function test_a_voiced_video_with_an_outdated_ending_goes_back_to_preparation_before_rendering(): void
    {
        VideoSettings::saveSite('talkami.laravel.cloud');
        $video = $this->oldReadyVideo();
        $video->update(['status' => LessonVideo::VOICED]);

        $this->getJson(route('api.videos.jobs'), ['Authorization' => 'Bearer '.self::TOKEN])->assertOk()->assertJson(['jobs' => []]);
        $this->assertSame(LessonVideo::SCRIPTED, $video->fresh()->status);
    }

    public function test_the_voice_trial_plays_each_voice_says_when_a_key_is_missing_and_saves_the_choice(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.videos.index'))
            ->assertOk()->assertSee('Essai comparatif des voix')
            ->assertSee('Achird')->assertSee('Iapetus')->assertSee('marin')
            ->assertSee('Clé OPENAI_API_KEY absente');

        $this->get(route('admin.videos.voice-sample', ['provider' => 'gemini', 'voice' => 'Charon']))
            ->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $this->get(route('admin.videos.voice-sample', ['provider' => 'gemini', 'voice' => 'Charon']))->assertOk();
        $this->assertSame(1, $this->speechRequests);

        $this->getJson(route('admin.videos.voice-sample', ['provider' => 'openai', 'voice' => 'cedar']))
            ->assertStatus(503)->assertJsonPath('message', fn ($message) => str_contains($message, 'clé'));
        $this->get(route('admin.videos.voice-sample', ['provider' => 'gemini', 'voice' => 'Inconnue']))->assertNotFound();

        $this->post(route('admin.videos.voice-choose'), ['voice' => 'gemini:Charon'])->assertRedirect();
        $this->assertSame(['provider' => 'gemini', 'voice' => 'Charon'], VideoSettings::voice());
        $this->assertSame('Charon', VideoVoice::voiceFor(SpeechProviders::find('gemini')));
        $this->get(route('admin.videos.index'))->assertSee('Choisie');
    }

    public function test_the_render_settings_keep_the_safe_zones_and_the_github_budget(): void
    {
        $render = config('videos.render');

        $this->assertSame([540, 960], [$render['avatar_width'], $render['avatar_height']]);
        $this->assertSame(0.45, $render['jaw']);
        $this->assertSame([0, 0.61, 0], $render['camera']['target']);
        $this->assertSame([0.40, 0.32, 0.68], [$render['camera']['half_height'], $render['camera']['half_width'], $render['camera']['eye_height']]);
        $this->assertSame('128k', $render['audio_bitrate']);
        // Titre sous les onglets de TikTok, sous-titres loin du bas (légende de TikTok), marges contre les icônes.
        $this->assertGreaterThanOrEqual(150, $render['text']['title_top']);
        $this->assertLessThanOrEqual(1500, $render['text']['subtitle_top']);
        $this->assertGreaterThanOrEqual(100, $render['text']['side_margin']);
        // Nom du professeur tiré de la configuration, jamais écrit en dur.
        $this->assertSame(config('avatar.model.name'), VideoSettings::watermark());
        $this->assertStringNotContainsString('Teacher Joe', file_get_contents(base_path('tools/video/subtitles.mjs')));
        $this->assertStringNotContainsString('Teacher Joe', file_get_contents(base_path('tools/video/render.html')));
    }

    public function test_the_private_disk_is_used_for_voice_trials(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.videos.voice-sample', ['provider' => 'gemini', 'voice' => 'Orus']))->assertOk();

        $this->assertNotEmpty(PrivateStorage::disk()->files('video-voice-trials'));
    }
}
