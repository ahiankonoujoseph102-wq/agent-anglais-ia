<?php

namespace Tests\Feature\Live;

use App\Models\User;
use App\Support\Confirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vidéo de « Enregistrer ma séance » pas encore téléchargée : rappel visible et confirmation avant de quitter la page
 * (public/js/video-guard.js, testé par tests/js/video-guard.test.mjs).
 */
class VideoLeaveGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_session_page_carries_the_reminder_and_the_leave_confirmation(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();

        // Rappel caché jusqu'à ce que la vidéo soit prête, puis le bouton, puis la phrase après le téléchargement.
        $this->assertMatchesRegularExpression('/<p id="record-ready" class="recorder-ready" role="status" hidden>.*Votre vidéo est prête\. Téléchargez-la avant de quitter cette page\.<\/span><\/p>/s', $html);
        $this->assertMatchesRegularExpression('/id="record-ready".*id="record-download" class="btn btn-lg btn-block".*id="record-saved"/s', $html);
        $this->assertMatchesRegularExpression('/<p id="record-saved"[^>]*hidden>/', $html);
        $this->assertMatchesRegularExpression('/data-video-guard="[^"]*js\/video-guard\.js\?v=/', $html);

        $texts = Confirmation::for('video.leave');
        $this->assertSame('Quitter sans votre vidéo ?', $texts['title']);
        $this->assertSame('Quitter la page', $texts['confirm']);
        $this->assertStringContainsString('« Télécharger ma vidéo »', $texts['message']);
        $this->assertMatchesRegularExpression('/data-video-leave=\'\{[^\']*Quitter sans votre vid/u', $this->rawContent($html));
    }

    public function test_nothing_is_loaded_when_recording_is_off(): void
    {
        config(['platform.recording.enabled' => false]);

        $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()
            ->assertDontSee('data-video-guard', false)
            ->assertDontSee('Votre vidéo est prête');
    }

    public function test_the_browser_side_guard_is_wired_without_touching_the_end_of_the_session(): void
    {
        $session = file_get_contents(public_path('js/live-session.js'));
        $guard = file_get_contents(public_path('js/video-guard.js'));

        // Écouteur beforeunload seulement quand une vidéo est en jeu, jamais de texte personnalisé.
        $this->assertStringContainsString("addEventListener('beforeunload'", $guard);
        $this->assertStringContainsString("removeEventListener('beforeunload'", $guard);
        $this->assertDoesNotMatchRegularExpression('/returnValue = \'[^\']/', $guard);
        // La vidéo est protégée au début de l'enregistrement, à son arrivée, et libérée au téléchargement.
        $this->assertSame(5, substr_count($session, 'guardVideo();'));
        $this->assertStringContainsString('recording.downloaded = true;', $session);
        // La fermeture de la page prévient toujours le serveur (pagehide), sans attendre la vidéo.
        $this->assertStringContainsString("window.addEventListener('pagehide'", $session);
    }

    private function rawContent(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES);
    }
}
