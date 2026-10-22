<?php

namespace Tests\Feature\Live;

use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * « Enregistrer ma séance » : bouton sur l'évaluation et la séance du jour, réglages donnés au navigateur
 * (config/platform.php → recording), vidéo fabriquée et gardée sur le téléphone (public/js/session-recorder.js).
 */
class SessionRecordingTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response([], 500)]);
    }

    /** @return array<string, mixed> */
    private function recordingConfig(string $html): array
    {
        $this->assertSame(1, preg_match("/data-recording='([^']*)'/", $html, $match));

        return json_decode(html_entity_decode($match[1]), true);
    }

    private function lessonLearner(): User
    {
        $user = User::factory()->level(Level::A1)->create(['name' => 'Afi', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->create();
        $this->buildProgram($user, Level::A1);

        return $user->fresh();
    }

    public function test_the_free_assessment_offers_to_record_the_session_on_the_phone(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();

        // Caché tant que le navigateur n'a pas dit qu'il sait enregistrer (et sans JavaScript).
        $this->assertMatchesRegularExpression('/<section id="recorder" class="recorder"[^>]*hidden>/', $html);
        $this->assertStringContainsString('Enregistrer ma séance', $html);
        $this->assertStringContainsString('Arrêter l\'enregistrement', $html);
        $this->assertStringContainsString('Télécharger ma vidéo', $html);
        $this->assertStringContainsString('Enregistrement en cours', $html);
        $this->assertStringContainsString('La vidéo reste sur votre téléphone : elle n\'est jamais envoyée à Talkami.', $html);
        $this->assertMatchesRegularExpression('/<p id="record-unsupported"[^>]*hidden>L\'enregistrement vidéo n\'est pas possible sur ce navigateur\. Votre séance fonctionne normalement\.<\/p>/', $html);
        $this->assertMatchesRegularExpression('/data-recorder="[^"]*js\/session-recorder\.js\?v=/', $html);

        $config = $this->recordingConfig($html);
        $this->assertSame(['width' => 540, 'height' => 960, 'fps' => 24], array_intersect_key($config, array_flip(['width', 'height', 'fps'])));
        $this->assertSame(9 / 16, $config['width'] / $config['height']);
        $this->assertSame('Talkami', $config['platform']);
        $this->assertSame('Teacher Joe', $config['teacher']);
        $this->assertSame('Évaluation gratuite', $config['title']);
        $this->assertSame('talkami-evaluation', $config['file_prefix']);
    }

    public function test_the_daily_session_offers_it_too(): void
    {
        $html = $this->actingAs($this->lessonLearner())->get('/seance')->assertOk()->getContent();

        $this->assertStringContainsString('id="record-start"', $html);
        $config = $this->recordingConfig($html);
        $this->assertSame('Séance du jour', $config['title']);
        $this->assertSame('talkami-seance', $config['file_prefix']);
    }

    public function test_the_site_address_and_names_come_from_the_configuration(): void
    {
        config(['app.url' => 'https://talkami.net/', 'platform.name' => 'Nom Essai', 'avatar.model.name' => 'Professeur Essai']);

        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();
        $config = $this->recordingConfig($html);

        $this->assertSame('talkami.net', $config['mention']);
        $this->assertSame('Nom Essai', $config['platform']);
        $this->assertSame('Professeur Essai', $config['teacher']);
        $this->assertSame('nom-essai-evaluation', $config['file_prefix']);
        $this->assertStringContainsString('elle n\'est jamais envoyée à Nom Essai.', $html);
    }

    public function test_the_default_mention_is_the_host_of_the_site_address(): void
    {
        config(['app.url' => 'https://talkami.laravel.cloud']);

        $config = $this->recordingConfig($this->actingAs(User::factory()->create())->get('/evaluation')->getContent());

        $this->assertSame('talkami.laravel.cloud', $config['mention']);
    }

    public function test_the_button_can_be_switched_off_or_limited_to_some_conversations(): void
    {
        config(['platform.recording.modes' => ['lesson']]);
        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();
        $this->assertStringNotContainsString('id="recorder"', $html);
        $this->assertStringNotContainsString('data-recording=', $html);

        config(['platform.recording.enabled' => false, 'platform.recording.modes' => ['assessment', 'lesson']]);
        $this->assertStringNotContainsString('id="recorder"', $this->actingAs($this->lessonLearner())->get('/seance')->getContent());
    }

    public function test_recording_stops_with_the_session_and_never_leaves_the_phone(): void
    {
        $session = file_get_contents(public_path('js/live-session.js'));
        $recorder = file_get_contents(public_path('js/session-recorder.js'));

        // Arrêtée dans finish(), avant la fermeture du contexte audio : jamais plus longue que la séance.
        $finish = substr($session, strpos($session, 'async function finish('));
        $this->assertLessThan(strpos($finish, 'context.close()'), strpos($finish, 'stopRecording();'));
        // Le micro de la vidéo suit la porte du micro de la séance (pas d'écho du haut-parleur).
        $this->assertStringContainsString('recording.recorder?.learnerOpen(open);', $session);
        // Rien n'est envoyé : ni requête ni lecture sur le haut-parleur.
        $this->assertDoesNotMatchRegularExpression('/fetch\(|XMLHttpRequest|sendBeacon|context\.destination/', $recorder);
        $this->assertStringContainsString("'video/mp4", $recorder);
        $this->assertStringContainsString("'video/webm'", $recorder);
        // Image 3D lue juste après son rendu.
        $this->assertStringContainsString('this.onrender?.(this.renderer.domElement);', file_get_contents(public_path('js/teacher-3d.js')));
    }
}
