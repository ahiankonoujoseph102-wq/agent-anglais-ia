<?php

namespace Tests\Feature\Live;

use App\Models\ConversationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Mode appel » du micro (demande du fondateur, 2 novembre 2026 : depuis le son plus fort, Teacher Joe ne
 * l'entendait plus sur son Pixel, même ses mots ne s'écrivaient pas). Sur Android, le micro s'ouvre sans
 * l'annulation d'écho (voix plus forte) ; certains téléphones gardent alors le micro muet. Le mode appel rouvre
 * le même micro avec l'annulation d'écho, sans couper la séance : tout seul quand le micro n'entend rien
 * pendant le tour de l'élève, ou par un lien ; gardé sur ce téléphone.
 */
class CallModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_session_screen_offers_the_call_mode_under_the_microphone(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();

        $panel = strpos($html, 'id="mic-panel"');
        $button = strpos($html, 'id="call-mode"');
        $this->assertNotFalse($button);
        $this->assertGreaterThan($panel, $button, 'dans le bloc du micro, visible seulement pendant la séance');
        $this->assertLessThan(strpos($html, 'id="mic-warning"'), $button);
        $this->assertStringContainsString('ne vous entend pas ? Passer en mode appel', $html);
        $this->assertStringContainsString('Revenir au son normal', $html);
        $this->assertStringContainsString('le mode appel est activé. Parlez à nouveau.', $html);
        // Jamais un mot technique pour l'élève.
        $this->assertStringNotContainsString('annulation d', strip_tags(preg_replace('/<!--.*?-->|\{\{--.*?--\}\}/s', '', $html)));
    }

    public function test_the_browser_switches_by_itself_once_when_the_microphone_hears_nothing(): void
    {
        $js = file_get_contents(public_path('js/live-session.js'));

        // Micro muet pendant le tour de l'élève, sur Android sans annulation d'écho : mode appel, une fois.
        $this->assertMatchesRegularExpression('/IS_ANDROID && !echoCancellation\(\) && !state\.callModeTried/', $js);
        $this->assertStringContainsString('setCallMode(true, true)', $js);
        // Le même micro est rouvert avec l'annulation d'écho, sans couper la conversation.
        $this->assertStringContainsString('echoCancellation: echoCancellation(),', $js);
        $this->assertStringContainsString("const CALL_MODE = 'call-mode';", $js);
        $this->assertArrayHasKey('mic_call_mode', ConversationEvent::KINDS);
    }

    public function test_a_too_weak_microphone_is_turned_up_automatically(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();
        $this->assertStringContainsString('"mic_boost":{"max":24', $html);

        $js = file_get_contents(public_path('js/live-session.js'));
        // Gain automatique puis limiteur, AVANT le bus du micro (envoi, témoin, vidéo) ; un changement de micro le garde.
        $this->assertStringContainsString('source.connect(micBoost).connect(micLimiter).connect(micBus);', $js);
        $this->assertStringContainsString('source.connect(micBoost);', $js);
        $this->assertArrayHasKey('mic_boost', ConversationEvent::KINDS);

        config(['platform.live.mic_boost.enabled' => false]);
        $html = $this->get('/evaluation')->getContent();
        $this->assertStringContainsString('"mic_boost":null', $html);
    }
}
