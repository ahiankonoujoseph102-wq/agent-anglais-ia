<?php

namespace Tests\Feature\Live;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\Lesson;
use App\Models\Subscription;
use App\Models\User;
use App\Support\DailyTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\Concerns\IgnoresSignalSilence;
use Tests\TestCase;

class LessonTest extends TestCase
{
    use BuildsPrograms, IgnoresSignalSilence, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur', 'services.gemini.token_minutes' => 5]);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton456'])]);
        $this->freezeSecond();
    }

    private function subscriber(string $plan = 'essentiel'): User
    {
        $user = User::factory()->level(Level::A2)->create(['name' => 'Ama']);
        Subscription::factory()->for($user)->forPlan($plan)->create();
        $this->buildProgram($user);

        return $user;
    }

    private function lesson(User $user): Lesson
    {
        return $user->lessons()->latest('id')->firstOrFail();
    }

    public function test_learner_without_subscription_cannot_start_a_lesson(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/seance/connexion')
            ->assertForbidden()
            ->assertJsonPath('message', 'Votre abonnement n\'est pas actif : choisissez une formule pour suivre vos séances.');
        $this->actingAs($user)->get('/seance')->assertRedirect('/abonnement');

        Http::assertNothingSent();
    }

    public function test_lesson_token_is_limited_and_lesson_gets_the_plan_duration(): void
    {
        $user = $this->subscriber('intensif');

        $this->actingAs($user)->postJson('/seance/connexion')
            ->assertOk()
            ->assertJsonPath('remaining_seconds', 1800)
            // Un jeton dure au plus 5 minutes : le navigateur doit en redemander un.
            ->assertJsonPath('token_expires_in', 300)
            ->assertJsonPath('warning_seconds', 120)
            ->assertJsonPath('cues.start', '[System] The learner is connected and ready. Start STEP 1 now: greet the learner, announce that the lesson lasts 30 minutes (exactly this number, never another one), follow HOW TO WORK WITH ME, do the WARM-UP (an open question, then the recall if there was a previous lesson), announce today\'s topic, then teach the first item of the board.');

        Http::assertSent(function (Request $request) {
            $instructions = $request['bidiGenerateContentSetup']['systemInstruction']['parts'][0]['text'];

            return $request['expireTime'] === now()->addMinutes(5)->utc()->toIso8601ZuluString()
                && str_contains($instructions, 'It lasts 30 minutes')
                && str_contains($instructions, 'elementary (A2)')
                && $request['bidiGenerateContentSetup']['tools'][0]['functionDeclarations'][0]['name'] === 'end_lesson';
        });

        $lesson = $this->lesson($user);
        $this->assertSame(1800, $lesson->allowed_seconds);
        $this->assertSame(LessonStatus::InProgress, $lesson->status);
        $this->assertTrue($lesson->date->isToday());
    }

    public function test_lesson_only_gets_the_time_left_today(): void
    {
        $user = $this->subscriber();
        $user->lessons()->create([
            'date' => today(),
            'allowed_seconds' => 900,
            'used_seconds' => 780,
            'status' => LessonStatus::Completed,
        ]);

        $this->actingAs($user)->postJson('/seance/connexion')
            ->assertOk()
            ->assertJsonPath('remaining_seconds', 120)
            ->assertJsonPath('token_expires_in', 120);

        // Le jeton expire exactement à la fin du temps restant.
        Http::assertSent(fn (Request $request) => $request['expireTime'] === now()->addSeconds(120)->utc()->toIso8601ZuluString());
    }

    public function test_new_connection_is_refused_when_the_daily_time_is_used_up(): void
    {
        $user = $this->subscriber();
        $user->lessons()->create([
            'date' => today(),
            'allowed_seconds' => 900,
            'used_seconds' => 900,
            'status' => LessonStatus::Completed,
        ]);

        $this->actingAs($user)->postJson('/seance/connexion')
            ->assertForbidden()
            ->assertJsonPath('message', DailyTime::EXHAUSTED);
        $this->actingAs($user)->get('/seance')->assertRedirect('/espace');

        Http::assertNothingSent();
    }

    public function test_signals_record_time_and_tokens_and_the_server_cuts_at_the_limit(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();

        $this->travel(3)->minutes();
        $this->actingAs($user)->postJson('/seance/signal', ['input_tokens' => 5000, 'output_tokens' => 2000])
            ->assertOk()
            ->assertJson(['active' => true, 'remaining_seconds' => 720]);

        $this->assertSame(180, $this->lesson($user)->used_seconds);
        // Affichage : le temps réellement restant ; pour ouvrir une autre séance, la séance ouverte garde tout son temps.
        $this->assertSame(720, $user->remainingSecondsToday(false));
        $this->assertSame(0, $user->remainingSecondsToday());

        // Le navigateur continue d'envoyer des signaux après la limite : la séance est close.
        $this->travel(13)->minutes();
        $this->actingAs($user)->postJson('/seance/signal', ['input_tokens' => 1000, 'output_tokens' => 500])
            ->assertOk()
            ->assertJson(['active' => false, 'remaining_seconds' => 0]);

        $lesson = $this->lesson($user);
        $this->assertSame(LessonStatus::Completed, $lesson->status);
        $this->assertSame(900, $lesson->used_seconds);
        $this->assertSame(6000, $lesson->input_tokens);
        $this->assertSame(2500, $lesson->output_tokens);

        $this->actingAs($user)->postJson('/seance/connexion')->assertForbidden();
    }

    public function test_renewing_the_token_continues_the_same_lesson(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();

        $this->travel(290)->seconds();
        $this->actingAs($user)->postJson('/seance/connexion', ['handle' => 'reprise-xyz'])
            ->assertOk()
            ->assertJsonPath('resumed', true)
            ->assertJsonPath('remaining_seconds', 610)
            ->assertJsonPath('setup.sessionResumption.handle', 'reprise-xyz');

        $this->assertSame(1, $user->lessons()->count());
        $this->assertSame(2, $this->lesson($user)->connections);
    }

    public function test_reconnections_are_limited(): void
    {
        config(['services.gemini.max_connections' => 2]);
        $user = $this->subscriber();

        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        $this->actingAs($user)->postJson('/seance/connexion')->assertTooManyRequests();
    }

    public function test_abandoned_lesson_is_charged_until_its_token_expired(): void
    {
        // Silence du navigateur tenu pour une coupure, comme sur le site.
        config(['platform.live.disconnect_after_seconds' => 30]);

        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();

        $this->travel(15)->seconds();
        $this->actingAs($user)->postJson('/seance/signal')->assertOk();

        // Le navigateur disparaît (et ne revient pas pendant les 10 minutes de coupure permises). Son jeton restait
        // valable 5 minutes : tout ce temps est décompté.
        $this->travel(12)->minutes();
        $this->artisan('live:close-stale')->assertSuccessful();

        $lesson = $this->lesson($user);
        $this->assertSame(LessonStatus::Interrupted, $lesson->status);
        $this->assertSame(300, $lesson->used_seconds);
        $this->assertSame(600, $user->remainingSecondsToday());

        // Une nouvelle séance repart avec le temps restant.
        $this->actingAs($user)->postJson('/seance/connexion')
            ->assertOk()
            ->assertJsonPath('resumed', false)
            ->assertJsonPath('remaining_seconds', 600);
    }

    public function test_lesson_still_running_is_not_closed_by_the_scheduler(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();

        $this->travel(2)->minutes();
        $this->artisan('live:close-stale')->assertSuccessful();

        $this->assertSame(LessonStatus::InProgress, $this->lesson($user)->status);
    }

    public function test_summary_homework_and_end_of_lesson_are_recorded(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        $this->travel(13)->minutes();

        $this->actingAs($user)->postJson('/seance/bilan', [
            'lesson_status' => 'completed',
            'summary' => 'Les salutations et se présenter.',
            'homework' => 'Se présenter en 5 phrases.',
            'scores' => ['fluency' => 3, 'pronunciation' => 2, 'grammar' => 3, 'vocabulary' => 4, 'comprehension' => 4],
        ])->assertOk();

        $this->actingAs($user)->postJson('/seance/fin', ['output_tokens' => 42])->assertOk();

        $lesson = $this->lesson($user);
        $this->assertSame(LessonStatus::Completed, $lesson->status);
        $this->assertSame(780, $lesson->used_seconds);
        $this->assertSame(42, $lesson->output_tokens);
        $this->assertSame('Se présenter en 5 phrases.', $lesson->homework);

        $this->actingAs($user)->get('/espace')
            ->assertSee('Les salutations et se présenter.')
            ->assertSee('Se présenter en 5 phrases.');
    }

    public function test_session_page_shows_time_left(): void
    {
        $user = $this->subscriber();

        $this->actingAs($user)->get('/seance')
            ->assertOk()
            ->assertSee('Ma séance du jour')
            ->assertSee('15:00')
            ->assertSee('Terminer');
    }
}
