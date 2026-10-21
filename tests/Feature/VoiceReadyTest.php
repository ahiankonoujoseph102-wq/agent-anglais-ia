<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\LevelMessage;
use App\Support\Gemini\WelcomeMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Messages vocaux prêts à l'avance : le message du résultat se prépare dès le verdict, la page de résultat demande
 * son état et le joue dès qu'il est prêt ; la salutation de bienvenue (le prénom) se prépare dès l'ouverture d'un
 * accès. Jamais « Préparation du message… ».
 */
class VoiceReadyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'filesystems.private_disk' => 'local',
            'services.gemini.key' => 'cle-gemini-de-test',
            'services.openai.key' => null,
            'services.tts.providers' => ['gemini'],
        ]);
        Http::fake([
            '*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton']),
            '*/interactions' => Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'audio', 'data' => base64_encode('RIFF-voix')]]]]]),
        ]);
    }

    private function assessed(User $user, Level $level): void
    {
        $user->forceFill(['level' => $level])->save();
        $user->assessment()->create([
            'status' => AssessmentStatus::Completed,
            'started_at' => now()->subHour(),
            'ended_at' => now()->subHour()->addMinutes(5),
            'allowed_seconds' => 300,
            'used_seconds' => 300,
            'level' => $level,
        ]);
    }

    private function synthesesFor(string $text): int
    {
        return Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/interactions') && str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), $text))->count();
    }

    public function test_the_verdict_prepares_the_result_message_after_the_response(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk();
        $this->assertFalse(LevelMessage::ready(Level::B1));

        $this->actingAs($user)->postJson('/evaluation/resultat', ['level' => 'B1', 'feedback' => 'Vous parlez bien.'])->assertOk();

        $this->assertTrue(LevelMessage::ready(Level::B1), 'prêt avant même l\'arrivée sur la page de résultat');
    }

    public function test_the_result_page_asks_the_state_and_a_missing_message_is_prepared_once(): void
    {
        $user = User::factory()->create();
        $this->assessed($user, Level::A2);

        $this->actingAs($user)->getJson(route('assessment.voice.status'))->assertOk()->assertExactJson(['ready' => false]);
        $this->assertTrue(LevelMessage::ready(Level::A2));
        $this->actingAs($user)->getJson(route('assessment.voice.status'))->assertOk()->assertExactJson(['ready' => true]);

        $this->assertSame(1, $this->synthesesFor(LevelMessage::text(Level::A2)));
        $this->actingAs($user)->get(route('assessment.voice'))->assertOk()->assertHeader('Content-Type', 'audio/wav');
    }

    public function test_the_result_page_waits_professionally_and_never_says_preparing(): void
    {
        $user = User::factory()->create();
        $this->assessed($user, Level::B2);

        $html = $this->actingAs($user)->get('/evaluation')->assertOk()->getContent();

        $this->assertStringContainsString('data-status-url="'.route('assessment.voice.status').'"', $html);
        $this->assertStringContainsString('js/voice-wait.js', $html);
        $this->assertStringContainsString('data-level-text', $html);
        // Textes lisibles par le navigateur (apostrophes comprises : « n'est pas disponible »).
        preg_match("/data-wait='([^']*)'/", $html, $match);
        $wait = json_decode(html_entity_decode($match[1]), true);
        $this->assertSame(config('platform.speech.messages.unavailable'), $wait['unavailable']);
        $this->assertSame(30, $wait['late_seconds']);
        $this->assertSame('Un instant…', config('platform.speech.messages.instant'));
        $this->assertSame('Le message arrive. Vous pouvez le lire juste en dessous : il démarrera tout seul.', config('platform.speech.messages.late'));
        $this->assertSame(30, config('platform.speech.late_after_seconds'));

        foreach (['js/level-message.js', 'js/welcome.js', 'js/voice-wait.js'] as $script) {
            $this->assertStringNotContainsString('Préparation du message', file_get_contents(public_path($script)), $script);
        }
    }

    public function test_offering_an_access_prepares_the_welcome_greeting_with_the_first_name(): void
    {
        $learner = User::factory()->unwelcomed()->create(['name' => 'Kossiwa Mensah']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.grant', $learner), ['plan' => 'essentiel', 'duration' => '3', 'confirmed' => 1])->assertRedirect();

        $texts = WelcomeMessage::texts($learner->fresh());
        $this->assertStringContainsString('Kossiwa', $texts['opening']);
        $this->assertSame(1, $this->synthesesFor('Kossiwa'));
        Storage::disk('local')->assertExists(WelcomeMessage::path($texts['opening'], WelcomeMessage::providerFor($texts), opening: true));
    }

    public function test_nothing_is_prepared_for_a_learner_already_welcomed_or_without_any_voice_provider(): void
    {
        $admin = User::factory()->admin()->create();
        $welcomed = User::factory()->create(['name' => 'Yao Agbeko', 'welcomed_at' => now()]);
        $this->actingAs($admin)->post(route('admin.users.grant', $welcomed), ['plan' => 'essentiel', 'duration' => '3', 'confirmed' => 1])->assertRedirect();
        $this->assertSame(0, $this->synthesesFor('Yao'));

        config(['services.gemini.key' => null]);
        $user = User::factory()->create();
        $this->assessed($user, Level::C1);
        $this->actingAs($user)->getJson(route('assessment.voice.status'))->assertOk()->assertExactJson(['ready' => false]);
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/interactions'));
    }
}
