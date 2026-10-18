<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Models\SpeechSynthesis;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Gemini\LevelMessage;
use App\Support\Gemini\SpeechFailed;
use App\Support\Gemini\SpeechLog;
use App\Support\Gemini\SpeechPreparer;
use App\Support\Gemini\VoiceSampler;
use App\Support\Gemini\WelcomeMessage;
use App\Support\PrivateStorage;
use App\Support\Speech\GeminiSpeech;
use App\Support\Speech\OpenAiSpeech;
use App\Support\Speech\SpeechProviders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Synthèse vocale des messages avec deux fournisseurs : Gemini d'abord, OpenAI en secours (TTS_PROVIDERS).
 * Règle d'UNE seule voix par message : un message est préparé et joué en entier par un seul fournisseur,
 * salutation comprise. Faux services uniquement : aucun appel réel à Google ni à OpenAI.
 */
class SpeechProvidersTest extends TestCase
{
    use RefreshDatabase;

    private const GEMINI_KEY = 'cle-gemini-secrete';

    private const OPENAI_KEY = 'sk-cle-openai-secrete';

    /** Voix de Gemini reçue (WAV) et voix d'OpenAI (PCM brut, enveloppé en WAV) : reconnaissables. */
    private const GEMINI_WAV = 'RIFF-voix-gemini';

    private const OPENAI_PCM = "\x0A\x0B\x0C\x0D\x0A\x0B\x0C\x0D";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'filesystems.private_disk' => 'local',
            'services.gemini.key' => self::GEMINI_KEY,
            'services.openai.key' => self::OPENAI_KEY,
            'services.tts.providers' => ['gemini', 'openai'],
            'app.timezone' => 'Africa/Lome',
        ]);
        Sleep::fake(syncWithCarbon: true);
    }

    private static function geminiSpeech(): array
    {
        return ['steps' => [['type' => 'model_output', 'content' => [['type' => 'audio', 'data' => base64_encode(self::GEMINI_WAV)]]]]];
    }

    private static function geminiDailyQuota(): array
    {
        return ['error' => [
            'code' => 429,
            'message' => 'Quota exceeded for requests per day.',
            'status' => 'RESOURCE_EXHAUSTED',
            'details' => [['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']]]],
        ]];
    }

    private static function openAiWav(): string
    {
        return (string) VoiceSampler::toWav(self::OPENAI_PCM, 'audio/pcm;rate=24000');
    }

    /**
     * @param  mixed  $gemini  réponse (ou suite de réponses) de Gemini
     * @param  mixed  $openai  réponse (ou suite de réponses) d'OpenAI
     */
    private function fake(mixed $gemini, mixed $openai = null): void
    {
        Http::fake([
            '*/interactions' => $gemini,
            '*/audio/speech' => $openai ?? Http::response(self::OPENAI_PCM, 200, ['Content-Type' => 'audio/pcm']),
        ]);
    }

    private function sentTo(string $provider): int
    {
        $pattern = $provider === 'openai' ? '/audio/speech' : '/interactions';

        return Http::recorded(fn (Request $request) => str_contains($request->url(), $pattern))->count();
    }

    private static function pieces(): int
    {
        return count(WelcomeMessage::commonParagraphs()) + count(LevelMessage::all());
    }

    private function newcomer(string $name = 'Koffi Mensah'): User
    {
        $user = User::factory()->unwelcomed()->create(['name' => $name, 'phone' => '+22890123456']);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();

        return $user;
    }

    /**
     * Joue tout le message de bienvenue comme la page : adresses et « prêt » de chaque morceau tirés de la page.
     *
     * @return list<array{status: int, body: string}>
     */
    private function listen(User $user): array
    {
        $this->actingAs($user);
        $page = $this->get(route('welcome.show', ['reecouter' => 1]))->assertOk()->getContent();
        $pieces = json_decode(html_entity_decode((string) preg_replace('/.*data-pieces=\'([^\']*)\'.*/s', '$1', $page)), true);
        $played = [];

        foreach ($pieces as $piece) {
            $response = $this->get($piece['url']);
            $played[] = ['status' => $response->status(), 'body' => (string) $response->getContent(), 'url' => $piece['url'], 'ready' => $piece['ready']];
        }

        return $played;
    }

    /* ---------- Réglages ---------- */

    public function test_the_order_comes_from_the_configuration_and_a_provider_without_key_is_ignored(): void
    {
        $this->assertSame(['gemini', 'openai'], array_map(fn ($provider) => $provider->name(), SpeechProviders::available()));

        config(['services.openai.key' => null]);
        $this->assertSame(['gemini'], array_map(fn ($provider) => $provider->name(), SpeechProviders::available()));

        config(['services.tts.providers' => ['openai', 'inconnu', 'openai']]);
        $this->assertSame(['openai'], array_map(fn ($provider) => $provider->name(), SpeechProviders::configured()));
        $this->assertSame([], SpeechProviders::available());

        // Valeurs par défaut sûres : modèle qui suit des consignes de ton, voix masculine posée.
        $this->assertSame('gpt-4o-mini-tts', (new OpenAiSpeech)->model());
        $this->assertSame('cedar', (new OpenAiSpeech)->voice());
    }

    /* ---------- Préparation ---------- */

    public function test_a_gemini_daily_limit_switches_the_whole_preparation_to_openai(): void
    {
        $this->fake(Http::response(self::geminiDailyQuota(), 429));

        $result = app(SpeechPreparer::class)->run(3600);

        // Une seule demande à Gemini, puis tout chez OpenAI.
        $this->assertSame(1, $this->sentTo('gemini'));
        $this->assertSame(self::pieces(), $this->sentTo('openai'));
        $this->assertStringContainsString('Tout est prêt.', SpeechPreparer::summary($result));

        foreach (SpeechPreparer::status()['messages'] as $message) {
            $this->assertSame('openai', $message['provider'], $message['label']);
            $this->assertTrue($message['complete']);
        }

        // OpenAI : modèle, voix, consignes de ton, audio PCM ; la clé seulement dans l'en-tête d'autorisation.
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/audio/speech')
            && $request['model'] === 'gpt-4o-mini-tts'
            && $request['voice'] === 'cedar'
            && $request['response_format'] === 'pcm'
            && $request['instructions'] === config('teacher.welcome.style')
            && $request->hasHeader('Authorization', 'Bearer '.self::OPENAI_KEY));
    }

    public function test_a_message_started_at_gemini_is_prepared_entirely_at_openai_never_mixed(): void
    {
        // Gemini dit 3 paragraphes du premier message, puis atteint sa limite du jour.
        $this->fake(Http::sequence()
            ->push(self::geminiSpeech())->push(self::geminiSpeech())->push(self::geminiSpeech())
            ->whenEmpty(Http::response(self::geminiDailyQuota(), 429)));

        app(SpeechPreparer::class)->run(3600);

        $first = WelcomeMessage::messages()[0];
        $this->assertSame(3, $first->readyCount(app(GeminiSpeech::class)));
        // Le message entier (8 paragraphes, pas seulement les 5 manquants) est préparé chez OpenAI.
        $this->assertTrue($first->isCompleteAt(new OpenAiSpeech));
        $this->assertSame(self::pieces(), $this->sentTo('openai'));
        $this->assertSame('openai', $first->provider()->name());

        // À l'écoute : salutation ET paragraphes avec la seule voix d'OpenAI.
        $played = $this->listen($this->newcomer());

        foreach ($played as $index => $piece) {
            $this->assertSame(200, $piece['status'], "Morceau {$index}");
            $this->assertSame(self::openAiWav(), $piece['body'], "Morceau {$index} : une seule voix par message.");
            $this->assertStringContainsString('voix=openai', $piece['url']);
        }

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/audio/speech') && str_contains($request['input'], 'Koffi'));
        $this->assertSame(0, Http::recorded(fn (Request $request) => str_contains($request->url(), '/interactions') && str_contains(json_encode($request->data()), 'Koffi'))->count());
    }

    public function test_a_message_already_complete_at_gemini_stays_with_gemini_greeting_included(): void
    {
        $this->fake(Http::response(self::geminiSpeech()));
        app(SpeechPreparer::class)->run(3600);
        $this->assertSame(0, $this->sentTo('openai'));

        // Nouvelle préparation : rien n'est redemandé, ni à Gemini ni à OpenAI.
        $before = $this->sentTo('gemini');
        app(SpeechPreparer::class)->run(3600);
        $this->assertSame($before, $this->sentTo('gemini'));

        $played = $this->listen($this->newcomer());

        foreach ($played as $index => $piece) {
            $this->assertSame(self::GEMINI_WAV, $piece['body'], "Morceau {$index}");
            $this->assertStringContainsString('voix=gemini', $piece['url']);
        }

        // La salutation personnalisée est faite par Gemini, le fournisseur du message.
        $this->assertSame($before + 1, $this->sentTo('gemini'));
        $this->assertSame(0, $this->sentTo('openai'));
    }

    public function test_a_failed_greeting_stays_in_text_and_the_message_keeps_its_provider(): void
    {
        // Tout est prêt chez OpenAI ; la salutation échoue chez OpenAI : Gemini n'est PAS appelé à sa place.
        $openAiDown = false;
        Http::fake([
            '*/interactions' => Http::response(self::geminiDailyQuota(), 429),
            '*/audio/speech' => function () use (&$openAiDown) {
                return $openAiDown
                    ? Http::response(['error' => ['message' => 'Server error', 'type' => 'server_error']], 500)
                    : Http::response(self::OPENAI_PCM, 200, ['Content-Type' => 'audio/pcm']);
            },
        ]);
        app(SpeechPreparer::class)->run(3600);
        $gemini = $this->sentTo('gemini');
        $openAiDown = true;

        $played = $this->listen($this->newcomer());

        $this->assertSame(503, $played[0]['status']);
        $this->assertSame($gemini, $this->sentTo('gemini'));

        foreach (array_slice($played, 1) as $index => $piece) {
            $this->assertSame(200, $piece['status']);
            $this->assertSame(self::openAiWav(), $piece['body'], 'Paragraphe '.($index + 1));
        }

        $this->assertSame('openai', SpeechLog::recent()[0]['provider']);
        $this->assertSame('Bienvenue, salutation', SpeechLog::recent()[0]['label']);
    }

    public function test_without_openai_key_openai_is_simply_ignored(): void
    {
        config(['services.openai.key' => null]);
        $this->fake(Http::response(self::geminiDailyQuota(), 429));

        $result = app(SpeechPreparer::class)->run(3600);

        $this->assertSame(0, $this->sentTo('openai'));
        $this->assertSame(SpeechFailed::QUOTA_DAILY, $result['stopped']);
        $this->assertStringContainsString('Relancez demain', SpeechPreparer::summary($result));

        // À l'écoute : la salutation n'est jamais demandée à OpenAI ; les paragraphes restent en texte.
        $played = $this->listen($this->newcomer());
        $this->assertSame(0, $this->sentTo('openai'));
        $this->assertFalse($played[1]['ready']);
    }

    public function test_the_order_openai_alone_never_calls_gemini(): void
    {
        config(['services.tts.providers' => ['openai']]);
        $this->fake(Http::response(self::geminiSpeech()));

        app(SpeechPreparer::class)->run(3600);
        $this->listen($this->newcomer());

        $user = User::factory()->create();
        $user->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 300, 'level' => 'B1', 'status' => 'completed']);
        $this->actingAs($user)->get('/evaluation/message-vocal')->assertOk()->assertSee(self::openAiWav(), false);

        $this->assertSame(0, $this->sentTo('gemini'));
        $this->assertSame(self::pieces() + 1, $this->sentTo('openai'));
    }

    public function test_the_level_message_falls_back_to_openai_when_gemini_fails_while_listening(): void
    {
        $this->fake(Http::response(self::geminiDailyQuota(), 429));
        $user = User::factory()->create();
        $user->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 300, 'level' => 'A2', 'status' => 'completed']);

        $this->actingAs($user)->get('/evaluation/message-vocal')->assertOk()->assertSee(self::openAiWav(), false);
        $this->assertTrue(PrivateStorage::disk()->exists(LevelMessage::path(Level::A2, new OpenAiSpeech)));

        // Gardé : plus aucune demande ; et le message reste celui d'OpenAI.
        $sent = Http::recorded()->count();
        $this->actingAs($user)->get('/evaluation/message-vocal')->assertOk()->assertSee(self::openAiWav(), false);
        $this->assertSame($sent, Http::recorded()->count());
    }

    public function test_openai_exhausted_credit_counts_as_a_daily_limit(): void
    {
        $this->fake(
            Http::response(self::geminiDailyQuota(), 429),
            Http::response(['error' => ['message' => 'You exceeded your current quota.', 'type' => 'insufficient_quota', 'code' => 'insufficient_quota']], 429),
        );

        $result = app(SpeechPreparer::class)->run(3600);

        $this->assertSame([1, 1], [$this->sentTo('gemini'), $this->sentTo('openai')]);
        $this->assertSame(SpeechFailed::QUOTA_DAILY, $result['stopped']);
        $this->assertStringContainsString('Gemini (Google) et OpenAI', SpeechPreparer::summary($result));
    }

    /* ---------- Journal et consommation ---------- */

    public function test_failures_are_logged_with_their_provider_and_never_the_key(): void
    {
        Log::spy();
        $this->fake(Http::response(self::geminiDailyQuota(), 429), Http::response(['error' => ['message' => 'Incorrect API key provided.', 'type' => 'invalid_request_error', 'code' => 'invalid_api_key']], 401));

        app(SpeechPreparer::class)->run(3600);

        // Clé refusée : OpenAI n'est plus appelé pour le reste de la préparation.
        $this->assertSame(1, $this->sentTo('openai'));
        $failure = SpeechLog::recent()[0];
        $this->assertSame('openai', $failure['provider']);
        $this->assertSame(401, $failure['status']);
        $this->assertSame('gemini', SpeechLog::recent()[1]['provider']);
        $this->assertStringNotContainsString(self::OPENAI_KEY, json_encode(SpeechLog::recent()));
        $this->assertStringNotContainsString(self::GEMINI_KEY, json_encode(SpeechLog::recent()));

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $message === 'OpenAI : synthèse vocale en échec.'
            && $context['provider'] === 'openai'
            && $context['model'] === 'gpt-4o-mini-tts'
            && ! str_contains(json_encode($context), self::OPENAI_KEY))->once();
    }

    public function test_each_synthesis_is_counted_with_its_provider_and_an_estimated_cost(): void
    {
        $pcm = str_repeat("\x01\x02", 24000 * 3); // 3 secondes de voix
        $this->fake(
            Http::sequence()->push(self::geminiSpeech())->whenEmpty(Http::response(self::geminiDailyQuota(), 429)),
            Http::response($pcm, 200, ['Content-Type' => 'audio/pcm']),
        );

        app(SpeechPreparer::class)->run(3600);

        $this->assertSame(1, SpeechSynthesis::query()->where('provider', 'gemini')->count());
        $openai = SpeechSynthesis::query()->where('provider', 'openai');
        $this->assertSame(self::pieces(), (clone $openai)->count());
        $row = $openai->first();
        $this->assertSame(3000, $row->audio_milliseconds);
        $this->assertSame('cedar', $row->voice);
        $this->assertEqualsWithDelta(0.015 * 3 / 60, $row->cost_usd, 0.000001);
        $this->assertSame(0.0, SpeechSynthesis::query()->where('provider', 'gemini')->sole()->cost_usd);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.usage.index'))->assertOk()
            ->assertSee('Synthèse vocale des messages')
            ->assertSeeInOrder(['Gemini (Google)', '1', 'OpenAI', (string) self::pieces()]);
    }

    /* ---------- Anciens fichiers et diagnostic ---------- */

    public function test_gemini_files_prepared_before_the_providers_are_kept(): void
    {
        $gemini = app(GeminiSpeech::class);
        $disk = PrivateStorage::disk();

        foreach (WelcomeMessage::commonParagraphs() as $piece) {
            $disk->put(WelcomeMessage::DIRECTORY.'/'.substr(basename(WelcomeMessage::path($piece['text'], $gemini)), strlen('gemini-')), self::GEMINI_WAV);
        }

        foreach (LevelMessage::all() as $item) {
            $disk->put(LevelMessage::DIRECTORY.'/'.$item['level']->value.'-'.substr(basename(LevelMessage::path($item['level'], $gemini)), strlen($item['level']->value.'-gemini-')), self::GEMINI_WAV);
        }

        $this->fake(Http::response(self::geminiSpeech()));
        $result = app(SpeechPreparer::class)->run(3600);

        Http::assertNothingSent();
        $this->assertStringContainsString('Tout est prêt.', SpeechPreparer::summary($result));
    }

    public function test_the_diagnostic_shows_each_provider_and_the_provider_of_each_message(): void
    {
        $this->fake(Http::response(self::geminiDailyQuota(), 429));
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from(route('admin.voice-diagnostic.index'))->post(route('admin.voice-diagnostic.test', 'openai'))
            ->assertSessionHas('diagnostic', fn (array $result) => $result['ok'] && str_contains($result['text'], 'OpenAI : réussi') && str_contains($result['text'], 'cedar'));
        $this->post(route('admin.voice-diagnostic.test', 'gemini'))
            ->assertSessionHas('diagnostic', fn (array $result) => ! $result['ok'] && str_contains($result['text'], 'code HTTP 429'));
        $this->post(route('admin.voice-diagnostic.prepare'));
        $this->post('/admin/diagnostic-voix/essai/inconnu')->assertNotFound();

        $page = $this->get(route('admin.voice-diagnostic.index'))->assertOk()
            ->assertSee('Clé OPENAI_API_KEY : <strong>présente</strong>', false)
            ->assertSee('Gemini (Google), puis OpenAI')
            ->assertSeeInOrder(['Bienvenue, formule Essentiel', '0 sur 8', '8 sur 8', 'OpenAI'])
            ->assertSeeInOrder(['Dernières erreurs', 'Gemini (Google)', 'Limite de demandes du jour']);

        $this->assertStringNotContainsString(self::OPENAI_KEY, $page->getContent());
        $this->assertStringNotContainsString(self::GEMINI_KEY, $page->getContent());
    }
}
