<?php

namespace Tests\Feature\Program;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Enums\ProgramLessonStatus;
use App\Models\Program;
use App\Models\ProgramLesson;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Curriculum\LessonBoards;
use App\Support\Curriculum\SpacedReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Révision espacée : une phrase clé des leçons faites il y a environ 1, 3 et 7 jours, redemandée pendant la pratique
 * guidée. Phrases tirées des tableaux gardés (ou de secours) : aucun appel au modèle texte.
 */
class SpacedReviewTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton']), '*/interactions' => Http::response([], 500)]);
    }

    /**
     * @return array{0: User, 1: Program}
     */
    private function learner(): array
    {
        $user = User::factory()->level(Level::A1)->create(['name' => 'Afi', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('intensif')->create();
        $program = $this->buildProgram($user, Level::A1);

        return [$user->fresh(), $program];
    }

    private function done(User $user, Program $program, int $position, int $daysAgo, string $outcome = 'completed'): ProgramLesson
    {
        $programLesson = $program->programLessons()->where('position', $position)->firstOrFail();
        $programLesson->forceFill(['status' => ProgramLessonStatus::Completed])->save();

        $user->lessons()->create([
            'subscription_id' => $user->activeSubscription->id, 'program_id' => $program->id, 'program_lesson_id' => $programLesson->id,
            'date' => today()->subDays($daysAgo), 'allowed_seconds' => 900, 'used_seconds' => 900,
            'started_at' => now()->subDays($daysAgo), 'status' => LessonStatus::Completed,
        ])->forceFill(['outcome' => $outcome, 'reported_at' => now()->subDays($daysAgo), 'summary' => 'Résumé.'])->save();

        return $programLesson->setRelation('program', $program);
    }

    public function test_one_phrase_of_the_lessons_of_about_one_three_and_seven_days_ago_comes_back(): void
    {
        [$user, $program] = $this->learner();
        $yesterday = $this->done($user, $program, 3, 1);
        $threeDays = $this->done($user, $program, 2, 3);
        $lastWeek = $this->done($user, $program, 1, 8);
        $current = $program->currentLesson()->first()->setRelation('program', $program);

        $phrases = SpacedReview::phrases($user, $program, $current);

        $this->assertCount(3, $phrases);
        // La veille : la deuxième phrase (la première est souvent déjà la phrase à retenir du rappel).
        $this->assertSame(LessonBoards::for($yesterday, $program)['phrases'][1]['en'], $phrases[0]['en']);
        $this->assertSame(1, $phrases[0]['days']);
        $this->assertSame(LessonBoards::for($threeDays, $program)['phrases'][0]['en'], $phrases[1]['en']);
        $this->assertSame(3, $phrases[1]['days']);
        $this->assertSame(LessonBoards::for($lastWeek, $program)['phrases'][0]['en'], $phrases[2]['en']);
        $this->assertSame(8, $phrases[2]['days']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'interactions'));
    }

    public function test_lessons_to_repeat_and_the_current_lesson_are_never_reviewed(): void
    {
        [$user, $program] = $this->learner();
        $this->done($user, $program, 1, 2, 'repeat');
        $current = $program->currentLesson()->first()->setRelation('program', $program);

        $this->assertSame([], SpacedReview::phrases($user, $program, $current));
    }

    public function test_the_daily_lesson_asks_the_learner_to_recall_them_in_guided_practice(): void
    {
        [$user, $program] = $this->learner();
        $done = $this->done($user, $program, 1, 3);
        $phrase = LessonBoards::for($done, $program)['phrases'][0]['en'];

        $text = $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json('setup.systemInstruction.parts.0.text');

        $this->assertStringContainsString('PHRASES TO REVIEW (spaced review of past lessons, in STEP 2: the learner recalls them)', $text);
        $this->assertStringContainsString('- "'.$phrase.'"', $text);
        $this->assertStringContainsString('(learned about 3 day(s) ago)', $text);
        $this->assertStringContainsString('SPACED REVIEW: for each phrase of "PHRASES TO REVIEW", make the learner RECALL it', $text);
    }

    public function test_without_past_lessons_there_is_nothing_to_review(): void
    {
        [$user] = $this->learner();

        $text = $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json('setup.systemInstruction.parts.0.text');

        $this->assertStringContainsString("PHRASES TO REVIEW (spaced review of past lessons, in STEP 2: the learner recalls them)\n- None today.", $text);
    }
}
