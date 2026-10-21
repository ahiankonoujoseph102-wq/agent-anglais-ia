<?php

namespace Tests\Feature\Live;

use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsPrograms;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

/**
 * Fin d'une conversation avec Teacher Joe : la carte « La suite » (UN bouton orange) apparaît en haut de la colonne
 * des commandes, AVANT le résumé, et la page y descend. Séance du jour : annonce des temps, sans bouton.
 */
class LiveNextStepTest extends TestCase
{
    use BuildsPrograms, HasCourses, RefreshDatabase {
        HasCourses::learner insteadof BuildsPrograms;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-18 10:00:00', 'Africa/Lome'));
    }

    /** La carte « La suite » cachée, avant le résumé et les sous-titres. */
    private function nextCard(string $html): string
    {
        $this->assertSame(1, preg_match('/<section[^>]*id="suite".*?<\/section>/s', $html, $match), 'Carte « La suite » absente.');
        $this->assertMatchesRegularExpression('/<section[^>]*\bhidden\b[^>]*id="suite"|<section[^>]*id="suite"[^>]*\bhidden\b/', $html);
        $this->assertLessThan(strpos($html, 'id="outcome"'), strpos($html, 'id="suite"'), 'La suite avant le résumé');
        $this->assertLessThan(strpos($html, 'id="subtitles-home"'), strpos($html, 'id="suite"'), 'La suite avant la transcription');
        $this->assertLessThan(strpos($html, 'id="board-wide"'), strpos($html, 'id="suite"'), 'La suite avant le tableau');
        $this->assertSame(1, substr_count($match[0], 'btn-next'));

        return $match[0];
    }

    public function test_the_end_of_the_assessment_leads_to_choosing_a_plan(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();
        $card = $this->nextCard($html);

        $this->assertStringContainsString('Évaluation terminée', $card);
        $this->assertStringContainsString('href="'.route('payment.plans').'"', $card);
        $this->assertStringContainsString('Choisir ma formule', $card);
        $this->assertStringContainsString('Voir mon résultat complet', $card);
        $this->assertStringNotContainsString('id="phase-note"', $html);
    }

    public function test_the_end_of_the_daily_lesson_leads_to_the_next_lesson_and_phases_are_announced(): void
    {
        $user = User::factory()->level(Level::A1)->create(['welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A1);

        $html = $this->actingAs($user)->get('/seance')->assertOk()->getContent();
        $card = $this->nextCard($html);

        $this->assertStringContainsString('Bravo, séance terminée !', $card);
        $this->assertStringContainsString('href="'.route('program.show').'#suite"', $card);
        $this->assertStringContainsString('Voir ma prochaine leçon', $card);
        $this->assertStringContainsString('Retour à mon espace', $card);

        // Annonce des temps : sous le professeur, annoncée aux lecteurs d'écran, sans bouton.
        $this->assertMatchesRegularExpression('/<p id="phase-note" class="phase-note" role="status" hidden>/', $html);
        $this->assertLessThan(strpos($html, 'id="board-home"'), strpos($html, 'id="phase-note"'));
        $this->assertDoesNotMatchRegularExpression('/<p id="phase-note".*?<button/s', substr($html, strpos($html, 'id="phase-note"'), 300));
    }

    public function test_the_end_of_a_course_session_leads_to_the_revision_document(): void
    {
        Storage::fake('local');
        $user = $this->learner();
        $lesson = $this->storedLesson($user);

        $card = $this->nextCard($this->actingAs($user)->get(route('courses.session', $lesson))->assertOk()->getContent());

        $this->assertStringContainsString('href="'.route('courses.show', $lesson).'#documents"', $card);
        $this->assertStringContainsString('Voir mon document de révision', $card);
        $this->assertStringContainsString('Votre document de révision se prépare', $card);
    }

    public function test_the_script_shows_the_next_step_only_once_the_conversation_is_over(): void
    {
        $script = file_get_contents(public_path('js/live-session.js'));

        // Défini une fois, appelé une fois : à la fin de finish(), seulement si une conversation a eu lieu.
        $this->assertSame(2, substr_count($script, 'showNext('));
        $this->assertMatchesRegularExpression('/if \(state\.config && state\.everReady\) \{\s+showNext\(state\.next \?\? \{\}\);/', $script);
        $this->assertStringContainsString('window.goTo ? window.goTo(next)', $script);
        // Résultat adapté : niveau trouvé, bilan réussi ou à repasser.
        $this->assertStringContainsString('title: `Votre niveau : ${level}`', $script);
        $this->assertStringContainsString('Commencer mon programme ${result.data.next_level}', $script);
        $this->assertStringContainsString('Revoir les unités à reprendre', $script);
        // Annonce des temps de la séance : seulement quand le tableau change d'étape.
        $this->assertStringContainsString('if (board.view.setPhase(phase, state.config.phases?.merged)) {', $script);
    }
}
