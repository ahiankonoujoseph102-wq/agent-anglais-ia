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
                    'endOfSpeechSensitivity' => 'END_SENSITIVITY_HIGH',
                    'prefixPaddingMs' => 300,
                    'silenceDurationMs' => 800,
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
            ->assertJsonPath('setup.realtimeInputConfig.automaticActivityDetection.silenceDurationMs', 800);

        $this->assertStringContainsString('Do not greet them again', $response->json('cues.pause_resume'));
        $this->assertStringContainsString('Do not greet the learner again', $response->json('cues.resume'));
        // Bouton « Relancer Teacher Joe » : l'apprenant a fini de parler et attend la réponse.
        $this->assertStringContainsString('has been waiting for your answer', $response->json('cues.nudge'));
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

    public function test_conversation_screen_has_a_mic_meter_a_mic_choice_and_the_silent_mic_warning(): void
    {
        config(['platform.live.voice_gain' => 2.5, 'platform.live.echo_cancellation' => false]);

        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();

        $this->assertStringContainsString('id="mic-level"', $html);
        $this->assertStringContainsString('Micro utilisé', $html);
        $this->assertStringContainsString('id="mic-select"', $html);
        $this->assertStringContainsString('Nous ne vous entendons pas. Vérifiez votre micro. Si des écouteurs ou une enceinte Bluetooth sont connectés, portez-les ou déconnectez-les.', $html);
        $this->assertStringContainsString('js/mic-input.js', $html);
        $this->assertStringContainsString("data-audio='{\"echo_cancellation\":false,\"echo_cancellation_android\":false,\"voice_gain\":2.5,\"silent_mic_seconds\":8,\"end_of_turn_seconds\":2,\"instant_seconds\":2,\"no_answer_seconds\":8,\"stuck_seconds\":20,\"voice_stuck_seconds\":4,\"voice_gap_hold_seconds\":3,\"greeting_wait_seconds\":5,\"messages\":{", $html);
    }

    public function test_voice_gain_and_echo_cancellation_defaults(): void
    {
        $this->assertSame(2.5, config('platform.live.voice_gain'));
        $this->assertTrue(config('platform.live.echo_cancellation'));
        $this->assertFalse(config('platform.live.echo_cancellation_android'), 'sur Android : jamais le mode appel par défaut');

        $config = file_get_contents(config_path('platform.php'));
        $this->assertStringContainsString("env('LIVE_VOICE_GAIN')", $config);
        $this->assertStringContainsString("env('GEMINI_VOICE_GAIN')", $config);
        $this->assertStringContainsString("env('LIVE_ECHO_CANCELLATION')", $config);
        $this->assertStringContainsString("env('LIVE_ECHO_CANCELLATION_ANDROID')", $config);

        // Le navigateur choisit le réglage d'Android d'après l'appareil.
        $script = file_get_contents(public_path('js/live-session.js'));
        $this->assertStringContainsString('/Android/i.test(navigator.userAgent', $script);
        $this->assertStringContainsString('echoCancellation: ECHO_CANCELLATION', $script);
    }

    public function test_empty_or_explicit_environment_values_are_read_safely(): void
    {
        $read = function (array $values) {
            foreach ($values as $name => $value) {
                $value === null ? putenv($name) : putenv("{$name}={$value}");
                $_ENV[$name] = $_SERVER[$name] = $value;

                if ($value === null) {
                    unset($_ENV[$name], $_SERVER[$name]);
                }
            }

            return (require config_path('platform.php'))['live'];
        };

        try {
            // Variables vides (comme dans .env.example) : valeurs par défaut, jamais une voix muette.
            $live = $read(['LIVE_VOICE_GAIN' => '', 'GEMINI_VOICE_GAIN' => '', 'LIVE_ECHO_CANCELLATION' => '', 'LIVE_ECHO_CANCELLATION_ANDROID' => '']);
            $this->assertSame(2.5, $live['voice_gain']);
            $this->assertTrue($live['echo_cancellation']);
            $this->assertFalse($live['echo_cancellation_android']);

            // L'ancienne variable est encore lue ; la nouvelle l'emporte.
            $live = $read(['GEMINI_VOICE_GAIN' => '3', 'LIVE_ECHO_CANCELLATION' => 'false', 'LIVE_ECHO_CANCELLATION_ANDROID' => 'true']);
            $this->assertSame(3.0, $live['voice_gain']);
            $this->assertFalse($live['echo_cancellation']);
            $this->assertTrue($live['echo_cancellation_android']);

            $live = $read(['LIVE_VOICE_GAIN' => '1.5', 'GEMINI_VOICE_GAIN' => '3']);
            $this->assertSame(1.5, $live['voice_gain']);

            $live = $read(['LIVE_VOICE_GAIN' => '0', 'GEMINI_VOICE_GAIN' => '0', 'LIVE_ECHO_CANCELLATION' => 'true', 'LIVE_ECHO_CANCELLATION_ANDROID' => 'no']);
            $this->assertSame(2.5, $live['voice_gain']);
            $this->assertTrue($live['echo_cancellation']);
            $this->assertFalse($live['echo_cancellation_android']);
        } finally {
            $read(['LIVE_VOICE_GAIN' => null, 'GEMINI_VOICE_GAIN' => null, 'LIVE_ECHO_CANCELLATION' => null, 'LIVE_ECHO_CANCELLATION_ANDROID' => null]);
        }
    }
}
