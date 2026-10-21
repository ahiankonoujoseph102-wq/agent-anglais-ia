<?php

namespace Tests\Feature\Live;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\Assessment;
use App\Models\Lesson;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Si la connexion est coupée, le temps de séance est mis en pause : l'élève ne perd pas de minutes. Coupure signalée
 * par le navigateur, ou plus de 30 secondes sans aucun signal ; 10 minutes de coupure au plus par séance. Le bouton
 * « Pause » de l'élève n'est pas une coupure : le temps continue.
 */
class DisconnectionPauseTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur', 'services.gemini.token_minutes' => 5]);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton456'])]);
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

    /**
     * Le navigateur envoie un signal toutes les 15 secondes pendant la durée donnée.
     */
    private function signals(User $user, int $seconds, ?bool $connected = true, string $url = '/seance/signal'): void
    {
        for ($done = 0; $done < $seconds; $done += 15) {
            $this->travel(15)->seconds();
            $this->actingAs($user)->postJson($url, $connected === null ? [] : ['connected' => $connected])->assertOk();
        }
    }

    public function test_a_disconnection_signalled_by_the_browser_pauses_the_time(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();

        $this->signals($user, 120);
        $this->assertSame(120, $this->lesson($user)->used_seconds);

        // Connexion avec le professeur coupée pendant 3 minutes (signal envoyé aussitôt) : le temps ne bouge pas.
        $this->actingAs($user)->postJson('/seance/signal', ['connected' => false])->assertOk();
        $this->signals($user, 180, connected: false);
        $lesson = $this->lesson($user);
        $this->assertTrue($lesson->isDisconnected());
        $this->assertSame(120, $lesson->used_seconds);
        $this->assertSame(780, $lesson->remainingSeconds());
        $this->assertSame(780, $user->remainingSecondsToday(false), 'Temps du jour : rien de perdu pendant la coupure.');

        // La connexion revient : la coupure est comptée, le temps repart.
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->assertJsonPath('remaining_seconds', 780);
        $this->signals($user, 60);

        $lesson = $this->lesson($user);
        $this->assertFalse($lesson->isDisconnected());
        $this->assertSame(180, $lesson->paused_seconds);
        $this->assertSame(180, $lesson->used_seconds);
        $this->assertSame(720, $lesson->remainingSeconds());
    }

    public function test_more_than_thirty_seconds_without_any_signal_is_a_disconnection(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        $this->signals($user, 60);

        // Plus d'internet du tout : aucun signal pendant 2 minutes, puis le navigateur se reconnecte.
        $this->travel(120)->seconds();
        $this->actingAs($user)->postJson('/seance/connexion')
            ->assertOk()
            ->assertJsonPath('resumed', true)
            ->assertJsonPath('remaining_seconds', 840);

        $lesson = $this->lesson($user);
        $this->assertSame(120, $lesson->paused_seconds);
        $this->assertSame(840, $lesson->remainingSeconds());
    }

    public function test_a_short_silence_is_not_a_disconnection(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        $this->signals($user, 30);

        // Un signal en retard (25 secondes) : le temps a continué.
        $this->travel(25)->seconds();
        $this->actingAs($user)->postJson('/seance/signal')->assertOk();

        $lesson = $this->lesson($user);
        $this->assertSame(0, $lesson->paused_seconds);
        $this->assertSame(55, $lesson->used_seconds);
    }

    public function test_at_most_ten_minutes_of_disconnection_per_session(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        $this->signals($user, 60);

        // 12 minutes de coupure : les 10 premières seulement sont en pause, ensuite le temps compte de nouveau.
        $this->signals($user, 720, connected: false);
        $this->assertSame(180, $this->lesson($user)->used_seconds);

        $this->signals($user, 15, connected: true);
        $lesson = $this->lesson($user);
        $this->assertSame(600, $lesson->paused_seconds);
        $this->assertSame(195, $lesson->used_seconds);
        $this->assertSame(0, $lesson->pauseAllowance());

        // Une nouvelle coupure n'arrête plus le temps.
        $this->signals($user, 60, connected: false);
        $this->assertSame(255, $this->lesson($user)->used_seconds);
    }

    public function test_the_end_of_the_session_is_pushed_back_by_the_disconnection(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        $this->signals($user, 60);
        $this->actingAs($user)->postJson('/seance/signal', ['connected' => false])->assertOk();
        $this->signals($user, 300, connected: false);
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();

        // 15 minutes de séance + 5 minutes de coupure : la séance est encore ouverte après 19 minutes et demie.
        $this->signals($user, 825);
        $this->assertSame(LessonStatus::InProgress, $this->lesson($user)->status);
        $this->assertSame(885, $this->lesson($user)->used_seconds);

        $this->travel(30)->seconds();
        $this->actingAs($user)->postJson('/seance/signal')->assertOk()->assertJson(['active' => false]);

        $lesson = $this->lesson($user);
        $this->assertSame(LessonStatus::Completed, $lesson->status);
        $this->assertSame(900, $lesson->used_seconds);
        $this->assertSame(0, $user->remainingSecondsToday());
    }

    public function test_the_scheduler_never_closes_a_session_during_a_disconnection(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        $this->signals($user, 60);
        $this->actingAs($user)->postJson('/seance/signal', ['connected' => false])->assertOk();

        // Plus aucun signal pendant 8 minutes (téléphone sans réseau) : la séance attend son élève.
        $this->travel(8)->minutes();
        $this->artisan('live:close-stale')->assertSuccessful();
        $this->assertSame(LessonStatus::InProgress, $this->lesson($user)->status);

        $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->assertJsonPath('resumed', true);
        $this->assertSame(60, $this->lesson($user)->elapsedUntil(now()));
    }

    public function test_an_abandoned_session_is_still_charged_until_its_last_token_expired(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        $this->signals($user, 60);
        $this->actingAs($user)->postJson('/seance/signal', ['connected' => false])->assertOk();

        // Jamais revenu : la séance est close après les 10 minutes de coupure permises, décomptée jusqu'à
        // l'expiration du dernier jeton (on ne parle pas gratuitement en coupant simplement les signaux).
        $this->travel(12)->minutes();
        $this->artisan('live:close-stale')->assertSuccessful();

        $lesson = $this->lesson($user);
        $this->assertSame(LessonStatus::Interrupted, $lesson->status);
        $this->assertSame(300, $lesson->used_seconds);
        $this->assertNull($lesson->disconnected_at);
    }

    public function test_the_pause_button_does_not_pause_the_time(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        $this->signals($user, 60);

        // Bouton « Pause » de l'élève : la connexion reste ouverte, les signaux disent « connecté ».
        $this->signals($user, 120, connected: true);

        $lesson = $this->lesson($user);
        $this->assertSame(0, $lesson->paused_seconds);
        $this->assertSame(180, $lesson->used_seconds);
    }

    public function test_the_free_assessment_is_paused_too(): void
    {
        $user = User::factory()->create(['name' => 'Kossi']);
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk();
        $this->signals($user, 60, url: '/evaluation/signal');
        $this->actingAs($user)->postJson('/evaluation/signal', ['connected' => false])->assertOk();
        $this->signals($user, 120, connected: false, url: '/evaluation/signal');
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk()->assertJsonPath('remaining_seconds', 240);

        $assessment = Assessment::sole();
        $this->assertSame(120, $assessment->paused_seconds);
        $this->assertSame(60, $assessment->used_seconds);
    }

    public function test_a_wrong_connected_value_is_refused(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();

        $this->actingAs($user)->postJson('/seance/signal', ['connected' => 'peut-être'])->assertUnprocessable();
    }
}
