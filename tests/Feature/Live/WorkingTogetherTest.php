<?php

namespace Tests\Feature\Live;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Gemini\TeacherSetup;
use App\Support\Live\WorkingTogether;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * « Comment travailler avec moi » : accueil complet aux premières séances, une phrase ensuite, rappel quand l'élève
 * bloque, phrase rassurante au début de l'évaluation, rien de répété après une reprise. Aucun appel réel.
 */
class WorkingTogetherTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton']), '*/interactions' => Http::response([], 500)]);
    }

    private function learner(): User
    {
        $user = User::factory()->level(Level::A1)->create(['name' => 'Afi', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('intensif')->create();
        $this->buildProgram($user, Level::A1);

        return $user->fresh();
    }

    private function heldLesson(User $user, int $usedSeconds): void
    {
        $user->lessons()->create([
            'subscription_id' => $user->activeSubscription->id,
            'date' => today()->subDay(),
            'allowed_seconds' => 900,
            'used_seconds' => $usedSeconds,
            'started_at' => now()->subDay(),
            'ended_at' => now()->subDay()->addSeconds($usedSeconds),
            'status' => LessonStatus::Completed,
        ]);
    }

    private function systemText(User $user): string
    {
        return $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json('setup.systemInstruction.parts.0.text');
    }

    public function test_the_first_lessons_explain_how_to_work_together_once(): void
    {
        $text = $this->systemText($this->learner());

        $this->assertStringContainsString('HOW TO WORK WITH ME (this is lesson number 1 of the learner', $text);
        $this->assertStringContainsString('they can ask you their questions in French at any moment', $text);
        $this->assertStringContainsString('« Can you repeat, please? »', $text);
        $this->assertStringContainsString('they have the right to make mistakes', $text);
        $this->assertStringContainsString('Never say it again in this lesson, even after a pause or a reconnection.', $text);
        // Placé avant le tableau, dans le premier tour.
        $this->assertStringContainsString('say that today\'s lesson lasts', $text);
        $this->assertLessThan(strpos($text, 'STEP 1 — LESSON'), strpos($text, 'HOW TO WORK WITH ME'));
        $this->assertStringContainsString('WHEN THE LEARNER SEEMS STUCK', $text);
        $this->assertStringContainsString('At most twice in the whole conversation', $text);
        $this->assertStringNotContainsString(':working_together', $text);
    }

    public function test_after_two_real_lessons_only_one_sentence_remains(): void
    {
        $user = $this->learner();
        // Une séance coupée au bout de quelques secondes ne compte pas.
        $this->heldLesson($user, 30);
        $this->heldLesson($user, 600);
        $this->assertSame(2, WorkingTogether::lessonNumber($user));
        $this->assertStringContainsString('lesson number 2 of the learner', $this->systemText($user));

        $this->heldLesson($user, 900);
        $this->assertSame(3, WorkingTogether::lessonNumber($user));
        $text = $this->systemText($user);

        $this->assertStringContainsString('HOW TO WORK WITH ME (lesson number 3: the learner already knows it)', $text);
        $this->assertStringContainsString('ONE short sentence only', $text);
        $this->assertStringNotContainsString('in about twenty seconds', $text);
        // Le rappel quand l'élève bloque reste.
        $this->assertStringContainsString('WHEN THE LEARNER SEEMS STUCK', $text);
    }

    public function test_a_resumed_lesson_keeps_its_number_and_resume_cues_never_repeat_it(): void
    {
        $user = $this->learner();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        $lesson = $user->lessons()->latest('id')->first();
        $lesson->forceFill(['used_seconds' => 300])->save();

        // La séance en cours n'est jamais comptée : même numéro, mêmes consignes à la reprise.
        $this->assertSame(1, WorkingTogether::lessonNumber($user, $lesson));
        $data = $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json();
        $this->assertStringContainsString('lesson number 1 of the learner', $data['setup']['systemInstruction']['parts'][0]['text']);

        foreach (['resume', 'pause_resume', 'lesson_resume', 'lesson_pause_resume'] as $cue) {
            $this->assertStringContainsString('how to work together', TeacherSetup::cue($cue, ['phase' => 'STEP 1']));
        }
    }

    public function test_the_assessment_reassures_in_french_and_my_courses_explains_it_in_the_welcome(): void
    {
        $user = User::factory()->create(['name' => 'Afi']);
        $assessment = TeacherSetup::instructions('assessment', $user, 5);
        $this->assertStringContainsString('« Répondez comme vous pouvez : si vous ne comprenez pas, dites-le-moi, même en français. Vous avez le droit de vous tromper. »', $assessment);
        $this->assertStringNotContainsString('HOW TO WORK WITH ME', $assessment);

        $first = TeacherSetup::course($user, 10, 'THE LESSON', null, 1)['systemInstruction']['parts'][0]['text'];
        $this->assertStringContainsString('one of the learner\'s first « Mes cours » sessions', $first);
        $this->assertStringContainsString('follow HOW TO WORK WITH ME above', $first);

        $later = TeacherSetup::course($user, 10, 'THE LESSON', null, 3)['systemInstruction']['parts'][0]['text'];
        $this->assertStringContainsString('Do not explain it again', $later);
        $this->assertStringContainsString('WHEN THE LEARNER SEEMS STUCK', $later);

        // Le bilan de niveau n'est pas un moment d'apprentissage : aucun accueil.
        $this->assertStringNotContainsString('HOW TO WORK WITH ME', TeacherSetup::instructions('level_test', $user, 10));
    }
}
