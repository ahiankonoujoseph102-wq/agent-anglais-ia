<?php

namespace Tests\Feature\Live;

use App\Enums\Level;
use App\Models\Lesson;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Gemini\VoiceSampler;
use App\Support\Live\LiveProviders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\Concerns\IgnoresSignalSilence;
use Tests\TestCase;

/**
 * Troisième clé Gemini (encore un autre compte Google) : dernier secours des séances en direct, après la deuxième
 * clé. Absente, rien ne change ; jamais affichée.
 */
class GeminiThirdKeyTest extends TestCase
{
    use BuildsPrograms, IgnoresSignalSilence, RefreshDatabase;

    private const KEYS = ['cle-gemini-1' => 'une', 'cle-gemini-2' => 'deux', 'cle-gemini-3' => 'trois'];

    /** Réponse de Google selon la clé. */
    private array $gemini = ['cle-gemini-1' => 429, 'cle-gemini-2' => 429, 'cle-gemini-3' => 200];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.key' => 'cle-gemini-1',
            'services.gemini.key_2' => 'cle-gemini-2',
            'services.gemini.key_3' => 'cle-gemini-3',
            'services.openai.key' => null,
            'services.live.providers' => ['gemini', 'openai', 'gemini2', 'gemini3'],
        ]);
        $this->freezeSecond();

        Http::fake([
            '*/auth_tokens' => fn (Request $request) => ($this->gemini[$request->header('x-goog-api-key')[0] ?? ''] ?? 500) === 200
                ? Http::response(['name' => 'auth_tokens/jeton-'.self::KEYS[$request->header('x-goog-api-key')[0]]])
                : Http::response(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded for requests per day.']], 429),
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
            ->map(fn (array $pair) => self::KEYS[$pair[0]->header('x-goog-api-key')[0]])
            ->values()->all();
    }

    public function test_the_third_key_takes_over_when_the_two_others_are_at_their_limit(): void
    {
        $this->assertSame('Gemini (troisième clé)', LiveProviders::label('gemini3'));
        $this->assertSame('GEMINI_API_KEY_3', LiveProviders::keyVariable('gemini3'));
        $this->assertTrue(LiveProviders::isGemini('gemini3'));

        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk()
            ->assertJsonPath('provider', 'gemini3')
            ->assertJsonStructure(['websocket_url', 'setup']);

        $this->assertSame(['une', 'deux', 'trois'], $this->keysSent());
        $this->assertSame('gemini3', Lesson::query()->whereBelongsTo($user)->value('live_provider'));
    }

    public function test_an_old_provider_list_still_uses_the_third_key_at_the_end(): void
    {
        config(['services.live.providers' => ['gemini', 'openai', 'gemini2']]);

        $this->assertSame(['gemini', 'openai', 'gemini2', 'gemini3'], LiveProviders::configured());

        config(['services.gemini.key_3' => null]);
        $this->assertSame(['gemini', 'openai', 'gemini2'], LiveProviders::configured());
    }

    public function test_the_diagnostic_shows_the_third_key_without_ever_showing_it(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.text-diagnostic.index'))->assertOk()
            ->assertSee('Gemini (troisième clé)')
            ->assertSeeText('Clé GEMINI_API_KEY_3 : présente')
            ->assertDontSee('cle-gemini-3');

        $this->actingAs($admin)->post(route('admin.live-trial.test', 'gemini3'))->assertRedirect();
        $this->assertSame(['trois'], $this->keysSent());
    }

    public function test_voice_messages_also_use_the_third_key_last(): void
    {
        $this->assertSame(['cle-gemini-1', 'cle-gemini-2', 'cle-gemini-3'], VoiceSampler::keys());
    }
}
