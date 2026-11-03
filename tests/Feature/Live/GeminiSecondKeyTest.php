<?php

namespace Tests\Feature\Live;

use App\Enums\Level;
use App\Models\Lesson;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Live\LiveLog;
use App\Support\Live\LiveProviders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\Concerns\IgnoresSignalSilence;
use Tests\TestCase;

/**
 * Deuxième clé Gemini (autre compte Google, autre limite) : troisième secours des séances en direct, après Gemini
 * (clé principale) et OpenAI. Absente ou invalide, le site marche comme avant ; jamais affichée.
 */
class GeminiSecondKeyTest extends TestCase
{
    use BuildsPrograms, IgnoresSignalSilence, RefreshDatabase;

    private const MAIN_KEY = 'cle-gemini-principale';

    private const SECOND_KEY = 'cle-gemini-deuxieme';

    /** Réponse de Google selon la clé : 200, 429 ou 400 (clé invalide). */
    private array $gemini = [self::MAIN_KEY => 429, self::SECOND_KEY => 200];

    private int $openaiCall = 429;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.key' => self::MAIN_KEY,
            'services.gemini.key_2' => self::SECOND_KEY,
            'services.openai.key' => 'sk-cle-openai',
            'services.live.providers' => ['gemini', 'openai', 'gemini2'],
        ]);
        $this->freezeSecond();

        Http::fake([
            '*/auth_tokens' => fn (Request $request) => match ($this->gemini[$request->header('x-goog-api-key')[0] ?? ''] ?? 500) {
                200 => Http::response(['name' => 'auth_tokens/jeton-'.($request->header('x-goog-api-key')[0] === self::SECOND_KEY ? 'deux' : 'un')]),
                429 => Http::response(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded for requests per day.']], 429),
                400 => Http::response(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'message' => 'API key not valid. Please pass a valid API key.', 'details' => [['reason' => 'API_KEY_INVALID']]]], 400),
                default => Http::response(['error' => ['code' => 500, 'message' => 'Internal error']], 500),
            },
            'api.openai.com/v1/realtime/calls' => fn () => $this->openaiCall === 201
                ? Http::response("v=0\r\n", 201, ['Location' => '/v1/realtime/calls/rtc_1'])
                : Http::response(['error' => ['message' => 'You exceeded your current quota.', 'code' => 'insufficient_quota']], $this->openaiCall),
            '*/realtime/calls/*/hangup' => Http::response('', 200),
            '*/interactions' => Http::response([], 503),
            'api.openai.com/v1/responses' => Http::response([], 503),
        ]);
    }

    private function subscriber(): User
    {
        $user = User::factory()->level(Level::A2)->create(['name' => 'Ama']);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user);

        return $user;
    }

    private function keysSent(): array
    {
        return Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/auth_tokens'))
            ->map(fn (array $pair) => $pair[0]->header('x-goog-api-key')[0] === self::SECOND_KEY ? 'deuxième' : 'principale')
            ->values()->all();
    }

    public function test_the_gemini_keys_come_first_and_openai_always_last(): void
    {
        $this->assertStringContainsString("'gemini,gemini2,gemini3,openai'", (string) file_get_contents(config_path('services.php')));
        $this->assertSame('Gemini (deuxième clé)', LiveProviders::label('gemini2'));
        $this->assertSame('GEMINI_API_KEY_2', LiveProviders::keyVariable('gemini2'));
        // Même une ancienne liste LIVE_PROVIDERS qui met OpenAI avant la deuxième clé : OpenAI passe en dernier.
        $this->assertSame(['gemini', 'gemini2', 'openai'], LiveProviders::ordered());
    }

    public function test_main_key_limited_the_lesson_starts_with_the_second_key_before_openai(): void
    {
        $this->openaiCall = 201;

        $this->actingAs($user = $this->subscriber())->postJson('/seance/connexion')
            ->assertOk()
            ->assertJsonPath('provider', 'gemini2')
            ->assertJsonStructure(['websocket_url', 'setup']);

        $this->assertTrue(LiveProviders::isPaused('gemini'));
        $this->assertSame('gemini2', Lesson::query()->whereBelongsTo($user)->value('live_provider'));
        $this->assertSame(['principale', 'deuxième'], $this->keysSent());
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'realtime/calls'));
    }

    public function test_openai_is_used_only_when_both_gemini_keys_are_at_their_limit(): void
    {
        $this->openaiCall = 201;
        $this->gemini[self::SECOND_KEY] = 429;

        $this->actingAs($this->subscriber())->postJson('/seance/connexion')->assertOk()->assertJsonPath('provider', 'openai');
        $this->assertSame(['principale', 'deuxième'], $this->keysSent());
    }

    public function test_a_browser_that_cannot_reach_gemini_goes_straight_to_openai(): void
    {
        $this->openaiCall = 201;
        $this->gemini[self::MAIN_KEY] = 200;

        $this->actingAs($user = $this->subscriber())->postJson('/seance/connexion')->assertOk()->assertJsonPath('provider', 'gemini');

        // Le téléphone n'a pas pu joindre Gemini : une autre clé Gemini passerait par le même service, OpenAI prend le relais.
        $this->actingAs($user)->postJson('/seance/connexion', ['failed' => 'gemini'])->assertOk()->assertJsonPath('provider', 'openai');
        $this->assertSame(['principale'], $this->keysSent());
    }

    public function test_without_a_second_key_nothing_changes(): void
    {
        config(['services.gemini.key_2' => null]);
        $this->gemini[self::MAIN_KEY] = 200;

        $this->assertSame(['gemini', 'openai'], LiveProviders::available());
        $this->actingAs($this->subscriber())->postJson('/seance/connexion')->assertOk()->assertJsonPath('provider', 'gemini');

        // Clé principale limitée, pas d'OpenAI ni de deuxième clé : le message habituel, rien de cassé.
        config(['services.openai.key' => null]);
        $this->gemini[self::MAIN_KEY] = 429;
        $this->actingAs($this->subscriber())->postJson('/seance/connexion')->assertStatus(503);
    }

    public function test_an_invalid_second_key_is_set_aside_and_the_site_keeps_working(): void
    {
        $this->gemini[self::SECOND_KEY] = 400;
        config(['services.openai.key' => null]);

        $this->actingAs($this->subscriber())->postJson('/seance/connexion')->assertStatus(503);
        $this->assertTrue(LiveProviders::isPaused('gemini2'));
        $this->assertSame('Gemini (deuxième clé)', LiveLog::recent()[0]['provider_label']);

        foreach (LiveLog::recent() as $entry) {
            $this->assertStringNotContainsString(self::SECOND_KEY, json_encode($entry));
        }

        // La clé principale revient : la séance commence chez elle, la deuxième clé n'est pas réessayée.
        $this->gemini[self::MAIN_KEY] = 200;
        cache()->forget('live-pause:gemini');
        $this->actingAs($this->subscriber())->postJson('/seance/connexion')->assertOk()->assertJsonPath('provider', 'gemini');
    }

    public function test_the_diagnostic_shows_and_tests_the_second_key_without_ever_showing_it(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.text-diagnostic.index'))->assertOk()
            ->assertSee('Gemini (deuxième clé)')
            ->assertSeeText('Clé GEMINI_API_KEY_2 : présente')
            ->assertDontSee(self::SECOND_KEY);

        $this->actingAs($admin)->post(route('admin.live-trial.test', 'gemini2'))->assertRedirect();
        $this->assertSame(['deuxième'], $this->keysSent());
        $this->actingAs($admin)->get(route('admin.live-trial.show', 'gemini2'))->assertOk();
    }
}
