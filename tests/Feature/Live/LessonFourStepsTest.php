<?php

namespace Tests\Feature\Live;

use App\Enums\Level;
use App\Models\LessonBoard;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Curriculum\LessonBoards;
use App\Support\Curriculum\LessonPlan;
use App\Support\Gemini\TeacherSetup;
use App\Support\Live\OpenAiRealtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\Feature\Program\LessonBoardTest;
use Tests\TestCase;

/**
 * Séance du jour en quatre temps (leçon, pratique guidée, conversation, bilan), dosés selon le niveau, avec le
 * tableau dans les consignes et l'anglais d'abord. Mêmes consignes chez Gemini et chez OpenAI. Aucun appel réel.
 */
class LessonFourStepsTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton']), '*/interactions' => Http::response([], 500)]);
        $this->freezeSecond();
    }

    private function learner(Level $level, string $plan = 'essentiel'): User
    {
        $user = User::factory()->level($level)->create(['name' => 'Afi', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan($plan)->create();
        $this->buildProgram($user, $level);

        return $user->fresh();
    }

    private function systemText(User $user): string
    {
        $data = $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json();

        return $data['setup']['systemInstruction']['parts'][0]['text'];
    }

    public function test_the_time_is_shared_by_level_and_the_summary_always_takes_the_last_two_minutes(): void
    {
        // Débutants : la leçon d'abord, mais déjà de la parole libre (docs/methode-teacher-joe.md, section 9).
        $this->assertSame(['lesson' => 4, 'practice' => 3, 'conversation' => 6, 'summary' => 2, 'merged' => false, 'minutes' => 15], LessonPlan::for(Level::A1, 15));
        $this->assertSame(['lesson' => 4, 'practice' => 3, 'conversation' => 6, 'summary' => 2, 'merged' => false, 'minutes' => 15], LessonPlan::for(Level::A2, 15));
        $this->assertSame(['lesson' => 3, 'practice' => 3, 'conversation' => 7, 'summary' => 2, 'merged' => false, 'minutes' => 15], LessonPlan::for(Level::B1, 15));
        $this->assertSame(['lesson' => 3, 'practice' => 2, 'conversation' => 8, 'summary' => 2, 'merged' => false, 'minutes' => 15], LessonPlan::for(Level::C1, 15));
        // Intensif : 30 minutes, toujours 2 minutes de bilan.
        $this->assertSame(['lesson' => 9, 'practice' => 7, 'conversation' => 12, 'summary' => 2, 'merged' => false, 'minutes' => 30], LessonPlan::for(Level::A1, 30));

        // Temps restant court : leçon et pratique fusionnées ; tout tient toujours dans la séance.
        foreach ([2, 3, 5, 7, 10, 15, 30] as $minutes) {
            foreach (Level::cases() as $level) {
                $plan = LessonPlan::for($level, $minutes);
                $this->assertSame($minutes, $plan['lesson'] + $plan['practice'] + $plan['conversation'] + $plan['summary'], "{$level->value} {$minutes}");
                $this->assertSame(min(2, $minutes), $plan['summary']);
                $this->assertSame($minutes < 6, $plan['merged']);
            }
        }

        $this->assertSame(['lesson' => 0, 'practice' => 240, 'conversation' => 420, 'summary' => 780], LessonPlan::starts(LessonPlan::for(Level::A1, 15)));
    }

    public function test_the_lesson_teaches_first_then_practices_then_talks_then_sums_up(): void
    {
        $text = $this->systemText($this->learner(Level::A1));

        $this->assertStringContainsString('A REAL TEACHER TEACHES FIRST, THEN PRACTICES, THEN LETS THE LEARNER TALK FREELY.', $text);
        $this->assertStringContainsString('Never start with a topic you have not taught.', $text);
        $this->assertStringContainsString('STEP 1 — LESSON. Teach the board ONE ITEM AT A TIME, in small, lively pieces, never a monologue', $text);
        $this->assertStringContainsString('its MEANING and WHEN to use it', $text);
        $this->assertStringContainsString('then "Your turn: …"', $text);
        $this->assertStringContainsString('never "Is it clear?"; never move on while it is not understood.', $text);
        $this->assertStringContainsString('EVERY item ends with the learner speaking', $text);
        $this->assertStringContainsString("STEP 2 — GUIDED PRACTICE. Start with this exact sentence: « Now, let's practice! »", $text);
        $this->assertStringContainsString('at least one short exercise where the learner makes their OWN sentence', $text);
        $this->assertStringContainsString("STEP 3 — CONVERSATION. Start with this exact sentence: « Now, let's talk! » Then OPEN questions", $text);
        $this->assertStringContainsString('The learner speaks MOST of the time', $text);
        $this->assertStringContainsString('\'Remember, we said "…".\'', $text);
        $this->assertStringContainsString('STEP 4 — SUMMARY (the last 2 minutes', $text);
        $this->assertStringContainsString('ONE sentence to remember', $text);
        $this->assertStringContainsString('These rules override the general rules above for this lesson', $text);

        // Dosage A1 sur 15 minutes et tableau (ici, celui de secours tiré du programme) dans les consignes.
        $this->assertStringContainsString("TODAY'S PLAN (15 minutes)\n- STEP 1, lesson: about 4 minute(s)\n- STEP 2, guided practice: about 3 minute(s)\n- STEP 3, conversation: about 6 minute(s)\n- STEP 4, summary: the last 2 minute(s)", $text);
        $this->assertStringContainsString("TODAY'S BOARD (shown on the learner's screen; teach it in this order)", $text);
        // Jamais de préparation du tableau à la connexion.
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'interactions'));
    }

    public function test_english_first_and_french_as_a_safety_net_by_level(): void
    {
        $common = config('teacher.lesson_language.common');
        $this->assertStringContainsString('You teach IN ENGLISH', $common);
        $this->assertStringContainsString('FIRST rephrase in simpler English', $common);
        $this->assertStringContainsString('ONLY THEN, if it is still not understood, explain briefly in French, and come back to English at once', $common);
        $this->assertStringContainsString('If the learner asks for an explanation in French, give it briefly, then go back to English.', $common);

        $expected = [
            'A1' => 'short French confirmation of the meaning (« Ça veut dire … »), then go straight back to English',
            'A2' => 'French only if the learner has not understood after one rephrasing in simpler English',
            'B1' => 'English only. French only as a last resort, for a difficult word or rule.',
            'B2' => 'Everything in English',
            'C2' => 'Everything in English',
        ];

        foreach ($expected as $level => $sentence) {
            $text = $this->systemText($this->learner(Level::from($level)));
            $this->assertStringContainsString($common, $text, $level);
            $this->assertStringContainsString($sentence, $text, $level);
            // L'ancienne règle de la séance du jour (« explications en français bienvenues ») n'est plus donnée.
            $this->assertStringNotContainsString(config("teacher.level_guidance.{$level}"), $text, $level);
            $this->actingAs($this->latestUser())->postJson('/seance/fin')->assertOk();
        }
    }

    private function latestUser(): User
    {
        return User::query()->latest('id')->firstOrFail();
    }

    public function test_the_prepared_board_is_given_to_the_teacher_in_its_order(): void
    {
        $user = $this->learner(Level::A1);
        $program = $user->activeProgram()->firstOrFail();
        $lesson = $program->currentLesson()->firstOrFail();
        LessonBoard::query()->create([
            'key' => LessonBoards::key($lesson, $program), 'level' => 'A1', 'unit_number' => 1, 'lesson_number' => 1,
            'situation_index' => $lesson->situation_index, 'goal' => 'work', 'content' => LessonBoards::normalize(LessonBoardTest::board()), 'provider' => 'gemini',
        ]);

        $this->assertStringContainsString(implode("\n", [
            "TODAY'S BOARD (shown on the learner's screen; teach it in this order)",
            '- Title: My family',
            '- Key phrases:',
            '  * "This is my sister." = Voici ma sœur.',
        ]), $text = $this->systemText($user));
        $this->assertStringContainsString("- Verbs:\n  * I have / she has = avoir", $text);
        $this->assertStringContainsString('- Rule: Use "his" for a man and "her" for a woman: Kofi and his mother. / (in French) « his » pour un homme', $text);
    }

    public function test_cues_follow_the_step_and_the_screen_receives_the_phase_phrases_and_times(): void
    {
        $data = $this->actingAs($this->learner(Level::A1))->postJson('/seance/connexion')->assertOk()->json();

        $this->assertSame(['practice' => "Now, let's practice!", 'conversation' => "Now, let's talk!", 'summary' => "Let's sum up!"], $data['phases']['phrases']);
        $this->assertSame(['lesson' => 0, 'practice' => 240, 'conversation' => 420, 'summary' => 780], $data['phases']['starts']);
        $this->assertStringContainsString('you are in STEP 2 (guided practice)', $data['cues']['resume_phases']['practice']);
        $this->assertStringContainsString('do not start the lesson again', $data['cues']['resume_phases']['lesson']);
        $this->assertStringContainsString('you are in STEP 3 (conversation: the role play)', $data['cues']['pause_phases']['conversation']);
        $this->assertStringContainsString("say « Now, let's talk! » and start the role play", $data['cues']['conversation']);
        $this->assertStringContainsString("Start STEP 4 now with « Let's sum up! »", $data['cues']['warning']);
    }

    public function test_openai_receives_exactly_the_same_four_step_instructions(): void
    {
        $user = $this->learner(Level::A2);
        $setup = TeacherSetup::lesson($user, 15, null, 'TODAY\'S BOARD (test)');
        $session = OpenAiRealtime::session($setup);

        $this->assertSame($setup['systemInstruction']['parts'][0]['text'], $session['instructions']);
        $this->assertStringContainsString('STEP 1 — LESSON', $session['instructions']);
        $this->assertStringContainsString(config('teacher.lesson_language.A2'), $session['instructions']);
    }

    public function test_the_lesson_screen_shows_the_board_with_its_items_and_the_phase(): void
    {
        $user = $this->learner(Level::A1);
        $program = $user->activeProgram()->firstOrFail();
        $lesson = $program->currentLesson()->firstOrFail();
        LessonBoard::query()->create([
            'key' => LessonBoards::key($lesson, $program), 'level' => 'A1', 'unit_number' => 1, 'lesson_number' => 1,
            'situation_index' => $lesson->situation_index, 'goal' => 'work', 'content' => LessonBoards::normalize(LessonBoardTest::board()), 'provider' => 'gemini',
        ]);

        $html = $this->actingAs($user)->get('/seance')->assertOk()->getContent();

        // Sous Teacher Joe (déplacé à droite sur grand écran par live-session.js), étape « Leçon » au début.
        $this->assertMatchesRegularExpression('/<div id="board-home"><details id="board" class="board" data-translations="show" data-phase="lesson">/', $html);
        $this->assertStringContainsString('<div id="board-wide"></div>', $html);
        $this->assertStringContainsString('<span class="board-phase" data-board-phase>Leçon</span>', $html);
        $this->assertStringContainsString('data-board-item="p0" data-kind="phrase" data-say="This is my sister."', $html);
        $this->assertStringContainsString('data-board-item="w0" data-kind="word" data-say="mother / mom"', $html);
        $this->assertStringContainsString('data-board-item="b0" data-kind="verb" data-say="I have / she has"', $html);
        $this->assertStringContainsString('data-board-item="r" data-kind="phrase"', $html);
        $this->assertStringContainsString('<span class="board-fr">Voici ma sœur.</span>', $html);
        $this->assertStringContainsString('data-lesson-board="', $html);
        // A1 : traductions visibles, sans case à cocher.
        $this->assertStringNotContainsString('Voir les traductions', $html);
    }

    public function test_translations_are_discreet_in_b1_and_hidden_from_b2(): void
    {
        $this->actingAs($this->learner(Level::B1))->get('/seance')->assertOk()
            ->assertSee('data-translations="discreet"', false)->assertSee('Voir les traductions');
        $this->actingAs($this->learner(Level::C1))->get('/seance')->assertOk()
            ->assertSee('data-translations="hidden"', false);

        $css = (string) file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('.board[data-translations="hidden"] .board-fr { display: none; }', $css);
        $this->assertStringContainsString('.board.show-translations .board-fr { display: block;', $css);
    }

    public function test_the_board_is_only_on_the_daily_lesson_and_the_screen_reads_it_without_any_function(): void
    {
        $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->assertDontSee('id="board"', false);

        $script = (string) file_get_contents(public_path('js/live-session.js'));
        $this->assertStringContainsString('const lit = board?.tracker?.feed(text);', $script);
        $this->assertStringContainsString("cue(isResume ? resumeCue('resume') : state.config.cues.start);", $script);
        $this->assertStringContainsString("cue(resumeCue('pause'));", $script);
        $this->assertStringContainsString('board.tracker.needsConversationNudge(elapsed) && !teacherSpeaking() && !state.generating', $script);
        // Aucune fonction ajoutée : seule la fonction de bilan existe dans la séance du jour.
        $setup = TeacherSetup::lesson($this->learner(Level::A2), 15);
        $this->assertSame([TeacherSetup::END_LESSON], array_column($setup['tools'][0]['functionDeclarations'], 'name'));
    }
}
