<?php

namespace Tests\Feature\Live;

use App\Enums\AssessmentStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Fin de l'évaluation : après le verdict, moment des questions libres puis conclusion,
 * au plus 2 minutes de plus, comptées à partir du verdict et coupées par le serveur.
 * Aucun appel réel à Gemini.
 */
class AssessmentQuestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-de-test']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton'])]);
        $this->freezeSecond();
    }

    private function verdictAfter(User $user, int $seconds)
    {
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk()->assertJsonPath('questions', false);
        $this->travel($seconds)->seconds();

        return $this->actingAs($user)->postJson('/evaluation/resultat', ['level' => 'A2', 'feedback' => 'Bonne base.']);
    }

    public function test_the_verdict_opens_two_minutes_of_questions_counted_from_the_verdict(): void
    {
        $user = User::factory()->create();

        $this->verdictAfter($user, 100)
            ->assertOk()
            ->assertJsonPath('level', 'A2')
            ->assertJsonPath('questions', true)
            ->assertJsonPath('remaining_seconds', 120)
            ->assertJsonPath('conclusion_cue_seconds', 30)
            ->assertJsonPath('closing_phrase', 'On se retrouve de l\'autre côté !');

        // Verdict tôt : la durée totale est plus courte (100 + 120 secondes).
        $assessment = $user->assessment()->first();
        $this->assertSame(220, $assessment->allowed_seconds);
        $this->assertTrue($assessment->inQuestions());

        $this->travel(110)->seconds();
        $this->actingAs($user)->postJson('/evaluation/signal')->assertJsonPath('active', true)->assertJsonPath('remaining_seconds', 10);

        // Le serveur coupe à la nouvelle limite.
        $this->travel(15)->seconds();
        $this->actingAs($user)->postJson('/evaluation/signal')->assertJsonPath('active', false);

        $assessment->refresh();
        $this->assertSame(AssessmentStatus::Completed, $assessment->status);
        $this->assertSame(220, $assessment->used_seconds);
    }

    public function test_total_duration_never_exceeds_seven_minutes(): void
    {
        $user = User::factory()->create();

        // Verdict à la dernière seconde des 5 minutes : 299 + 120 = 419 secondes au plus.
        $this->verdictAfter($user, 299)->assertJsonPath('questions', true)->assertJsonPath('remaining_seconds', 120);

        $assessment = $user->assessment()->first();
        $this->assertSame(419, $assessment->allowed_seconds);
        $this->assertLessThanOrEqual(7 * 60, $assessment->allowed_seconds);

        $this->travel(10)->minutes();
        $this->artisan('live:close-stale')->assertSuccessful();
        $this->assertSame(419, $assessment->fresh()->used_seconds);

        // La page « Consommation » compte ces minutes.
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/consommation')
            ->assertOk()
            ->assertSee('6 minutes 59 secondes / 6 minutes 59 secondes');
    }

    public function test_one_verdict_only_and_no_extension_twice(): void
    {
        $user = User::factory()->create();
        $this->verdictAfter($user, 120)->assertOk();

        $this->travel(30)->seconds();
        $this->actingAs($user)->postJson('/evaluation/resultat', ['level' => 'B1', 'feedback' => 'Autre.'])->assertStatus(409);

        $assessment = $user->assessment()->first();
        $this->assertSame('A2', $assessment->level->value);
        $this->assertSame(240, $assessment->allowed_seconds, 'le deuxième appel ne repousse pas la fin');
    }

    public function test_reconnection_during_the_questions_keeps_the_questions_and_the_new_limit(): void
    {
        $user = User::factory()->create();
        $this->verdictAfter($user, 200)->assertOk();
        $this->travel(20)->seconds();

        $response = $this->actingAs($user)->postJson('/evaluation/connexion', ['handle' => 'reprise'])
            ->assertOk()
            ->assertJsonPath('questions', true)
            ->assertJsonPath('remaining_seconds', 100)
            ->assertJsonPath('cues.conclusion', '[System] About 30 seconds remain. Do not take any more questions. If you have not given your conclusion yet, give it now, in French, once, ending with « On se retrouve de l\'autre côté ! ». If you have already said this sentence, say nothing.');

        // Le jeton ne dure jamais au-delà de la nouvelle limite ; les consignes gardent la durée de l'évaluation.
        $this->assertLessThanOrEqual(100, $response->json('token_expires_in'));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/auth_tokens')
            && str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), 'The assessment itself lasts 5 minutes at most'));
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
}
