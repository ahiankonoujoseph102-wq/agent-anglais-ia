<?php

namespace Tests\Feature\Program;

use App\Enums\ErrorStatus;
use App\Enums\Level;
use App\Enums\ProgramLessonStatus;
use App\Enums\ProgramStatus;
use App\Models\Lesson;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Curriculum\Curriculum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\Concerns\IgnoresSignalSilence;
use Tests\TestCase;

class LessonProgramTest extends TestCase
{
    use BuildsPrograms, IgnoresSignalSilence, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-test']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton'])]);
        $this->freezeSecond();
    }

    private function learner(): User
    {
        $user = User::factory()->create(['name' => 'Yao']);
        $user->level = Level::B1;
        $user->save();
        Subscription::factory()->for($user)->create();
        $this->buildProgram($user, null, ['goal' => 'business', 'occupation' => 'Importateur de pièces auto']);

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function report(array $overrides = []): array
    {
        return array_merge([
            'lesson_status' => 'completed',
            'summary' => 'Nous avons commandé des marchandises au téléphone.',
            'homework' => 'Préparer 3 questions pour un fournisseur.',
            'errors' => [],
            'reviewed_errors' => [],
            'scores' => ['fluency' => 3, 'pronunciation' => 2, 'grammar' => 4, 'vocabulary' => 3, 'comprehension' => 5],
        ], $overrides);
    }

    /**
     * Une séance complète : connexion, quelques minutes, bilan du professeur, fin.
     *
     * @param  array<string, mixed>  $report
     */
    private function lessonWithReport(User $user, array $report): Lesson
    {
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        $this->travel(10)->minutes();
        $this->actingAs($user)->postJson('/seance/bilan', $report)->assertOk();
        $this->actingAs($user)->postJson('/seance/fin')->assertOk();
        $this->travel(1)->days();

        return $user->lessons()->latest('id')->firstOrFail();
    }

    public function test_lesson_instructions_contain_profile_lesson_previous_summary_and_errors(): void
    {
        $user = $this->learner();
        $program = $user->activeProgram;
        $unit = Curriculum::unit(Level::B1, 1);

        $previous = $user->lessons()->create(['date' => today()->subDay(), 'allowed_seconds' => 900, 'used_seconds' => 900, 'status' => 'completed', 'summary' => 'Révision du present perfect.', 'homework' => 'Raconter 3 expériences.']);
        $previous->forceFill(['reported_at' => now()->subDay()])->save();
        $error = $user->learnerErrors()->create(['wrong' => 'I am agree', 'correct' => 'I agree', 'explanation' => 'agree est un verbe']);

        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();

        Http::assertSent(function (Request $request) use ($unit, $error) {
            $text = $request['bidiGenerateContentSetup']['systemInstruction']['parts'][0]['text'];

            return str_contains($text, 'First name: Yao')
                && str_contains($text, 'Level: B1')
                && str_contains($text, 'Main goal: business')
                && str_contains($text, 'Occupation / situation: Importateur de pièces auto')
                && str_contains($text, 'Unit 1 of 12: "'.$unit->title.'" — lesson 1 of 3')
                && str_contains($text, array_key_first($unit->vocabulary))
                && str_contains($text, 'Summary: Révision du present perfect.')
                && str_contains($text, "[{$error->id}] wrong: \"I am agree\" → correct: \"I agree\"")
                && str_contains($text, 'ERRORS TO REVIEW')
                && str_contains($text, 'STEP 2 — GUIDED PRACTICE')
                && str_contains($text, 'STEP 3 — CONVERSATION');
        });

        $lesson = $user->lessons()->latest('id')->first();
        $this->assertSame($program->id, $lesson->program_id);
        $this->assertSame($program->currentLesson->id, $lesson->program_lesson_id);
    }

    public function test_structured_report_is_recorded_and_the_program_moves_on(): void
    {
        $user = $this->learner();

        $lesson = $this->lessonWithReport($user, $this->report([
            'errors' => [
                ['wrong' => 'I have ordered them last week', 'correct' => 'I ordered them last week', 'explanation' => 'Moment précis : prétérit.'],
                ['wrong' => 'He don\'t pay', 'correct' => 'He doesn\'t pay'],
            ],
        ]));

        $this->assertSame('completed', $lesson->outcome);
        $this->assertSame(3, $lesson->score_fluency);
        $this->assertSame(5, $lesson->score_comprehension);
        $this->assertSame('Préparer 3 questions pour un fournisseur.', $lesson->homework);
        $this->assertNotNull($lesson->reported_at);

        $this->assertSame(2, $user->openErrors()->count());
        $this->assertSame(ProgramLessonStatus::Completed, $lesson->programLesson->status);

        $program = $user->activeProgram()->sole();
        $this->assertSame(2, $program->currentLesson->position);
        $this->assertSame(2, $program->progressPercent());

        // Un seul bilan par séance.
        $this->travel(-1)->days();
        $this->actingAs($user)->postJson('/seance/bilan', $this->report())->assertConflict();
        $this->travel(1)->days();

        // Leçon « à reprendre » : on ne passe pas à la suivante.
        $this->lessonWithReport($user, $this->report(['lesson_status' => 'repeat']));
        $this->assertSame(2, $user->activeProgram()->sole()->currentLesson->position);
        $this->assertSame(1, $user->activeProgram()->sole()->currentLesson->attempts);

        // Historique visible par l'apprenant, avec résumé, devoir et erreurs.
        $this->actingAs($user)->get('/mes-seances')
            ->assertOk()
            ->assertSee('Nous avons commandé des marchandises au téléphone.')
            ->assertSee('Préparer 3 questions pour un fournisseur.')
            ->assertSee('I ordered them last week')
            ->assertSee('leçon à reprendre');
    }

    public function test_invalid_scores_are_rejected(): void
    {
        $user = $this->learner();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();

        $this->actingAs($user)->postJson('/seance/bilan', $this->report(['scores' => ['fluency' => 9]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scores.fluency', 'scores.grammar']);
    }

    public function test_an_error_is_removed_after_two_consecutive_corrections(): void
    {
        $user = $this->learner();
        $error = $user->learnerErrors()->create(['wrong' => 'I am agree', 'correct' => 'I agree']);

        // 1re correction, puis rechute : la série repart de zéro.
        $this->lessonWithReport($user, $this->report(['reviewed_errors' => [['id' => $error->id, 'corrected' => true]]]));
        $this->assertSame(1, $error->fresh()->correct_streak);

        $this->lessonWithReport($user, $this->report(['reviewed_errors' => [['id' => $error->id, 'corrected' => false]]]));
        $this->assertSame(0, $error->fresh()->correct_streak);
        $this->assertSame(2, $error->fresh()->times_seen);

        // Deux corrections de suite : l'erreur est retirée.
        $this->lessonWithReport($user, $this->report(['reviewed_errors' => [['id' => $error->id, 'corrected' => true]]]));
        $this->assertSame(ErrorStatus::Open, $error->fresh()->status);

        $this->lessonWithReport($user, $this->report(['reviewed_errors' => [['id' => $error->id, 'corrected' => true]]]));
        $this->assertSame(ErrorStatus::Resolved, $error->fresh()->status);
        $this->assertSame(0, $user->openErrors()->count());

        // Elle n'est plus envoyée au professeur.
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        Http::assertSent(fn (Request $request) => str_contains(
            $request['bidiGenerateContentSetup']['systemInstruction']['parts'][0]['text'] ?? '', 'ERRORS TO REVIEW (use the id in end_lesson.reviewed_errors)'."\n- None for now.",
        ));
    }

    public function test_a_repeated_error_is_not_duplicated_and_other_learners_errors_are_ignored(): void
    {
        $user = $this->learner();
        $other = User::factory()->create();
        $foreign = $other->learnerErrors()->create(['wrong' => 'x', 'correct' => 'y']);

        $this->lessonWithReport($user, $this->report(['errors' => [['wrong' => 'I am agree.', 'correct' => 'I agree.']]]));
        $this->lessonWithReport($user, $this->report([
            'errors' => [['wrong' => 'i am AGREE', 'correct' => 'I agree']],
            'reviewed_errors' => [['id' => $foreign->id, 'corrected' => true]],
        ]));

        $this->assertSame(1, $user->learnerErrors()->count());
        $this->assertSame(2, $user->learnerErrors()->first()->times_seen);
        $this->assertSame(0, $foreign->fresh()->correct_streak);
    }

    public function test_finishing_all_lessons_opens_the_level_test(): void
    {
        $user = $this->learner();
        $program = $user->activeProgram;
        $program->programLessons()->where('position', '<', 36)->update(['status' => ProgramLessonStatus::Completed]);

        $this->lessonWithReport($user, $this->report());

        $program->refresh();
        $this->assertSame(ProgramStatus::LevelTestDue, $program->status);
        $this->assertSame(100, $program->progressPercent());

        $user = $user->fresh();
        $this->actingAs($user)->get('/seance')->assertRedirect(route('level-test.show'));
        $this->actingAs($user)->get('/espace')->assertSee('Passer mon bilan');
    }

    public function test_dashboard_shows_progress_streak_skills_and_errors(): void
    {
        $user = $this->learner();

        $this->lessonWithReport($user, $this->report(['errors' => [['wrong' => 'He go', 'correct' => 'He goes']]]));
        $this->lessonWithReport($user, $this->report(['scores' => ['fluency' => 5, 'pronunciation' => 4, 'grammar' => 4, 'vocabulary' => 3, 'comprehension' => 5]]));
        $this->travel(-1)->days();

        $this->actingAs($user->fresh())->get('/espace')
            ->assertOk()
            ->assertSee('Progression du niveau B1')
            ->assertSee('5 %')
            ->assertSee('2 jours')
            ->assertSee('Aisance')
            ->assertSee('4,0')
            ->assertSee('He goes');
    }
}
