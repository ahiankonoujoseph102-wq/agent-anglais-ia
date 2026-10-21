<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\LevelMessage;
use App\Support\Gemini\SpeechFailed;
use App\Support\Gemini\SpeechLog;
use App\Support\Gemini\SpeechPreparer;
use App\Support\Gemini\VoiceSampler;
use App\Support\Gemini\WelcomeMessage;
use App\Support\PrivateStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Préparation à l'avance des messages vocaux communs (commande app:prepare-voice, page « Diagnostic de la voix ») :
 * espacement des demandes, nouvel essai après une erreur 429, délai dépassé, échec d'écriture, morceaux déjà
 * prêts, journal des échecs sans clé, audio PCM brut. Fausse synthèse uniquement : aucun appel réel à Gemini.
 */
class VoicePreparationTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'cle-secrete-de-test';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['filesystems.private_disk' => 'local', 'services.gemini.key' => self::KEY]);
        Sleep::fake(syncWithCarbon: true);
    }

    private static function speech(string $bytes = 'RIFF-son-de-test', ?string $mime = null): array
    {
        return ['steps' => [[
            'type' => 'model_output',
            'content' => [array_filter(['type' => 'audio', 'data' => base64_encode($bytes), 'mime_type' => $mime])],
        ]]];
    }

    private static function quota(bool $daily = false, string $retryDelay = '20s'): array
    {
        return ['error' => [
            'code' => 429,
            'message' => $daily ? 'Quota exceeded for requests per day.' : 'Resource exhausted.',
            'status' => 'RESOURCE_EXHAUSTED',
            'details' => [
                ['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['quotaId' => $daily ? 'GenerateRequestsPerDayPerProjectPerModel-FreeTier' : 'GenerateRequestsPerMinutePerProjectPerModel-FreeTier']]],
                ['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => $retryDelay],
            ],
        ]];
    }

    /** Morceaux communs, sans doublon. */
    private static function total(): int
    {
        return count(WelcomeMessage::commonParagraphs()) + count(LevelMessage::all());
    }

    /** Messages communs (un message de bienvenue par formule et par état du programme, un message par niveau). */
    private static function messages(): int
    {
        return count(SpeechPreparer::messages());
    }

    public function test_everything_is_prepared_one_by_one_with_spacing(): void
    {
        Http::fake(['*/interactions' => Http::response(self::speech())]);

        $result = app(SpeechPreparer::class)->run(3600);

        Http::assertSentCount(self::total());
        $this->assertSame(self::total(), $result['generated']);
        $this->assertSame($result['welcome']['total'], $result['welcome']['ready']);
        $this->assertSame($result['levels']['total'], $result['levels']['ready']);
        $this->assertStringContainsString('Tout est prêt.', SpeechPreparer::summary($result));
        // Une pause entre deux demandes (limite par minute).
        Sleep::assertSleptTimes(self::total() - 1);
        Sleep::assertSequence(array_fill(0, self::total() - 1, Sleep::for(8)->seconds()));
    }

    public function test_pieces_already_ready_are_never_asked_again(): void
    {
        Http::fake(['*/interactions' => Http::response(self::speech())]);
        app(SpeechPreparer::class)->run(3600);

        Http::fake();
        $result = app(SpeechPreparer::class)->run(3600);

        Http::assertNothingSent();
        $this->assertSame(0, $result['generated']);
    }

    public function test_a_429_is_retried_after_a_growing_delay_then_succeeds(): void
    {
        Http::fake(['*/interactions' => Http::sequence()
            ->push(self::quota(retryDelay: '20s'), 429)
            ->push(self::quota(retryDelay: '5s'), 429)
            ->whenEmpty(Http::response(self::speech()))]);

        $result = app(SpeechPreparer::class)->run(3600);

        $this->assertSame(self::total(), $result['generated']);
        Http::assertSentCount(self::total() + 2);
        // Premier nouvel essai : le délai conseillé par Google (20 secondes) ; second : 30 secondes (délai croissant).
        $this->assertSame([20, 30], array_slice($this->sleptSeconds(), 0, 2));
        $this->assertSame(SpeechFailed::QUOTA, SpeechLog::recent()[0]['kind']);
        $this->assertSame(429, SpeechLog::recent()[0]['status']);
    }

    public function test_repeated_429_stop_the_preparation_and_say_what_remains(): void
    {
        Http::fake(['*/interactions' => Http::response(self::quota(retryDelay: '1s'), 429)]);

        $result = app(SpeechPreparer::class)->run(3600);

        // Un essai, puis un nouvel essai par délai de la configuration (15, 30, 60 secondes), puis arrêt.
        Http::assertSentCount(1 + count(config('services.gemini.tts_retry_delays')));
        $this->assertSame(SpeechFailed::QUOTA, $result['stopped']);
        $this->assertStringContainsString('Il en reste '.self::messages().' :', SpeechPreparer::summary($result));
        $this->assertStringContainsString('Relancez dans quelques minutes', SpeechPreparer::summary($result));
    }

    public function test_the_daily_limit_stops_at_once(): void
    {
        Http::fake(['*/interactions' => Http::response(self::quota(daily: true), 429)]);

        $result = app(SpeechPreparer::class)->run(3600);

        Http::assertSentCount(1);
        $this->assertSame(SpeechFailed::QUOTA_DAILY, $result['stopped']);
        $this->assertStringContainsString('Relancez demain', SpeechPreparer::summary($result));
        $this->assertSame('Limite de demandes du jour atteinte ou crédit épuisé (erreur 429)', SpeechLog::recent()[0]['reason']);
    }

    public function test_a_timeout_is_logged_and_the_preparation_stops_after_a_few_failures(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            throw new ConnectionException('cURL error 28: Operation timed out after 60001 milliseconds');
        });

        $result = app(SpeechPreparer::class)->run(3600);

        $this->assertSame(SpeechPreparer::MAX_FAILURES, $calls);
        $this->assertSame('errors', $result['stopped']);
        $failure = SpeechLog::recent()[0];
        $this->assertSame(SpeechFailed::TIMEOUT, $failure['kind']);
        $this->assertSame('Délai d\'attente dépassé', $failure['reason']);
        $this->assertNotNull($failure['duration_ms']);
        // Les messages du résultat de l'évaluation sont préparés d'abord.
        $this->assertStringStartsWith('Résultat de l\'évaluation, niveau', $failure['label']);
    }

    public function test_a_storage_failure_stops_the_preparation(): void
    {
        Http::fake(['*/interactions' => Http::response(self::speech())]);
        config(['filesystems.disks.casse' => ['driver' => 'local', 'root' => '/dev/null/casse', 'throw' => false], 'filesystems.private_disk' => 'casse']);

        $result = app(SpeechPreparer::class)->run(3600);

        Http::assertSentCount(1);
        $this->assertSame(SpeechFailed::STORAGE, $result['stopped']);
        $this->assertSame('Écriture impossible dans le stockage privé', SpeechLog::recent()[0]['reason']);
        $this->assertStringContainsString('stockage privé', SpeechPreparer::summary($result));
    }

    public function test_the_time_budget_is_never_exceeded(): void
    {
        Http::fake(['*/interactions' => Http::response(self::speech())]);

        // 30 secondes : une demande tout de suite, puis une toutes les 8 secondes tant qu'il en reste au moins 10.
        $result = app(SpeechPreparer::class)->run(30);

        $this->assertSame(3, $result['generated']);
        $this->assertSame('budget', $result['stopped']);
        // Les messages du résultat (un seul morceau) d'abord : 3 sont complets, les autres restent à finir.
        $this->assertStringContainsString('Il en reste '.(self::messages() - 3).' : le temps accordé est écoulé', SpeechPreparer::summary($result));
    }

    public function test_raw_pcm_audio_is_wrapped_in_a_wav_header(): void
    {
        $pcm = str_repeat("\x01\x02", 2400);
        Http::fake(['*/interactions' => Http::response(self::speech($pcm, 'audio/l16;codec=pcm;rate=24000'))]);

        $wav = app(VoiceSampler::class)->synthesize('Achird', 'Bonjour.', 'calme');

        $this->assertStringStartsWith('RIFF', $wav);
        $this->assertSame('WAVE', substr($wav, 8, 4));
        $this->assertSame(24000, unpack('V', substr($wav, 24, 4))[1]);
        $this->assertSame(strlen($pcm), unpack('V', substr($wav, 40, 4))[1]);
        $this->assertSame($pcm, substr($wav, 44));

        // Un WAV reçu tel quel est gardé tel quel ; un autre format est refusé et journalisé.
        $this->assertSame('RIFFxxxxWAVE', VoiceSampler::toWav('RIFFxxxxWAVE', 'audio/wav'));
        $this->assertNull(VoiceSampler::toWav('ID3...', 'audio/mpeg'));
    }

    public function test_failures_are_logged_with_their_cause_and_never_the_key(): void
    {
        Log::spy();
        Http::fake(['*/interactions' => Http::response(['error' => ['code' => 404, 'message' => 'models/gemini-inconnu is not found.', 'status' => 'NOT_FOUND']], 404)]);

        try {
            app(VoiceSampler::class)->synthesize('Achird', 'Bonjour.', 'calme', 'Bienvenue, paragraphe 2');
            $this->fail('La synthèse aurait dû échouer.');
        } catch (SpeechFailed $e) {
            $this->assertSame(SpeechFailed::HTTP, $e->kind);
            $this->assertSame(404, $e->status);
        }

        $failure = SpeechLog::recent()[0];
        $this->assertSame('Bienvenue, paragraphe 2', $failure['label']);
        $this->assertSame(404, $failure['status']);
        $this->assertStringContainsString('models/gemini-inconnu is not found.', $failure['message']);
        $this->assertStringContainsString(config('services.gemini.tts_model'), $failure['message']);
        $this->assertStringNotContainsString(self::KEY, json_encode(SpeechLog::recent()));

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $message === 'Gemini : synthèse vocale en échec.'
            && $context['status'] === 404
            && $context['label'] === 'Bienvenue, paragraphe 2'
            && ! str_contains(json_encode($context), self::KEY))->once();
    }

    public function test_the_command_never_fails_the_deployment(): void
    {
        Http::fake(['*/interactions' => Http::response(self::quota(daily: true), 429)]);
        $this->artisan('app:prepare-voice')->expectsOutputToContain('Relancez demain')->assertSuccessful();

        config(['services.gemini.key' => null]);
        $this->artisan('app:prepare-voice')->expectsOutputToContain('aucune clé de synthèse vocale n\'est présente (GEMINI_API_KEY, OPENAI_API_KEY)')->assertSuccessful();

        // Même une erreur inattendue ne fait pas échouer la commande.
        $this->app->instance(SpeechPreparer::class, new class extends SpeechPreparer
        {
            public function __construct() {}

            public function run(int $budget, ?\Closure $report = null): array
            {
                throw new \RuntimeException('panne');
            }
        });
        $this->artisan('app:prepare-voice')->expectsOutputToContain('interrompue')->assertSuccessful();
    }

    public function test_level_messages_prepared_in_advance_are_served_without_asking_google(): void
    {
        Http::fake(['*/interactions' => Http::response(self::speech())]);
        $this->artisan('app:prepare-voice')->assertSuccessful();
        Http::fake();

        $user = User::factory()->create();
        $user->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 300, 'level' => 'B1', 'status' => 'completed']);
        $user->level = Level::B1;
        $user->save();

        $this->actingAs($user)->get('/evaluation/message-vocal')->assertOk()->assertHeader('Content-Type', 'audio/wav');
        Http::assertNothingSent();
    }

    /* ---------- Page « Diagnostic de la voix » ---------- */

    public function test_only_administrators_see_the_diagnostic_page_and_never_the_key(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.voice-diagnostic.index'))->assertForbidden();

        $page = $this->actingAs(User::factory()->admin()->create())->get(route('admin.voice-diagnostic.index'))->assertOk()
            ->assertSee('Diagnostic de la voix')
            ->assertSee('Tester Gemini (Google)')
            ->assertSee('Tester OpenAI')
            ->assertSee('Préparer le message de bienvenue')
            ->assertSeeText('Messages de bienvenue : 0 prêts sur '.count(WelcomeMessage::messages()))
            ->assertSee('Clé GEMINI_API_KEY : <strong>présente</strong>', false)
            ->assertSee('Clé OPENAI_API_KEY : <strong>absente</strong>', false)
            ->assertSee(config('services.openai.tts_model'))
            ->assertSee(config('services.openai.tts_voice'))
            ->assertSee(config('services.gemini.tts_model'))
            ->assertSee('Aucune erreur enregistrée.');

        $this->assertStringNotContainsString(self::KEY, $page->getContent());
        $page->assertSee(route('admin.voice-diagnostic.index'), false);
    }

    public function test_the_test_button_reports_success_or_the_cause_of_the_failure(): void
    {
        $admin = User::factory()->admin()->create();
        Http::fake(['*/interactions' => Http::sequence()
            ->push(self::speech())
            ->push(self::quota(), 429)]);

        $this->actingAs($admin)->from(route('admin.voice-diagnostic.index'))->post(route('admin.voice-diagnostic.test', 'gemini'))
            ->assertRedirect(route('admin.voice-diagnostic.index'))
            ->assertSessionHas('diagnostic', fn (array $result) => $result['ok'] && str_contains($result['text'], 'Gemini (Google) : réussi'));
        $this->assertSame([], PrivateStorage::disk()->allFiles('voice-diagnostic'));

        $this->actingAs($admin)->from(route('admin.voice-diagnostic.index'))->post(route('admin.voice-diagnostic.test', 'gemini'))
            ->assertSessionHas('diagnostic', fn (array $result) => ! $result['ok'] && str_contains($result['text'], 'code HTTP 429'));

        $this->get(route('admin.voice-diagnostic.index'))->assertSee('Diagnostic : phrase d&#039;essai', false)->assertSee('Limite de demandes par minute atteinte (erreur 429)');
    }

    public function test_the_prepare_button_says_how_many_remain(): void
    {
        Http::fake(['*/interactions' => Http::response(self::speech())]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from(route('admin.voice-diagnostic.index'))->post(route('admin.voice-diagnostic.prepare'))
            ->assertSessionHas('diagnostic', fn (array $result) => ! $result['ok'] && str_contains($result['text'], 'relancez la préparation'));

        // Nouveaux touchers jusqu'à ce que tout soit prêt.
        for ($i = 0; $i < 10 && ! (SpeechPreparer::status()['welcome']['ready'] === SpeechPreparer::status()['welcome']['total'] && SpeechPreparer::status()['levels']['ready'] === SpeechPreparer::status()['levels']['total']); $i++) {
            $this->travel(1)->minutes();
            $this->post(route('admin.voice-diagnostic.prepare'));
        }

        $this->travel(1)->minutes();
        $this->post(route('admin.voice-diagnostic.prepare'))
            ->assertSessionHas('diagnostic', fn (array $result) => $result['ok'] && str_contains($result['text'], 'Tout est prêt.'));
        Http::assertSentCount(self::total());
    }

    /**
     * @return list<int>
     */
    private function sleptSeconds(): array
    {
        $durations = (new \ReflectionProperty(Sleep::class, 'sequence'))->getValue();

        return array_map(fn ($duration) => (int) $duration->totalSeconds, $durations);
    }
}
