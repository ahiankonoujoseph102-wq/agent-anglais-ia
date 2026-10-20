<?php

namespace Tests\Feature\Live;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\Assessment;
use App\Models\LessonBoard;
use App\Models\User;
use App\Support\Curriculum\Curriculum;
use App\Support\Curriculum\LessonBoards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Program\LessonBoardTest;
use Tests\TestCase;

/**
 * Fin de l'évaluation : après le verdict, mini-leçon découverte, questions libres puis conclusion,
 * au plus 3 minutes de plus (1 + 2), comptées à partir du verdict et coupées par le serveur.
 * Aucun appel réel à Gemini.
 */
class AssessmentQuestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-de-test']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton'])]);
        $this->freezeSecond();
    }

    private function verdictAfter(User $user, int $seconds)
    {
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk()->assertJsonPath('questions', false);
        $this->travel($seconds)->seconds();

        return $this->actingAs($user)->postJson('/evaluation/resultat', ['level' => 'A2', 'feedback' => 'Bonne base.']);
    }

    public function test_the_verdict_opens_three_minutes_of_mini_lesson_and_questions_counted_from_the_verdict(): void
    {
        $user = User::factory()->create();

        $this->verdictAfter($user, 100)
            ->assertOk()
            ->assertJsonPath('level', 'A2')
            ->assertJsonPath('questions', true)
            ->assertJsonPath('remaining_seconds', 180)
            ->assertJsonPath('conclusion_cue_seconds', 30)
            ->assertJsonPath('closing_phrase', 'On se retrouve de l\'autre côté !');

        // Verdict tôt : la durée totale est plus courte (100 + 180 secondes).
        $assessment = $user->assessment()->first();
        $this->assertSame(280, $assessment->allowed_seconds);
        $this->assertTrue($assessment->inQuestions());

        $this->travel(170)->seconds();
        $this->actingAs($user)->postJson('/evaluation/signal')->assertJsonPath('active', true)->assertJsonPath('remaining_seconds', 10);

        // Le serveur coupe à la nouvelle limite.
        $this->travel(15)->seconds();
        $this->actingAs($user)->postJson('/evaluation/signal')->assertJsonPath('active', false);

        $assessment->refresh();
        $this->assertSame(AssessmentStatus::Completed, $assessment->status);
        $this->assertSame(280, $assessment->used_seconds);
    }

    public function test_total_duration_never_exceeds_eight_minutes(): void
    {
        $user = User::factory()->create();

        // Verdict à la dernière seconde des 5 minutes : 299 + 180 = 479 secondes au plus.
        $this->verdictAfter($user, 299)->assertJsonPath('questions', true)->assertJsonPath('remaining_seconds', 180);

        $assessment = $user->assessment()->first();
        $this->assertSame(479, $assessment->allowed_seconds);
        $this->assertLessThanOrEqual(8 * 60, $assessment->allowed_seconds);
        $this->assertSame(8, Assessment::totalMinutes());

        $this->travel(10)->minutes();
        $this->artisan('live:close-stale')->assertSuccessful();
        $this->assertSame(479, $assessment->fresh()->used_seconds);

        // La page « Consommation » compte ces minutes.
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/consommation')
            ->assertOk()
            ->assertSee('7 minutes 59 secondes / 7 minutes 59 secondes');
    }

    public function test_one_verdict_only_and_no_extension_twice(): void
    {
        $user = User::factory()->create();
        $this->verdictAfter($user, 120)->assertOk();

        $this->travel(30)->seconds();
        $this->actingAs($user)->postJson('/evaluation/resultat', ['level' => 'B1', 'feedback' => 'Autre.'])->assertStatus(409);

        $assessment = $user->assessment()->first();
        $this->assertSame('A2', $assessment->level->value);
        $this->assertSame(300, $assessment->allowed_seconds, 'le deuxième appel ne repousse pas la fin');
    }

    public function test_reconnection_during_the_questions_keeps_the_questions_and_the_new_limit(): void
    {
        $user = User::factory()->create();
        $this->verdictAfter($user, 200)->assertOk();
        $this->travel(20)->seconds();

        $response = $this->actingAs($user)->postJson('/evaluation/connexion', ['handle' => 'reprise'])
            ->assertOk()
            ->assertJsonPath('questions', true)
            ->assertJsonPath('remaining_seconds', 160)
            ->assertJsonPath('cues.conclusion', '[System] About 30 seconds remain. Do not take any more questions. If you have not given your conclusion yet, give it now, in French, once, ending with « On se retrouve de l\'autre côté ! ». If you have already said this sentence, say nothing.');

        // Le jeton ne dure jamais au-delà de la nouvelle limite ; les consignes gardent la durée de l'évaluation.
        $this->assertLessThanOrEqual(160, $response->json('token_expires_in'));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/auth_tokens')
            && str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), 'The assessment itself lasts 5 minutes at most'));
    }

    public function test_a_verdict_after_the_server_cut_opens_no_questions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk();
        $this->travel(301)->seconds();
        $this->actingAs($user)->postJson('/evaluation/signal')->assertJsonPath('active', false);

        $this->actingAs($user)->postJson('/evaluation/resultat', ['level' => 'A2', 'feedback' => 'Bonne base.'])
            ->assertOk()
            ->assertJsonPath('questions', false)
            ->assertJsonPath('remaining_seconds', 0);

        $this->assertSame(300, $user->assessment()->first()->allowed_seconds);
    }

    public function test_the_timer_label_can_show_the_questions_moment(): void
    {
        $this->actingAs(User::factory()->create())->get('/evaluation')
            ->assertOk()
            ->assertSee('<span id="timer-label">Temps restant</span>', false);
    }

    public function test_the_verdict_brings_a_one_phrase_discovery_mini_lesson_from_the_first_lesson_of_the_level(): void
    {
        $user = User::factory()->create();

        $response = $this->verdictAfter($user, 150)->assertOk()->assertJsonPath('questions', true);

        // Tableau de secours de l'unité 1 du niveau trouvé (A2) : 3 phrases au plus, en anglais du programme.
        $phrases = $response->json('discovery.phrases');
        $this->assertNotEmpty($phrases);
        $this->assertLessThanOrEqual(3, count($phrases));
        $this->assertSame(Curriculum::unit(Level::A2, 1)->title, $response->json('discovery.title'));
        $this->assertStringContainsString($phrases[0]['en'], implode(' ', Curriculum::unit(Level::A2, 1)->functions));
        $this->assertSame(min(array_map(fn ($phrase) => mb_strlen($phrase['en']), $phrases)), mb_strlen($phrases[0]['en']), 'la plus courte est enseignée');

        // La suite donnée au professeur : la phrase fixe d'ouverture, la phrase à enseigner, puis questions et conclusion.
        $next = $response->json('next_step');
        $this->assertStringContainsString('« Laissez-moi vous montrer comment nous allons travailler ensemble. »', $next);
        $this->assertStringContainsString('"'.$phrases[0]['en'].'"', $next);
        $this->assertStringContainsString('Repeat after me: '.$phrases[0]['en'], $next);
        $this->assertStringContainsString('never teach another phrase, and never call this function again', $next);
        $this->assertStringNotContainsString(':phrase', $next);
    }

    public function test_the_mini_lesson_uses_the_shared_board_of_the_first_lesson_when_it_is_ready(): void
    {
        LessonBoard::query()->create([
            'key' => LessonBoards::VERSION.':A2-01-L1-S0-work',
            'level' => 'A2', 'unit_number' => 1, 'lesson_number' => 1, 'situation_index' => 0, 'goal' => 'work',
            'content' => LessonBoardTest::board(), 'provider' => 'gemini',
        ]);
        $user = User::factory()->create();

        $response = $this->verdictAfter($user, 150)->assertOk();

        // La phrase la plus courte des trois premières est enseignée (« Her name is Ama. »).
        $first = LessonBoardTest::board()['phrases'][1];
        $this->assertSame($first['en'], $response->json('discovery.phrases.0.en'));
        $this->assertSame($first['fr'], $response->json('discovery.phrases.0.fr'));
        $this->assertStringContainsString('(in French: "'.$first['fr'].'")', $response->json('next_step'));
    }

    public function test_no_mini_lesson_when_the_verdict_comes_after_the_server_cut(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk();
        $this->travel(301)->seconds();
        $this->actingAs($user)->postJson('/evaluation/signal');

        $this->actingAs($user)->postJson('/evaluation/resultat', ['level' => 'A2', 'feedback' => 'Bonne base.'])
            ->assertOk()
            ->assertJsonMissingPath('discovery')
            ->assertJsonMissingPath('next_step');
    }
}
