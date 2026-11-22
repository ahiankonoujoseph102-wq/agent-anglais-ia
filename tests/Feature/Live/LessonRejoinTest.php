<?php

namespace Tests\Feature\Live;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\ConversationLine;
use App\Models\Lesson;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Séance reprise : l'élève revient sur sa séance commencée (page rouverte) ou rouvre la même leçon après une séance
 * interrompue aujourd'hui. Le professeur ne recommence pas la leçon : « Welcome back! », où l'on s'était arrêté
 * (dernières répliques données comme données), puis la suite.
 */
class LessonRejoinTest extends TestCase
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
        $user = User::factory()->level(Level::A1)->create(['name' => 'Afi Mensah', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A1);

        return $user->fresh();
    }

    private function lines(User $user, Lesson $lesson): void
    {
        foreach ([
            ['teacher', 'Repeat after me: How much is it?'],
            ['learner', 'How much is it?'],
            ['teacher', 'Very good! Now, how do you say « C\'est trop cher » in English?'],
        ] as $position => [$speaker, $text]) {
            ConversationLine::create([
                'user_id' => $user->id, 'conversation_type' => 'lesson', 'conversation_id' => $lesson->id,
                'position' => $position, 'speaker' => $speaker, 'text' => $text, 'spoken_at' => now(),
            ]);
        }
    }

    private function startCue(User $user): string
    {
        return $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json('cues.start');
    }

    public function test_a_reopened_lesson_continues_where_it_stopped(): void
    {
        $user = $this->learner();
        $this->startCue($user);
        $lesson = $user->lessons()->firstOrFail();
        $this->lines($user, $lesson);

        // La page est rouverte : même séance en cours, nouvelle page.
        $cue = $this->startCue($user);

        $this->assertSame(1, $user->lessons()->count());
        $this->assertStringContainsString('coming BACK to today\'s lesson', $cue);
        $this->assertStringContainsString('This is NOT a new lesson', $cue);
        $this->assertStringContainsString('"Welcome back!"', $cue);
        $this->assertStringContainsString('WHERE WE STOPPED', $cue);
        $this->assertStringContainsString('Teacher: Very good! Now, how do you say « C\'est trop cher » in English?', $cue);
        $this->assertStringContainsString('Learner: How much is it?', $cue);
        $this->assertStringNotContainsString('ask how they are today', $cue);
    }

    public function test_a_lesson_interrupted_earlier_today_is_resumed_in_a_new_session(): void
    {
        $user = $this->learner();
        $programLesson = $user->activeProgram->programLessons()->orderBy('position')->first();
        $old = $user->lessons()->make()->forceFill([
            'subscription_id' => $user->activeSubscription->id, 'program_id' => $user->activeProgram->id,
            'program_lesson_id' => $programLesson->id, 'date' => today(), 'allowed_seconds' => 900, 'used_seconds' => 300,
            'started_at' => now()->subMinutes(30), 'ended_at' => now()->subMinutes(25), 'status' => LessonStatus::Interrupted,
        ]);
        $old->save();
        $this->lines($user, $old);

        $cue = $this->startCue($user);

        $this->assertSame(2, $user->lessons()->count());
        $this->assertStringContainsString('"Welcome back!"', $cue);
        $this->assertStringContainsString('Teacher: Repeat after me: How much is it?', $cue);
    }

    public function test_a_new_lesson_still_starts_normally(): void
    {
        $user = $this->learner();

        $cue = $this->startCue($user);

        $this->assertStringContainsString('ask how they are today and STOP', $cue);
        $this->assertStringNotContainsString('WHERE WE STOPPED', $cue);
    }

    public function test_a_lesson_finished_with_its_report_is_not_resumed(): void
    {
        $user = $this->learner();
        $programLesson = $user->activeProgram->programLessons()->orderBy('position')->first();
        $old = $user->lessons()->make()->forceFill([
            'subscription_id' => $user->activeSubscription->id, 'program_lesson_id' => $programLesson->id, 'date' => today(),
            'allowed_seconds' => 900, 'used_seconds' => 300, 'started_at' => now()->subMinutes(30),
            'status' => LessonStatus::Interrupted, 'reported_at' => now()->subMinutes(25),
        ]);
        $old->save();
        $this->lines($user, $old);

        $this->assertStringNotContainsString('WHERE WE STOPPED', $this->startCue($user));
    }

    public function test_a_lesson_stopped_by_a_long_pause_is_resumed_in_a_new_session(): void
    {
        $user = $this->learner();
        $this->startCue($user);
        $lesson = $user->lessons()->firstOrFail();
        $this->lines($user, $lesson);

        // La page arrête la séance (pause de plus de deux minutes) : fin sans bilan.
        $this->actingAs($user)->postJson('/seance/fin')->assertOk();
        $this->assertNull($lesson->fresh()->reported_at);

        $cue = $this->startCue($user);

        $this->assertSame(2, $user->lessons()->count());
        $this->assertStringContainsString('"Welcome back!"', $cue);
        $this->assertStringContainsString('Teacher: Very good!', $cue);
    }

    public function test_the_page_resumes_after_a_pause_and_never_alarms_during_it(): void
    {
        $js = file_get_contents(public_path('js/live-session.js'));

        // Connexion fermée pendant la pause : rouverte à la reprise, sans message de coupure.
        $this->assertStringContainsString('state.reconnectOnResume = true;', $js);
        // « Reprendre la séance » sort aussi de la pause.
        $this->assertMatchesRegularExpression('/function resumeSession\(\) \{[\s\S]*?state\.paused = false;/', $js);
        // Pause trop longue : la séance du jour et « Mes cours » proposent de reprendre, minutes gardées.
        $this->assertStringContainsString('Vos minutes non utilisées restent disponibles aujourd', $js);
        $this->assertStringContainsString("label: 'Reprendre ma séance'", $js);
    }
}
