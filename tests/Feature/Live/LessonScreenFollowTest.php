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
 * Retours du fondateur (2 novembre 2026, vidéo d'une séance d'essai) : le tableau suit le professeur sans que l'élève
 * défile, un problème de connexion s'affiche SUR le professeur, le site relance puis reconnecte tout seul et arrête
 * proprement la séance (« Reprendre » dans deux minutes au plus), aucun mot technique ni « Envoi en cours… ».
 */
class LessonScreenFollowTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    private function learner(): User
    {
        Http::fake(['*/interactions' => Http::response([], 500)]);
        $user = User::factory()->level(Level::A1)->create(['name' => 'Afi', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A1);

        return $user->fresh();
    }

    public function test_the_board_says_to_follow_it_and_follows_the_teacher(): void
    {
        $html = $this->actingAs($this->learner())->get('/seance')->assertOk()->getContent();

        $this->assertStringContainsString('class="board-follow"', $html);
        $this->assertStringContainsString("Suivez au tableau : ce que dit Teacher Joe s'allume en orange.", $html);

        $session = file_get_contents(public_path('js/live-session.js'));
        // La séance commence : le tableau s'ouvre aussi sur téléphone, une fois.
        $this->assertStringContainsString('function openBoardOnce()', $session);
        $this->assertMatchesRegularExpression("/setPhase\('live'\);\s*ui\.intro\.hidden = true;\s*openBoardOnce\(\);/", $session);
        // L'élément dont parle le professeur vient au milieu du tableau (le tableau défile, jamais la page).
        $board = file_get_contents(public_path('js/lesson-board.js'));
        $this->assertStringContainsString('this.follow(el);', $board);
        $this->assertStringContainsString("el.closest?.('.board-body')", $board);

        $css = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('.board-item.is-lit::before', $css);
        $this->assertStringContainsString('.live[data-phase="live"] #board-home .board-body', $css);
    }

    public function test_a_connection_problem_shows_on_the_teacher_with_a_resume_button(): void
    {
        $html = $this->actingAs($this->learner())->get('/seance')->assertOk()->getContent();

        // Le bandeau est dans la colonne du professeur, juste après lui.
        $stage = strpos($html, 'class="live-stage"');
        $alert = strpos($html, 'id="stage-alert"');
        $panel = strpos($html, 'class="live-panel"');
        $this->assertTrue($stage < $alert && $alert < $panel);
        $this->assertStringContainsString('id="stage-alert-resume"', $html);

        $messages = config('platform.live.messages');
        $this->assertStringContainsString('Reprenez-la dans deux minutes au plus', $messages['teacher_down']);
        $this->assertStringContainsString('vous ne perdez pas de minutes', $messages['teacher_down']);
        $this->assertStringContainsString('Revenez dans deux minutes au plus', $messages['unavailable']);
        // Jamais de mot technique pour l'élève.
        foreach (['teacher_down', 'stuck_auto', 'unavailable'] as $key) {
            $this->assertDoesNotMatchRegularExpression('/fournisseur|quota|limite|serveur|google|gemini|openai/i', $messages[$key]);
        }

        $session = file_get_contents(public_path('js/live-session.js'));
        // Aucune réponse : relance automatique, puis reconnexion, puis arrêt propre avec « Reprendre la séance ».
        $this->assertStringContainsString('function autoNudge()', $session);
        $this->assertStringContainsString('function forceReconnect()', $session);
        $this->assertStringContainsString('stageAlert(message, true);', $session);
        $this->assertStringContainsString("ui.stageAlertResume?.addEventListener('click', resumeSession);", $session);
        $this->assertStringNotContainsString("Beaucoup d'élèves", $session);
        $this->assertSame(15, config('platform.live.stuck_reconnect_seconds'));
    }

    public function test_waiting_buttons_never_say_sending(): void
    {
        foreach (['js/app.js', 'js/long-submit.js'] as $file) {
            $this->assertStringNotContainsString("'Envoi en cours", file_get_contents(public_path($file)));
        }

        // Fin du questionnaire : ce qui se passe vraiment, jamais « Envoi en cours… ».
        $view = file_get_contents(resource_path('views/program/questionnaire.blade.php'));
        $this->assertStringContainsString("'Construction de votre programme…'", $view);
    }

    public function test_the_review_form_explains_the_stars_step_by_step(): void
    {
        $user = $this->learner();
        $user->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 300, 'level' => 'A1', 'status' => 'completed']);

        $html = $this->actingAs($user)->get('/mon-avis?note=4')->assertOk()->getContent();
        $this->assertStringContainsString('Touchez les étoiles pour donner votre note', $html);
        $this->assertStringContainsString('1 étoile : pas content · 5 étoiles : très content', $html);
        $this->assertStringContainsString('Votre note : 4 sur 5 (content)', $html);
        $this->assertStringContainsString('Écrivez votre avis en quelques mots', $html);
        $this->assertStringContainsString('Cochez la case, puis touchez le bouton orange', $html);

        // « Mon espace » : l'invitation est juste sous la prochaine étape et le parcours.
        $dashboard = file_get_contents(resource_path('views/dashboard.blade.php'));
        $this->assertLessThan(strpos($dashboard, '<x-club-invite'), strpos($dashboard, '<x-review-invite'));
    }
}
