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
        $this->assertStringContainsString('La vidéo montre Teacher Joe, le tableau, et on y entend vos deux voix. Elle reste sur votre téléphone : elle n\'est jamais envoyée à Talkami. Elle s\'arrête avec la séance : téléchargez-la avant de quitter la page.', $html);
        $this->assertMatchesRegularExpression('/<p id="record-unsupported"[^>]*hidden>Cet appareil ne permet pas d\'enregistrer la séance en vidéo\. Votre séance fonctionne normalement\.<\/p>/', $html);
        $this->assertMatchesRegularExpression('/data-recorder="[^"]*js\/session-recorder\.js\?v=/', $html);

        $config = $this->recordingConfig($html);
        $this->assertSame(['width' => 540, 'height' => 960, 'fps' => 24], array_intersect_key($config, array_flip(['width', 'height', 'fps'])));
        $this->assertSame(9 / 16, $config['width'] / $config['height']);
        $this->assertSame('Talkami', $config['platform']);
        $this->assertSame('talkami-evaluation', $config['file_prefix']);
        // Écran partagé : le tableau en haut (45 % de la hauteur), Teacher Joe en dessous, transition douce.
        $this->assertSame(0.45, $config['board_share']);
        $this->assertSame(700, $config['transition_ms']);
        $this->assertSame(['max' => 34, 'min' => 24, 'window' => 28], $config['board_sizes']);
        $this->assertSame(14, $config['frame_budget_ms']);
        foreach (['camera_full', 'camera_split'] as $camera) {
            $this->assertCount(3, $config[$camera]['target']);
            $this->assertGreaterThan(0, $config[$camera]['half_height']);
            $this->assertGreaterThan(0, $config[$camera]['half_width']);
        }
        // Tête et épaules en écran partagé : cadrage plus serré qu'en plein cadre.
        $this->assertLessThan($config['camera_full']['half_height'], $config['camera_split']['half_height']);
    }

    public function test_the_daily_session_offers_it_too(): void
    {
        $html = $this->actingAs($this->lessonLearner())->get('/seance')->assertOk()->getContent();

        $this->assertStringContainsString('id="record-start"', $html);
        $config = $this->recordingConfig($html);
        $this->assertSame('talkami-seance', $config['file_prefix']);
        // Le tableau de la séance est dans la page (replié sur téléphone) : la vidéo le dessine à partir de lui.
        $this->assertMatchesRegularExpression('/<details id="board" class="board"/', $html);
        $this->assertStringContainsString('data-board-item="p0"', $html);
    }

    public function test_the_site_address_and_names_come_from_the_configuration(): void
    {
        config(['app.url' => 'https://talkami.net/', 'platform.name' => 'Nom Essai', 'avatar.model.name' => 'Professeur Essai']);

        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();
        $config = $this->recordingConfig($html);

        $this->assertSame('talkami.net', $config['mention']);
        $this->assertSame('Nom Essai', $config['platform']);
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
        // Image 3D de la vidéo rendue juste après celle de l'écran, par sa propre caméra.
        $teacher = file_get_contents(public_path('js/teacher-3d.js'));
        $this->assertStringContainsString('this.onrender?.(this);', $teacher);
        $this->assertStringContainsString('renderVideo({ width, height, camera: framing })', $teacher);
        $this->assertStringContainsString('this.renderer.setRenderTarget(null);', $teacher);
        // La bibliothèque 3D regroupée fournit l'image hors écran.
        $this->assertStringContainsString('as WebGLRenderTarget', file_get_contents(public_path('teacher3d/lib/three-teacher.min.js')));
        // Le tableau de la mini-leçon de l'évaluation est donné à la vidéo dès qu'il apparaît.
        $this->assertStringContainsString('recording.recorder?.setBoard(boardEl);', $session);
    }

    public function test_the_video_starts_with_the_first_3d_image_and_never_with_the_simple_face_before_it(): void
    {
        $session = file_get_contents(public_path('js/live-session.js'));
        $recorder = file_get_contents(public_path('js/session-recorder.js'));

        // 3D en chargement : la vidéo attend sa première vraie image (couverture) ; repli sur le visage simple
        // seulement quand la 3D est indisponible (data-teacher="svg"), ou après le délai de la configuration.
        $this->assertStringContainsString("waitForTeacher: root.dataset.teacher !== 'svg'", $session);
        $this->assertStringContainsString('recording.recorder?.useFace();', $session);
        $this->assertStringContainsString('if (!hasPicture(frame))', $recorder);
        $this->assertSame(35000, config('platform.recording.teacher_wait_ms'));
        $config = $this->recordingConfig($this->actingAs(User::factory()->create())->get('/evaluation')->getContent());
        $this->assertSame(35000, $config['teacher_wait_ms']);
    }

    public function test_the_screen_shows_a_clean_loading_place_instead_of_the_simple_face_while_the_3d_loads(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();
        $this->assertStringContainsString('data-teacher="loading"', $html);
        $css = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('.js [data-teacher="loading"] .face { display: none; }', $css);

        // Sans professeur 3D : le visage simple, comme avant.
        config(['avatar.enabled' => false]);
        $this->assertStringContainsString('data-teacher="svg"', $this->actingAs(User::factory()->create())->get('/evaluation')->getContent());
    }

    public function test_a_started_assessment_keeps_the_button_and_it_sits_under_start_before_the_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/evaluation')->assertOk();
        $user->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 0, 'started_at' => now()]);

        // Évaluation commencée puis reprise : toujours la page de la séance, avec le bouton.
        $this->assertStringContainsString('id="record-start"', $this->actingAs($user)->get('/evaluation')->assertOk()->getContent());
        // Avant « Commencer », le bouton est placé juste sous lui et sa ligne (visible sans faire défiler).
        $this->assertStringContainsString("root.dataset.phase === 'intro' ? ($('start-block') ?? $('start')) : null", file_get_contents(public_path('js/live-session.js')));
    }

    public function test_the_assessment_video_board_never_reveals_a_level_before_the_verdict(): void
    {
        $board = $this->recordingConfig($this->actingAs(User::factory()->create())->get('/evaluation')->getContent())['assessment'];

        $this->assertSame("Évaluation gratuite d'anglais", $board['title']);
        // Nom du professeur et durée tirés de la configuration.
        $this->assertSame('Parlez anglais avec '.config('avatar.model.name').'. Découvrez votre niveau en '.config('platform.assessment.max_minutes').' minutes.', $board['text']);
        $this->assertSame(4000, $board['reveal_ms']);

        // Aucun niveau (lettre ou libellé) dans les textes donnés au navigateur pour la vidéo.
        $text = implode(' ', array_filter($board, 'is_string'));
        foreach (Level::cases() as $level) {
            $this->assertDoesNotMatchRegularExpression('/\\b'.$level->value.'\\b/', $text);
            $this->assertStringNotContainsString($level->label(), $text);
        }

        // Le niveau n'entre dans la vidéo que par le résultat affiché à l'écran.
        $session = file_get_contents(public_path('js/live-session.js'));
        $show = substr($session, strpos($session, 'function showAssessmentResult('), 400);
        $this->assertStringContainsString('recording.recorder?.revealLevel(level, label);', $show);
        $this->assertSame(2, substr_count($session, '.revealLevel('));
        $this->assertSame(1, substr_count($session, 'revealLevel(state.revealed.level'));

        // Séance du jour : pas de tableau neutre.
        $this->assertNull($this->recordingConfig($this->actingAs($this->lessonLearner())->get('/seance')->getContent())['assessment']);
    }

    public function test_the_screen_camera_and_the_board_on_screen_do_not_change(): void
    {
        // La caméra de l'écran de la séance reste celle de config/avatar.php ; celle de la vidéo est à part.
        $this->assertSame([0, 0.57, 0], config('avatar.model.camera.target'));
        $html = $this->actingAs($this->lessonLearner())->get('/seance')->assertOk()->getContent();
        // Sur téléphone, le tableau reste replié à l'écran (aucun attribut open dans la page).
        $this->assertDoesNotMatchRegularExpression('/<details id="board"[^>]*\bopen\b/', $html);
    }
}
