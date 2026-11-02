<?php

namespace Tests\Feature\Live;

use App\Models\ConversationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Micro trop faible (fondateur, Pixel 6, 2 novembre 2026). Le « mode appel » essayé d'abord passait la voix au
 * volume d'appel sans aider le micro : retiré, la voix reste au volume normal du téléphone. Restent : le volume du
 * micro remonté d'après la voix de l'élève (MicBoost) et, sur Android, la réduction de bruit coupée (elle effaçait
 * une voix faible).
 */
class WeakMicrophoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_call_mode_is_gone_and_the_voice_stays_at_the_normal_volume(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="call-mode"', $html);
        $this->assertStringNotContainsString('mode appel', $html);

        $js = file_get_contents(public_path('js/live-session.js'));
        $this->assertStringNotContainsString('setCallMode', $js);
        // L'ancien choix gardé sur le téléphone est effacé : retour au son normal.
        $this->assertStringContainsString("localStorage.removeItem('call-mode')", $js);
    }

    public function test_android_uses_the_normal_call_audio_by_default(): void
    {
        // Comme un appel ou Google Meet : annulation d'écho et réduction de bruit, voix au volume normal.
        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->getContent();
        $this->assertStringContainsString('"echo_cancellation_android":true', $html);
        $this->assertStringContainsString('"noise_suppression_android":true', $html);
        $this->assertStringContainsString('"voice_gain":1', $html);

        $js = file_get_contents(public_path('js/live-session.js'));
        $this->assertStringContainsString('noiseSuppression: NOISE_SUPPRESSION,', $js);
        $this->assertStringContainsString('audioConfig.echo_cancellation_android ?? true', $js);
        $this->assertStringContainsString('IS_ANDROID ? audioConfig.noise_suppression_android ?? true : true', $js);
    }

    public function test_a_too_weak_microphone_is_turned_up_automatically(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();
        $this->assertStringContainsString('"mic_boost":{"max":40', $html);

        $js = file_get_contents(public_path('js/live-session.js'));
        $this->assertStringContainsString('source.connect(micBoost).connect(micLimiter).connect(micBus);', $js);
        $this->assertStringContainsString('source.connect(micBoost);', $js);
        $this->assertArrayHasKey('mic_boost', ConversationEvent::KINDS);

        config(['platform.live.mic_boost.enabled' => false]);
        $this->assertStringContainsString('"mic_boost":null', $this->get('/evaluation')->getContent());
    }
}
