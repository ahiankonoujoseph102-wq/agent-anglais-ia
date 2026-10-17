<?php

namespace Tests\Feature\Live;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Écoute du professeur : détection de parole figée dans le jeton, réglages
 * anti-écho et pause transmis au navigateur, boutons de l'écran de conversation.
 */
class VoiceConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton123'])]);
    }

    public function test_speech_detection_for_hesitant_learners_is_locked_in_the_ephemeral_token(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/evaluation/connexion')->assertOk();

        Http::assertSent(function (Request $request) {
            $config = $request['bidiGenerateContentSetup']['realtimeInputConfig'];

            return $config === [
                'automaticActivityDetection' => [
                    'disabled' => false,
                    'startOfSpeechSensitivity' => 'START_SENSITIVITY_LOW',
                    'endOfSpeechSensitivity' => 'END_SENSITIVITY_LOW',
                    'prefixPaddingMs' => 300,
                    'silenceDurationMs' => 1200,
                ],
                'activityHandling' => 'START_OF_ACTIVITY_INTERRUPTS',
            ];
        });
    }

    public function test_the_browser_receives_the_echo_guard_the_pause_limit_and_the_pause_cue(): void
    {
        config(['platform.live.echo_guard_ms' => 450, 'platform.live.pause_max_seconds' => 90]);

        $response = $this->actingAs(User::factory()->create())->postJson('/evaluation/connexion')
            ->assertOk()
            ->assertJsonPath('echo_guard_ms', 450)
            ->assertJsonPath('pause_max_seconds', 90)
            ->assertJsonPath('setup.realtimeInputConfig.automaticActivityDetection.silenceDurationMs', 1200);

        $this->assertStringContainsString('Do not greet them again', $response->json('cues.pause_resume'));
        $this->assertStringContainsString('Do not greet the learner again', $response->json('cues.resume'));
    }

    public function test_conversation_screen_has_large_pause_mute_and_interrupt_buttons(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/evaluation')
            ->assertOk()
            ->assertSee('id="pause"', false)
            ->assertSee('Pause')
            ->assertSee('Reprendre')
            ->assertSee('id="mute"', false)
            ->assertSee('Couper le micro')
            ->assertSee('Interrompre le professeur')
            ->assertSee('Le temps continue pendant la pause.')
            ->assertSee('Au-delà de 2 minutes, la conversation se termine.')
            ->assertSee('data-teacher-name="'.config('avatar.model.name').'"', false)
            ->assertSee('id="turn"', false);
    }
}
