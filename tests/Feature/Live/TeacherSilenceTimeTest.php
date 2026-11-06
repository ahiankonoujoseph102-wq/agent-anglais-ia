<?php

namespace Tests\Feature\Live;

use App\Enums\Level;
use App\Models\Lesson;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Gemini\TeacherSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Séance du fondateur avec Teacher Rose (6 novembre 2026) : plus d'une minute et demie sans réponse du professeur,
 * comptée sur le temps de l'élève ; « Almost » sur une réponse juste ; « aller » traduit par « see » ; demande
 * d'explication en français ignorée ; professeur qui attend une réponse que l'élève n'a pas. Corrigé ici.
 */
class TeacherSilenceTimeTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur', 'services.gemini.token_minutes' => 5]);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton'])]);
        $this->freezeSecond();
    }

    private function subscriber(): User
    {
        $user = User::factory()->level(Level::A2)->create(['name' => 'Ama']);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user);

        return $user;
    }

    private function lesson(User $user): Lesson
    {
        return $user->lessons()->latest('id')->firstOrFail();
    }

    public function test_the_time_spent_waiting_for_a_silent_teacher_is_given_back(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();

        foreach (range(1, 4) as $signal) {
            $this->travel(15)->seconds();
            $this->actingAs($user)->postJson('/seance/signal', ['connected' => true])->assertOk();
        }
        $this->assertSame(60, $this->lesson($user)->used_seconds);

        // Le professeur ne répond plus depuis 20 secondes : la relance automatique le signale, avec ces 20 secondes.
        $this->travel(20)->seconds();
        $this->actingAs($user)->postJson('/seance/signal', ['connected' => false, 'lost_seconds' => 20])->assertOk();
        $this->assertSame(60, $this->lesson($user)->used_seconds, 'Les 20 secondes sans réponse ne sont pas comptées.');

        // Il reparle 70 secondes plus tard (les signaux continuent, connexion « coupée ») : rien n'est compté.
        foreach (range(1, 4) as $signal) {
            $this->travel(15)->seconds();
            $this->actingAs($user)->postJson('/seance/signal', ['connected' => false])->assertOk();
        }
        $this->travel(10)->seconds();
        $this->actingAs($user)->postJson('/seance/signal', ['connected' => true])->assertOk();
        $lesson = $this->lesson($user);
        $this->assertSame(60, $lesson->used_seconds);
        $this->assertSame(90, $lesson->paused_seconds);
    }

    public function test_a_lost_time_never_goes_before_the_start_or_beyond_a_minute(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();

        $this->travel(10)->seconds();
        $this->actingAs($user)->postJson('/seance/signal', ['connected' => false, 'lost_seconds' => 600])->assertOk();
        $this->travel(5)->seconds();
        $this->actingAs($user)->postJson('/seance/signal', ['connected' => true])->assertOk();

        $this->assertSame(15, $this->lesson($user)->paused_seconds);
        $this->actingAs($user)->postJson('/seance/signal', ['lost_seconds' => 601])->assertUnprocessable();
    }

    public function test_up_to_ten_minutes_of_connection_problems_are_given_back(): void
    {
        $this->assertSame(600, config('platform.live.disconnect_pause_max_seconds'));
    }

    public function test_the_browser_pauses_the_timer_while_the_teacher_does_not_answer(): void
    {
        $js = file_get_contents(public_path('js/live-session.js'));

        $this->assertStringContainsString('state.teacherStuck = true;', $js);
        $this->assertStringContainsString('link.lost_seconds = lost;', $js);
        $this->assertStringContainsString("'Temps en pause (connexion)'", $js);
        $this->assertStringContainsString('connected: state.connected && !state.teacherStuck', $js);
    }

    public function test_both_teachers_judge_word_by_word_follow_the_board_and_lead(): void
    {
        foreach (['joe', 'rose'] as $teacher) {
            $user = User::factory()->level(Level::A2)->create(['name' => 'Ama']);
            $user->forceFill(['teacher' => $teacher])->save();
            $text = TeacherSetup::instructions('lesson', $user, 15, ['context' => '']);

            $this->assertStringContainsString('the same words as the model = "Correct!", NEVER "Almost"', $text, $teacher);
            $this->assertStringContainsString('THE BOARD IS YOUR ANSWER KEY', $text, $teacher);
            $this->assertStringContainsString('« aller » = "go"', $text, $teacher);
            $this->assertStringContainsString('YOU LEAD:', $text, $teacher);
            $this->assertStringContainsString('never ask the English question again before it', $text, $teacher);
            $this->assertStringContainsString('« Pardon, continuez. »', $text, $teacher);
            $this->assertStringContainsString('« On entend souvent « Did you went? » : c\'est faux. »', $text, $teacher);
        }
    }

    public function test_teachers_wait_a_little_longer_before_answering(): void
    {
        $this->assertSame(1600, TeacherSetup::silenceMs('A1'));
        $this->assertSame(1400, TeacherSetup::silenceMs('A2'));
    }
}
