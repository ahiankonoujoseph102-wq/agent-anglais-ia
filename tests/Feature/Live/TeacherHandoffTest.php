<?php

namespace Tests\Feature\Live;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Passage de relais entre professeurs : quand la dernière séance a été faite avec l'autre professeur, le nouveau
 * commence par « Teacher Joe m'a fait un rapport sur ce que vous avez appris la dernière fois : … » (ou l'inverse),
 * tiré du résumé et des erreurs à revoir, puis une ou deux questions, avant la leçon du jour.
 */
class TeacherHandoffTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur', 'teachers.list.rose.ready' => true]);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton']), '*/interactions' => Http::response([], 500)]);
    }

    private function learner(?string $teacher): User
    {
        $user = User::factory()->level(Level::A1)->create(['name' => 'Afi Mensah', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A1);
        $user->forceFill(['teacher' => $teacher])->save();

        return $user->fresh();
    }

    private function pastLesson(User $user, ?string $teacher, bool $reported = true): void
    {
        $user->lessons()->make()->forceFill([
            'subscription_id' => $user->activeSubscription->id, 'date' => today()->subDay(), 'allowed_seconds' => 900,
            'used_seconds' => 600, 'started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addMinutes(10),
            'status' => LessonStatus::Completed, 'teacher' => $teacher,
            'summary' => $reported ? '"Nice to meet you." The learner can greet people at the market.' : null,
            'reported_at' => $reported ? now()->subDay() : null,
        ])->save();
    }

    private function startCue(User $user): string
    {
        return $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json('cues.start');
    }

    public function test_teacher_rose_reports_what_teacher_joe_taught_last_time(): void
    {
        $user = $this->learner('rose');
        // Séance d'avant les professeurs (colonne vide) : Teacher Joe.
        $this->pastLesson($user, null);

        $cue = $this->startCue($user);

        $this->assertStringContainsString('« Teacher Joe m\'a fait un rapport sur ce que vous avez appris la dernière fois : »', $cue);
        $this->assertStringContainsString('taken ONLY from PREVIOUS LESSON and ERRORS TO REVIEW', $cue);
        $this->assertStringContainsString('ONE or TWO short questions, one at a time', $cue);
        // Le contexte de la séance donne bien le résumé de la séance précédente.
        $this->assertStringContainsString('Nice to meet you.', $this->actingAs($user)->postJson('/seance/connexion')->json('setup.systemInstruction.parts.0.text'));
    }

    public function test_and_the_other_way_round(): void
    {
        $user = $this->learner(null);
        $this->pastLesson($user, 'rose');

        $this->assertStringContainsString('« Teacher Rose m\'a fait un rapport sur ce que vous avez appris la dernière fois : »', $this->startCue($user));
    }

    public function test_no_handover_with_the_same_teacher_or_nothing_to_report(): void
    {
        $same = $this->learner('rose');
        $this->pastLesson($same, 'rose');
        $this->assertStringNotContainsString('HANDOVER', $this->startCue($same));

        $nothing = $this->learner('rose');
        $this->pastLesson($nothing, 'joe', reported: false);
        $this->assertStringNotContainsString('HANDOVER', $this->startCue($nothing));

        $first = $this->learner('rose');
        $this->assertStringNotContainsString('HANDOVER', $this->startCue($first));
    }

    public function test_the_last_held_lesson_decides(): void
    {
        $user = $this->learner('rose');
        $this->pastLesson($user, null);
        // Puis une séance avec Teacher Rose : plus de relais.
        $this->pastLesson($user, 'rose');

        $this->assertStringNotContainsString('HANDOVER', $this->startCue($user));
    }
}
