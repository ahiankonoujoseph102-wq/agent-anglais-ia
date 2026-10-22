<?php

namespace Tests\Feature\Videos;

use App\Models\LessonVideo;
use App\Models\User;
use App\Support\Speech\SpeechProviders;
use App\Support\Videos\LessonVideos;
use App\Support\Videos\VideoScriptWriter;
use App\Support\Videos\VideoTopics;
use App\Support\Videos\VideoVoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Vidéos de leçon de Teacher Joe : sujets jamais répétés, script (Gemini puis OpenAI), voix d'un seul fournisseur,
 * API de GitHub Actions protégée par le jeton, page « Vidéos » de l'administration.
 */
class LessonVideoTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'jeton-de-test-des-videos-assez-long-1234567890';

    /** Réponse du modèle texte (Gemini), ou code d'erreur. */
    private mixed $geminiText = null;

    private mixed $openaiText = 500;

    /** Réponse de la synthèse vocale de Gemini : code d'erreur, ou audio. */
    private int $geminiSpeech = 200;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Sleep::fake();
        config([
            'filesystems.private_disk' => 'local',
            'services.gemini.key' => 'cle-gemini',
            'services.openai.key' => 'cle-openai',
            'services.text.providers' => ['gemini', 'openai'],
            'videos.voice.providers' => ['gemini', 'openai'],
            'videos.token' => self::TOKEN,
            'app.url' => 'https://talkami.laravel.cloud',
        ]);
        $this->geminiText = $this->script();

        Http::fake([
            '*/interactions' => fn (Request $request) => ($request['response_format']['type'] ?? null) === 'audio'
                ? ($this->geminiSpeech === 200 ? Http::response(self::audio()) : Http::response(['error' => ['code' => $this->geminiSpeech, 'message' => 'Resource has been exhausted (e.g. check quota). Quota exceeded for metric: generate_requests_per_model_per_day']], $this->geminiSpeech))
                : $this->textReply($this->geminiText, 'gemini'),
            'api.openai.com/v1/responses' => fn () => $this->textReply($this->openaiText, 'openai'),
            'api.openai.com/v1/audio/speech' => fn () => Http::response(str_repeat("\x01\x00", 24000), 200, ['Content-Type' => 'audio/pcm']),
        ]);
    }

    /** Un script complet, de la bonne longueur (environ 250 mots). */
    private function script(array $overrides = []): array
    {
        $segment = fn (int $i) => [
            'spoken' => "Partie {$i} : pour dire votre nom, on dit My name is Afi, et vous répétez après moi lentement, avec une voix claire, sans avoir peur de vous tromper du tout.",
            'subtitle' => "Partie {$i} : on dit [en]My name is Afi[/en].",
        ];

        return array_merge([
            'title' => 'Se présenter en anglais',
            'segments' => array_map($segment, range(1, 8)),
            'caption' => 'Apprenez à vous présenter en anglais en dix secondes. Nom, ville et métier !',
            'hashtags' => ['#anglais', 'apprendrelanglais', '#englishlesson', '#anglais', '#chatgpt'],
        ], $overrides);
    }

    private function textReply(mixed $answer, string $provider): mixed
    {
        if (is_int($answer)) {
            return Http::response(['error' => ['code' => $answer, 'message' => 'Erreur interne']], $answer);
        }

        $text = json_encode($answer);

        return $provider === 'gemini'
            ? Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $text]]]], 'usage' => ['total_input_tokens' => 900, 'total_output_tokens' => 700]])
            : Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => $text]]]], 'usage' => ['input_tokens' => 900, 'output_tokens' => 700]]);
    }

    /** Une demi-seconde de son (PCM 16 bits, 24 kHz), comme la synthèse de Gemini. */
    private static function audio(): array
    {
        return ['steps' => [['type' => 'model_output', 'content' => [['type' => 'audio', 'mime_type' => 'audio/l16;codec=pcm;rate=24000', 'data' => base64_encode(str_repeat("\x10\x00", 12000))]]]]];
    }

    private function prepareAll(): void
    {
        for ($i = 0; $i < 5; $i++) {
            app(LessonVideos::class)->prepare(3600);
        }
    }

    public function test_the_topic_program_is_valid_and_names_no_other_country(): void
    {
        $topics = VideoTopics::all();
        $keys = array_column($topics, 'key');

        $this->assertGreaterThanOrEqual(80, count($topics));
        $this->assertSame($keys, array_unique($keys));

        foreach ($topics as $topic) {
            $this->assertContains($topic['level'], config('videos.levels'));
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $topic['key']);
            $this->assertLessThanOrEqual(80, mb_strlen($topic['title']));
            $this->assertDoesNotMatchRegularExpression('/\b(ghana|accra|aflao|nigeria|france|certificat|diplôme|t-money)\b/iu', $topic['title'].' '.$topic['angle']);
        }
    }

    public function test_a_topic_is_never_treated_twice(): void
    {
        $videos = app(LessonVideos::class);

        $this->assertSame(3, $videos->plan(today(), 3));
        $this->assertSame(2, $videos->plan(today()->addDay(), 2));
        $this->assertSame(0, $videos->planToday());

        $keys = LessonVideo::query()->pluck('topic_key');
        $this->assertCount(5, $keys->unique());
        $this->assertSame(array_column(VideoTopics::all(), 'key')[0], LessonVideo::query()->orderBy('id')->value('topic_key'));
        $this->assertSame([1, 2, 3], LessonVideo::query()->whereDate('day', today())->orderBy('slot')->pluck('slot')->all());
        $this->assertSame(count(VideoTopics::all()) - 5, VideoTopics::remaining());
    }

    public function test_the_script_then_the_voice_are_prepared_with_the_fixed_closing_sentence(): void
    {
        app(LessonVideos::class)->plan(today(), 1);
        $this->prepareAll();

        $video = LessonVideo::query()->firstOrFail();
        $this->assertSame(LessonVideo::VOICED, $video->status);
        $this->assertSame('gemini', $video->text_provider);
        $this->assertSame('gemini', $video->voice_provider);

        $last = collect($video->script['segments'])->last();
        $this->assertTrue($last['closing']);
        $this->assertSame('Pour aller plus loin, évaluez gratuitement votre niveau d\'anglais sur talkami.laravel.cloud.', $last['subtitle']);
        $this->assertStringContainsString('talkami point laravel point cloud', $last['spoken']);

        // Hashtags nettoyés : sans doublon, sans marque.
        $this->assertSame('#anglais #apprendrelanglais #englishlesson', $video->hashtags);
        $this->assertStringContainsString('évaluez gratuitement votre niveau d\'anglais sur talkami.laravel.cloud', $video->caption);

        // Une voix assemblée : 9 parties d'une demi-seconde et 8 silences.
        $this->assertTrue(Storage::disk('local')->exists($video->audio_path));
        $this->assertCount(9, $video->timings['segments']);
        $this->assertEqualsWithDelta(9 * 0.5 + 8 * 0.45, $video->timings['duration'], 0.01);
        $this->assertSame(0.95, $video->timings['segments'][1]['start']);
        $this->assertFalse(Storage::disk('local')->exists($video->folder().'/voice'));
    }

    public function test_openai_writes_the_script_when_gemini_fails_and_a_bad_script_is_refused(): void
    {
        $this->geminiText = $this->script(['caption' => 'Une vidéo faite avec ChatGPT.']);
        $this->openaiText = $this->script();

        app(LessonVideos::class)->plan(today(), 1);
        app(LessonVideos::class)->prepare(3600);

        $this->assertSame('openai', LessonVideo::query()->value('text_provider'));
    }

    public function test_the_whole_video_switches_to_openai_when_gemini_reaches_its_daily_limit(): void
    {
        $this->geminiSpeech = 429;
        app(LessonVideos::class)->plan(today(), 1);
        $this->prepareAll();

        $video = LessonVideo::query()->firstOrFail();
        $this->assertSame(LessonVideo::VOICED, $video->status);
        $this->assertSame('openai', $video->voice_provider);
        $this->assertSame('cedar', $video->voice_name);
        $this->assertTrue(VideoVoice::isPaused(SpeechProviders::find('gemini')));
    }

    public function test_the_voices_can_be_changed_by_configuration(): void
    {
        config(['videos.voice.providers' => ['openai', 'gemini'], 'videos.voice.openai_voice' => 'marin']);
        app(LessonVideos::class)->plan(today(), 1);
        $this->prepareAll();

        $video = LessonVideo::query()->firstOrFail();
        $this->assertSame(['openai', 'marin'], [$video->voice_provider, $video->voice_name]);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'audio/speech') && $request['voice'] === 'marin');
    }

    public function test_the_github_api_refuses_a_wrong_or_missing_token(): void
    {
        $this->getJson(route('api.videos.jobs'))->assertUnauthorized();
        $this->getJson(route('api.videos.jobs'), ['Authorization' => 'Bearer mauvais'])->assertUnauthorized();

        config(['videos.token' => null]);
        $this->getJson(route('api.videos.jobs'), ['Authorization' => 'Bearer '.self::TOKEN])->assertStatus(503);
    }

    public function test_github_takes_a_job_uploads_the_video_and_marks_it_ready(): void
    {
        app(LessonVideos::class)->plan(today(), 1);
        $this->prepareAll();
        $headers = ['Authorization' => 'Bearer '.self::TOKEN];

        $this->getJson(route('api.videos.jobs', ['apercu' => 1]), $headers)->assertOk()->assertJson(['waiting' => 1]);

        $job = $this->getJson(route('api.videos.jobs', ['limit' => 3]), $headers)->assertOk()->json('jobs.0');
        $video = LessonVideo::query()->firstOrFail();
        $this->assertSame(LessonVideo::RENDERING, $video->status);
        $this->assertSame('talkami.laravel.cloud', $job['site']);
        $this->assertCount(9, $job['segments']);
        $this->assertSame(1080, $job['render']['width']);
        $this->assertArrayHasKey('camera', $job['avatar']);
        $this->assertStringNotContainsString('cle-', json_encode($job));

        // Déjà pris : plus rien à prendre.
        $this->getJson(route('api.videos.jobs'), $headers)->assertOk()->assertJson(['jobs' => []]);

        $this->get($job['audio_url'], $headers)->assertOk();

        // Adresse signée du disque (le bucket sur Laravel Cloud) ; le dépôt par l'API reste possible.
        $this->assertTrue($job['upload']['direct']);
        $this->assertSame('PUT', $job['upload']['method']);
        $this->call('PUT', route('api.videos.upload', $video), [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN, 'CONTENT_TYPE' => 'video/mp4', 'CONTENT_LENGTH' => '12'], 'faux-mp4-123')->assertOk();
        $this->call('PUT', route('api.videos.upload', $video), [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN, 'CONTENT_LENGTH' => (string) (61 * 1024 * 1024)], 'x')->assertStatus(413);

        $this->postJson($job['done_url'], ['duration' => 125.4], $headers)->assertOk();
        $video->refresh();
        $this->assertSame(LessonVideo::READY, $video->status);
        $this->assertSame(126, $video->duration_seconds);
        $this->assertSame(12, $video->size_bytes);
    }

    public function test_a_failed_render_goes_back_to_the_queue_then_fails_after_too_many_attempts(): void
    {
        app(LessonVideos::class)->plan(today(), 1);
        $this->prepareAll();
        $headers = ['Authorization' => 'Bearer '.self::TOKEN];

        for ($i = 1; $i <= 4; $i++) {
            $job = $this->getJson(route('api.videos.jobs'), $headers)->json('jobs.0');
            $this->postJson($job['failed_url'], ['error' => 'ffmpeg a échoué'], $headers)->assertOk();
        }

        $video = LessonVideo::query()->firstOrFail();
        $this->assertSame(LessonVideo::FAILED, $video->status);
        $this->assertStringContainsString('ffmpeg a échoué', $video->error);

        // « Réessayer » : la voix est gardée, la vidéo repart en fabrication.
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.videos.retry', $video))->assertRedirect();
        $this->assertSame(LessonVideo::VOICED, $video->fresh()->status);
    }

    public function test_the_admin_page_shows_the_video_the_download_the_caption_and_the_ai_reminder(): void
    {
        $admin = User::factory()->admin()->create();
        $video = LessonVideo::query()->create([
            'topic_key' => 'saluer', 'day' => today()->toDateString(), 'slot' => 1, 'level' => 'A1', 'title' => 'Dire bonjour',
            'status' => LessonVideo::READY, 'caption' => VideoScriptWriter::publication('Dites bonjour comme il faut.', ['#anglais']),
            'video_path' => 'videos/test.mp4', 'duration_seconds' => 124, 'size_bytes' => 9_000_000,
        ]);
        Storage::disk('local')->put('videos/test.mp4', 'faux-mp4');

        $this->actingAs($admin)->get(route('admin.videos.index'))
            ->assertOk()
            ->assertSee('Dire bonjour')
            ->assertSee('Télécharger')
            ->assertSee('Contenu généré par IA')
            ->assertSee('Copier le texte')
            ->assertSee('#anglais');

        // Adresse signée de courte durée du bucket privé.
        $this->get(route('admin.videos.file', ['video' => $video, 'telecharger' => 1]))->assertRedirect();
        $this->get(route('admin.videos.file', LessonVideo::query()->create([
            'topic_key' => 'merci-pardon', 'day' => today()->toDateString(), 'slot' => 2, 'level' => 'A1', 'title' => 'Merci', 'status' => LessonVideo::VOICED,
        ])))->assertNotFound();

        $this->put(route('admin.videos.publish', $video), ['published' => 1])->assertRedirect();
        $this->assertNotNull($video->fresh()->published_at);
        $this->put(route('admin.videos.publish', $video), [])->assertRedirect();
        $this->assertNull($video->fresh()->published_at);
    }

    public function test_the_admin_can_prepare_videos_now_and_a_learner_cannot(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.videos.index'))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.videos.prepare'), ['count' => 2])->assertRedirect();
        $this->assertSame(2, LessonVideo::query()->whereDate('day', today())->count());
    }

    public function test_the_morning_task_adds_videos_only_when_github_is_set_up(): void
    {
        config(['videos.token' => null]);
        $this->artisan('videos:prepare --plan --budget=1')->assertSuccessful();
        $this->assertSame(0, LessonVideo::query()->count());

        config(['videos.token' => self::TOKEN]);
        $this->artisan('videos:prepare --plan --budget=1')->assertSuccessful();
        $this->assertSame(3, LessonVideo::query()->count());
    }

    public function test_the_token_command_shows_a_random_token_once(): void
    {
        $this->artisan('videos:generate-token')->expectsOutputToContain('VIDEO_UPLOAD_TOKEN')->assertSuccessful();
    }
}
