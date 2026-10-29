<?php

namespace Tests\Feature\Admin;

use App\Models\Assessment;
use App\Models\CourseSession;
use App\Models\Lesson;
use App\Models\User;
use App\Support\Live\LiveVoiceSample;
use App\Support\Teachers\Teachers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * « Écouter en séance » (« Essai des voix ») : vraie connexion de séance avec la voix essayée, sans micro, aucune
 * séance ni minute comptée, extrait gardé, appel raccroché par le serveur, deuxième clé Gemini en secours.
 */
class VoiceLiveSampleTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, int> réponse de Google à la création d'un jeton, selon la clé */
    private array $tokens = ['cle-principale' => 200, 'cle-deuxieme' => 200];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'filesystems.private_disk' => 'local',
            'services.gemini.key' => 'cle-principale',
            'services.gemini.key_2' => 'cle-deuxieme',
            'services.openai.key' => 'sk-cle-openai',
        ]);

        Http::fake([
            '*/auth_tokens' => fn (Request $request) => ($this->tokens[$request->header('x-goog-api-key')[0] ?? ''] ?? 500) === 200
                ? Http::response(['name' => 'auth_tokens/jeton-'.($request->header('x-goog-api-key')[0] === 'cle-deuxieme' ? 'deux' : 'un')])
                : Http::response(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded for requests per day.']], 429),
            'api.openai.com/v1/realtime/calls' => Http::response("v=0\r\n", 201, ['Location' => '/v1/realtime/calls/rtc_essai']),
            '*/realtime/calls/*/hangup' => Http::response('', 200),
        ]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function wav(): UploadedFile
    {
        $data = str_repeat("\x10\x00", 2400);
        $wav = 'RIFF'.pack('V', 36 + strlen($data)).'WAVE'.'fmt '.pack('VvvVVvv', 16, 1, 1, 24000, 48000, 2, 16).'data'.pack('V', strlen($data)).$data;

        return UploadedFile::fake()->createWithContent('extrait.wav', $wav);
    }

    public function test_the_page_offers_listening_in_a_real_session(): void
    {
        $this->actingAs($this->admin())->get(route('admin.voices.index'))->assertOk()
            ->assertSee('Écouter en séance')
            ->assertSee(route('admin.voices.live', ['voice' => 'Achird', 'professeur' => 'joe', 'fournisseur' => 'gemini']));
    }

    public function test_gemini_session_with_this_voice_no_microphone_and_nothing_counted(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson(route('admin.voices.live', ['voice' => 'Orus', 'professeur' => 'joe', 'fournisseur' => 'gemini']))
            ->assertOk()
            ->assertJsonPath('provider', 'gemini')
            ->assertJsonPath('seconds', 30)
            ->assertJsonPath('setup.generationConfig.speechConfig.voiceConfig.prebuiltVoiceConfig.voiceName', 'Orus');

        $this->assertStringContainsString('START', $response->json('setup.systemInstruction.parts.0.text'));
        $this->assertArrayNotHasKey('realtimeInputConfig', $response->json('setup'));
        $this->assertSame(0, Lesson::query()->count() + Assessment::query()->count() + CourseSession::query()->count());

        // La voix reçue par le navigateur est gardée : l'écoute suivante ne se connecte plus.
        $this->actingAs($admin)->post($response->json('store_url'), ['audio' => $this->wav()])->assertOk();
        $tokens = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/auth_tokens'))->count();

        $stored = $this->actingAs($admin)->postJson(route('admin.voices.live', ['voice' => 'Orus', 'professeur' => 'joe', 'fournisseur' => 'gemini']))
            ->assertOk()->json('stored');
        $this->assertSame($tokens, Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/auth_tokens'))->count());
        $this->actingAs($admin)->get($stored)->assertOk()->assertHeader('Content-Type', 'audio/wav');
    }

    public function test_the_second_gemini_key_takes_over(): void
    {
        $this->tokens['cle-principale'] = 429;

        $this->actingAs($this->admin())->postJson(route('admin.voices.live', ['voice' => 'Orus', 'professeur' => 'joe', 'fournisseur' => 'gemini']))
            ->assertOk()
            ->assertJsonPath('websocket_url', fn (string $url) => str_contains($url, 'jeton-deux'));
    }

    public function test_openai_session_with_this_voice_without_turn_detection_and_hung_up_by_the_server(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson(route('admin.voices.live', ['voice' => 'marin', 'professeur' => 'rose', 'fournisseur' => 'openai']))
            ->assertOk()->assertJsonPath('provider', 'openai');

        $ticket = basename((string) $response->json('call_url'));
        $session = Cache::get('live-call:'.$ticket)['session'];
        $this->assertSame('marin', $session['audio']['output']['voice']);
        $this->assertNull($session['audio']['input']['turn_detection']);

        // Appel ouvert, puis la page est fermée sans rien dire : le serveur raccroche une fois les 30 secondes passées.
        $this->actingAs($admin)->postJson($response->json('call_url'), ['sdp' => "v=0\r\n"])->assertOk();
        $this->travel(31)->seconds();
        $this->artisan('live:close-stale')->assertSuccessful();

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/realtime/calls/rtc_essai/hangup'));
    }

    public function test_an_extract_is_accepted_only_after_a_session_of_this_admin_and_only_as_audio(): void
    {
        $admin = $this->admin();
        $url = route('admin.voices.live.store', ['voice' => 'Orus', 'professeur' => 'joe', 'fournisseur' => 'gemini']);

        $this->actingAs($admin)->post($url, ['audio' => $this->wav()])->assertStatus(409);

        $this->actingAs($admin)->postJson(route('admin.voices.live', ['voice' => 'Orus', 'professeur' => 'joe', 'fournisseur' => 'gemini']))->assertOk();
        $this->actingAs($admin)->post($url, ['audio' => UploadedFile::fake()->createWithContent('x.wav', '<html>')])->assertStatus(422);
        $this->assertNull(LiveVoiceSample::stored('gemini', 'Orus', Teachers::default()));
    }

    public function test_unknown_voice_and_learner_are_refused(): void
    {
        $this->actingAs($this->admin())->postJson(route('admin.voices.live', ['voice' => 'Inconnue', 'fournisseur' => 'gemini']))->assertNotFound();
        $this->actingAs(User::factory()->create())->postJson(route('admin.voices.live', ['voice' => 'Orus']))->assertForbidden();
    }
}
