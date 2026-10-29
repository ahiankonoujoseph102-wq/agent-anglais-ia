<?php

namespace Tests\Feature\Videos;

use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Support\Gemini\SpeechFailed;
use App\Support\Teachers\Teachers;
use App\Support\Videos\PresentationScripts;
use App\Support\Videos\PresentationVideos;
use App\Support\Videos\VideoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Vidéos de présentation de Teacher Rose redites avec la voix choisie (Zephyr, « Essai des voix ») : phrases
 * regroupées en quelques demandes (9 au lieu de 105 pour les 7 vidéos), toujours la voix choisie (attendre plutôt que
 * changer de voix), remise en préparation par la migration. Aucun appel réel.
 */
class RosePresentationVoiceTest extends TestCase
{
    use RefreshDatabase;

    private const KEYS = ['rose-talkami', 'rose-compte', 'rose-evaluation', 'rose-formules', 'rose-paiement', 'rose-seance', 'rose-mes-cours'];

    /** @var bool Gemini à sa limite du jour sur les deux clés */
    private bool $geminiExhausted = false;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Sleep::fake();
        config([
            'filesystems.private_disk' => 'local',
            'services.gemini.key' => 'cle-gemini',
            'services.gemini.key_2' => 'cle-gemini-2',
            'services.openai.key' => 'cle-openai',
            'videos.voice.providers' => ['gemini', 'openai'],
        ]);
        VideoSettings::saveSite('talkami.net');
        Teachers::find('rose')->chooseSpeechVoice('gemini', 'Zephyr');

        // 0,5 seconde de voix par phrase (séparées par un saut de paragraphe), 0,3 seconde de silence entre deux.
        $speech = function (string $text) {
            $phrases = substr_count($text, "\n\n") + 1;

            return implode(str_repeat("\x00\x00", 7200), array_fill(0, $phrases, str_repeat("\x00\x10", 12000)));
        };

        Http::fake([
            '*/interactions' => fn (Request $request) => $this->geminiExhausted
                ? Http::response(['error' => ['code' => 429, 'message' => 'Quota exceeded for metric: generate_requests_per_model_per_day, limit: 10', 'details' => [['violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']]]]]], 429)
                : Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'audio', 'mime_type' => 'audio/l16;codec=pcm;rate=24000', 'data' => base64_encode($speech((string) ($request['input'][0]['content'][0]['text'] ?? '')))]]]]]),
            '*/audio/speech' => Http::response('pcm', 200),
        ]);
    }

    private function prepareAll(): void
    {
        for ($i = 0; $i < 10; $i++) {
            app(PresentationVideos::class)->prepare(microtime(true) + 3600);
        }
    }

    public function test_her_seven_videos_are_said_with_zephyr_in_nine_requests(): void
    {
        foreach (self::KEYS as $key) {
            $this->assertSame(1, app(PresentationVideos::class)->request($key));
        }

        $phrases = collect(self::KEYS)->sum(fn (string $key) => count(PresentationScripts::script($key)['segments']));
        $this->assertGreaterThan(100, $phrases);

        $this->prepareAll();

        $requests = Http::recorded(fn (Request $request) => str_contains($request->url(), '/interactions'));
        $this->assertCount(9, $requests);
        $this->assertTrue($requests->every(fn (array $pair) => $pair[0]['generation_config']['speech_config'][0]['voice'] === 'Zephyr'));

        foreach (self::KEYS as $key) {
            $video = PresentationVideo::query()->where('key', $key)->firstOrFail();
            $this->assertSame(LessonVideo::VOICED, $video->status, $key);
            $this->assertSame(['gemini', 'Zephyr'], [$video->voice_provider, $video->voice_name]);
            // Une heure de début et de fin par phrase (sous-titres et écrans), dans l'ordre.
            $this->assertCount(count($video->script['segments']), $video->timings['segments']);
            $starts = array_column($video->timings['segments'], 'start');
            $this->assertSame($starts, collect($starts)->sort()->values()->all());
        }
    }

    public function test_a_presentation_waits_for_her_chosen_voice_instead_of_another_one(): void
    {
        $this->geminiExhausted = true;
        app(PresentationVideos::class)->request('rose-compte');
        $this->prepareAll();

        $video = PresentationVideo::query()->where('key', 'rose-compte')->firstOrFail();
        $this->assertSame(LessonVideo::SCRIPTED, $video->status);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/audio/speech'));
        $this->assertSame(SpeechFailed::QUOTA_DAILY, cache()->get('videos.voice.paused.gemini'));
    }

    public function test_the_migration_redoes_her_videos_said_with_another_voice_only(): void
    {
        $videos = app(PresentationVideos::class);

        foreach ($videos->all() as $video) {
            $script = PresentationScripts::script($video->key);
            Storage::disk('local')->put($video->folder().'/voice.wav', 'wav');
            $video->update([
                'status' => LessonVideo::READY, 'script' => $script, 'script_hash' => PresentationScripts::hash($script),
                'audio_path' => $video->folder().'/voice.wav', 'voice_provider' => 'gemini',
                'voice_name' => str_starts_with($video->key, 'rose-') ? 'Sulafat' : 'Achird', 'ready_at' => now(),
            ]);
        }

        // Déjà redite avec Zephyr : pas touchée.
        PresentationVideo::query()->where('key', 'rose-formules')->update(['voice_name' => 'Zephyr']);

        (require database_path('migrations/2026_11_22_000001_revoice_rose_presentations.php'))->up();

        foreach (self::KEYS as $key) {
            $this->assertSame($key === 'rose-formules' ? LessonVideo::READY : LessonVideo::SCRIPTED, PresentationVideo::query()->where('key', $key)->value('status'), $key);
        }

        // Vidéos de Teacher Joe : jamais touchées.
        $this->assertSame(0, PresentationVideo::query()->where('key', 'not like', 'rose-%')->where('status', '!=', LessonVideo::READY)->count());
    }
}
