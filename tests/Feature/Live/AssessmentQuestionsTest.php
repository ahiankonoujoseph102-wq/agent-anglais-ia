<?php

namespace Tests\Feature\Live;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\Assessment;
use App\Models\LessonBoard;
use App\Models\User;
use App\Support\Curriculum\Curriculum;
use App\Support\Curriculum\LessonBoards;
use App\Support\Gemini\TeacherSetup;
use App\Support\Live\OpenAiRealtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\IgnoresSignalSilence;
use Tests\Feature\Program\LessonBoardTest;
use Tests\TestCase;

/**
 * Fin de l'évaluation : après le verdict, mini-leçon découverte, questions libres puis conclusion,
 * au plus 3 minutes de plus (1 + 2), comptées à partir du verdict et coupées par le serveur.
 * Aucun appel réel à Gemini.
 */
class AssessmentQuestionsTest extends TestCase
{
    use IgnoresSignalSilence, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-de-test']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton'])]);
        $this->freezeSecond();
    }

    /**
     * @param  array<int, mixed>|null  $lesson  mini-leçon personnelle transmise avec le verdict
     */
    private function verdictAfter(User $user, int $seconds, ?array $lesson = null)
    {
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk()->assertJsonPath('questions', false);
        $this->travel($seconds)->seconds();

        return $this->actingAs($user)->postJson('/evaluation/resultat', ['level' => 'A2', 'feedback' => 'Bonne base.'] + ($lesson === null ? [] : ['lesson' => $lesson]));
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
            ->assertJsonPath('closing_phrase', 'On se retrouve de l\'autre côté !')
            // Niveau montré quand le professeur le dit : délai de sécurité et maximum (configuration).
            ->assertJsonPath('level_reveal_delay_seconds', 4)
            ->assertJsonPath('level_reveal_max_seconds', 12);

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
            ->assertJsonPath('cues.conclusion', '[System] About 30 seconds remain. Do not take any more questions. If you have not given your conclusion yet, give it now, in French, once, ending with « On se retrouve de l\'autre côté ! ». If you have already given it, say only this closing sentence again, in one short goodbye.');

        // Après le verdict : le jeton couvre la nouvelle limite et la petite marge (token_margin_seconds), rien de
        // plus ; les consignes gardent la durée de l'évaluation.
        $this->assertSame(160 + 60, $response->json('token_expires_in'));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/auth_tokens')
            && str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), 'FREE ORAL LEVEL ASSESSMENT: 5 minutes at most'));
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

    public function test_the_mini_lesson_is_built_on_the_real_mistakes_of_this_learner(): void
    {
        // Exemples INVENTÉS : deux apprenants du même niveau reçoivent deux leçons différentes.
        $kofi = $this->verdictAfter(User::factory()->create(), 150, [
            ['said' => 'I have 30 years', 'correct' => "I'm 30 years old.", 'why' => 'En anglais, l\'âge se dit avec « be », pas avec « have ».'],
            ['said' => 'Yesterday I go to the market', 'correct' => 'Yesterday I went to the market.', 'why' => 'Hier, c\'est le passé : « go » devient « went ».'],
            ['said' => 'troisième', 'correct' => 'ignorée', 'why' => 'Deux points au plus.'],
        ])->assertOk();

        $this->assertSame('Votre leçon personnelle', $kofi->json('discovery.title'));
        $this->assertSame('Vos deux points du jour', $kofi->json('discovery.heading'));
        $this->assertCount(2, $kofi->json('discovery.phrases'));
        $this->assertSame("I'm 30 years old.", $kofi->json('discovery.phrases.0.en'));
        $this->assertSame('Vous avez dit « I have 30 years ». En anglais, l\'âge se dit avec « be », pas avec « have ».', $kofi->json('discovery.phrases.0.fr'));

        $next = $kofi->json('next_step');
        $this->assertStringContainsString('PERSONAL MINI-LESSON', $next);
        $this->assertStringContainsString('(1) they said "I have 30 years", right form "I\'m 30 years old."', $next);
        $this->assertStringContainsString('(2) they said "Yesterday I go to the market"', $next);
        // Une seule action par réplique : il attend la répétition avant « Your turn », puis la réponse avant les questions.
        $this->assertStringContainsString('ONE ACTION PER TURN, and WAIT for the learner after each one', $next);
        $this->assertStringContainsString('"Repeat after me: …" with the right form, and STOP talking. TURN 2 (after they repeated)', $next);
        $this->assertStringContainsString('TURN 3 (after their answer)', $next);
        $this->assertStringContainsString('ONE sincere encouragement about something they really did well today', $next);
        $this->assertLessThan(strpos($next, 'Avez-vous des questions'), strpos($next, 'TURN 3'));
        $this->assertStringNotContainsString('troisième', $next);
        $this->assertStringNotContainsString(':items', $next);

        // Presque aucune erreur : une tournure plus naturelle du niveau supérieur.
        $ama = $this->verdictAfter(User::factory()->create(), 150, [
            ['said' => '', 'correct' => "I'm really looking forward to it.", 'why' => 'Plus naturel que « I wait for it with impatience ».'],
        ])->assertOk();
        $this->assertSame('Votre point du jour', $ama->json('discovery.heading'));
        $this->assertSame('Plus naturel que « I wait for it with impatience ».', $ama->json('discovery.phrases.0.fr'));
        $this->assertStringContainsString('(1) a more natural phrase: "I\'m really looking forward to it."', $ama->json('next_step'));
        $this->assertNotSame($kofi->json('discovery'), $ama->json('discovery'));
    }

    public function test_a_broken_lesson_never_blocks_the_verdict_and_falls_back_to_the_first_lesson(): void
    {
        $user = User::factory()->create();

        $response = $this->verdictAfter($user, 150, [['said' => 'x'], 'texte', ['correct' => '<b>Hi</b>', 'why' => '']])->assertOk();

        $this->assertSame('A2', $user->assessment()->first()->level->value);
        $this->assertSame('Votre première phrase', $response->json('discovery.heading'));
        $this->assertStringContainsString('on this phrase instead (nothing else was sent)', $response->json('next_step'));
    }

    public function test_the_verdict_function_asks_for_the_learner_s_own_mistakes_at_both_providers(): void
    {
        $setup = TeacherSetup::assessment(User::factory()->create(), 5);
        $lesson = $setup['tools'][0]['functionDeclarations'][0]['parameters']['properties']['lesson'];

        $this->assertSame('ARRAY', $lesson['type']);
        $this->assertSame(['said', 'correct', 'why'], array_keys($lesson['items']['properties']));
        $this->assertStringContainsString('real mistakes of this learner', $lesson['description']);
        $this->assertSame(['level', 'feedback'], $setup['tools'][0]['functionDeclarations'][0]['parameters']['required']);

        $openai = OpenAiRealtime::session($setup);
        $this->assertSame('array', $openai['tools'][0]['parameters']['properties']['lesson']['type']);
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

    public function test_the_level_is_saved_at_once_but_shown_when_the_teacher_says_it(): void
    {
        $user = User::factory()->create();

        // Enregistré en base dès la réponse : rien n'attend l'annonce.
        $this->verdictAfter($user, 100)->assertOk();
        $this->assertSame('A2', $user->assessment()->first()->level->value);
        $this->assertSame('A2', $user->fresh()->level->value);

        $session = file_get_contents(public_path('js/live-session.js'));
        $call = substr($session, strpos($session, "call.name === 'record_assessment_result'"), 1400);
        // L'écran et la vidéo ne sont montrés que par le report (même appel showAssessmentResult).
        $this->assertStringContainsString('scheduleLevelReveal(result.data.level, () => {', $call);
        $this->assertLessThan(strpos($call, 'showAssessmentResult('), strpos($call, 'scheduleLevelReveal('));
        $show = substr($session, strpos($session, 'function showAssessmentResult('), 600);
        $this->assertStringContainsString('recording.recorder?.revealLevel(level, label);', $show);
        // Le minuteur garde « Temps restant » pendant l'attente (rien ne dit que le verdict est tombé).
        $this->assertStringContainsString("state.end.questions && !state.pendingReveal ? 'Vos questions' : 'Temps restant'", $session);
        // Les sous-titres de l'annonce attendent aussi le niveau.
        $this->assertStringContainsString('state.pendingReveal.held.push(text);', $session);
        // Fin de la conversation pendant l'attente : montré avant l'arrêt de la vidéo.
        $finish = substr($session, strpos($session, 'async function finish('));
        $this->assertLessThan(strpos($finish, 'stopRecording();'), strpos($finish, 'revealLevelNow();'));
    }
}
