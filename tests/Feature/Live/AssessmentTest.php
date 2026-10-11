<?php

namespace Tests\Feature\Live;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssessmentTest extends TestCase
{
    use RefreshDatabase;

    /** Réponse simulée de l'API Gemini (null = jeton délivré). */
    private mixed $geminiResponse = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur']);
        Http::fake(['*/auth_tokens' => fn () => $this->geminiResponse ?? Http::response(['name' => 'auth_tokens/jeton123'])]);
        $this->freezeSecond();
    }

    public function test_server_produces_an_ephemeral_token_without_exposing_the_api_key(): void
    {
        $user = User::factory()->create(['name' => 'Kossi']);

        $response = $this->actingAs($user)->postJson('/evaluation/connexion')
            ->assertOk()
            ->assertJsonPath('remaining_seconds', 300)
            ->assertJsonPath('token_expires_in', 300)
            ->assertJsonPath('resumed', false)
            ->assertJsonPath('setup.model', 'models/gemini-3.8-live');

        $this->assertStringContainsString(
            'wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContentConstrained?access_token=auth_tokens%2Fjeton123',
            $response->json('websocket_url'),
        );
        $this->assertStringNotContainsString('cle-secrete-du-serveur', $response->getContent());

        Http::assertSent(function (Request $request) {
            $setup = $request['bidiGenerateContentSetup'];

            return $request->url() === 'https://generativelanguage.googleapis.com/v1alpha/auth_tokens'
                && $request->hasHeader('x-goog-api-key', 'cle-secrete-du-serveur')
                && $request['uses'] === 1
                // Le jeton expire à la limite de l'évaluation (5 min) : la coupure est imposée par Google.
                && $request['expireTime'] === now()->addMinutes(5)->utc()->toIso8601ZuluString()
                && $request['newSessionExpireTime'] === now()->addMinute()->utc()->toIso8601ZuluString()
                && $setup['model'] === 'models/gemini-3.8-live'
                && $setup['generationConfig']['responseModalities'] === ['AUDIO']
                && str_contains($setup['systemInstruction']['parts'][0]['text'], 'Kossi')
                && str_contains($setup['systemInstruction']['parts'][0]['text'], 'FREE ORAL LEVEL ASSESSMENT')
                && $setup['tools'][0]['functionDeclarations'][0]['name'] === 'record_assessment_result';
        });

        $assessment = $user->assessment()->first();
        $this->assertSame(AssessmentStatus::InProgress, $assessment->status);
        $this->assertSame(300, $assessment->allowed_seconds);
        $this->assertSame(1, $assessment->connections);
    }

    public function test_assessment_duration_comes_from_the_configuration(): void
    {
        config(['platform.assessment.max_minutes' => 3]);

        $this->actingAs(User::factory()->create())->postJson('/evaluation/connexion')
            ->assertOk()
            ->assertJsonPath('remaining_seconds', 180);

        Http::assertSent(fn (Request $request) => $request['expireTime'] === now()->addMinutes(3)->utc()->toIso8601ZuluString());
    }

    public function test_reconnection_continues_the_same_assessment_without_extra_time(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk();

        $this->travel(2)->minutes();

        $this->actingAs($user)->postJson('/evaluation/connexion', ['handle' => 'reprise-abc'])
            ->assertOk()
            ->assertJsonPath('resumed', true)
            ->assertJsonPath('remaining_seconds', 180)
            ->assertJsonPath('setup.sessionResumption.handle', 'reprise-abc');

        $this->assertSame(1, $user->assessment()->count());
        $this->assertSame(2, $user->assessment()->first()->connections);
    }

    public function test_only_one_assessment_per_account(): void
    {
        $user = User::factory()->level(Level::A2)->create();
        $user->assessment()->create([
            'status' => AssessmentStatus::Completed,
            'started_at' => now()->subDay(),
            'ended_at' => now()->subDay()->addMinutes(5),
            'allowed_seconds' => 300,
            'used_seconds' => 300,
            'level' => Level::A2,
        ]);

        $this->actingAs($user)->postJson('/evaluation/connexion')
            ->assertConflict()
            ->assertJsonPath('message', 'Vous avez déjà passé votre évaluation gratuite : il n\'y en a qu\'une par compte.');

        $this->actingAs($user)->get('/evaluation')->assertRedirect('/espace');

        Http::assertNothingSent();
    }

    public function test_assessment_is_cut_at_the_time_limit(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk();

        $this->travel(2)->minutes();
        $this->actingAs($user)->postJson('/evaluation/signal', ['input_tokens' => 1200, 'output_tokens' => 800])
            ->assertOk()
            ->assertJson(['active' => true, 'remaining_seconds' => 180]);

        $this->assertSame(120, $user->assessment()->first()->used_seconds);

        $this->travel(4)->minutes();
        $this->actingAs($user)->postJson('/evaluation/signal', ['input_tokens' => 300, 'output_tokens' => 200])
            ->assertOk()
            ->assertJson(['active' => false, 'remaining_seconds' => 0]);

        $assessment = $user->assessment()->first();
        $this->assertSame(300, $assessment->used_seconds);
        $this->assertSame(1500, $assessment->input_tokens);
        $this->assertSame(1000, $assessment->output_tokens);
        // Sans niveau annoncé, l'évaluation est interrompue… et ne peut pas être recommencée.
        $this->assertSame(AssessmentStatus::Abandoned, $assessment->status);

        $this->actingAs($user)->postJson('/evaluation/connexion')->assertConflict();
    }

    public function test_level_and_feedback_are_recorded_on_the_assessment_and_the_account(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk();
        $this->travel(4)->minutes();

        $this->actingAs($user)->postJson('/evaluation/resultat', [
            'level' => 'B1',
            'feedback' => 'Bonne compréhension, travaillez le passé.',
        ])->assertOk()->assertJson(['level' => 'B1', 'label' => 'Intermédiaire']);

        $this->assertSame(Level::B1, $user->fresh()->level);
        $this->assertSame('Bonne compréhension, travaillez le passé.', $user->assessment()->first()->feedback);

        // Le résultat ne peut pas être modifié ensuite.
        $this->actingAs($user)->postJson('/evaluation/resultat', ['level' => 'C2'])->assertConflict();
        $this->assertSame(Level::B1, $user->fresh()->level);

        $this->actingAs($user)->postJson('/evaluation/fin')->assertOk();
        $assessment = $user->assessment()->first();
        $this->assertSame(AssessmentStatus::Completed, $assessment->status);
        $this->assertSame(240, $assessment->used_seconds);
    }

    public function test_result_arriving_right_after_the_cut_is_still_recorded(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk();

        $this->travel(5)->minutes();
        $this->artisan('live:close-stale')->assertSuccessful();
        $this->assertSame(AssessmentStatus::Abandoned, $user->assessment()->first()->status);

        $this->travel(30)->seconds();
        $this->actingAs($user)->postJson('/evaluation/resultat', ['level' => 'A2', 'feedback' => 'Bon début.'])->assertOk();

        $this->assertSame(AssessmentStatus::Completed, $user->assessment()->first()->status);
        $this->assertSame(Level::A2, $user->fresh()->level);
    }

    public function test_invalid_level_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk();

        $this->actingAs($user)->postJson('/evaluation/resultat', ['level' => 'Z9'])->assertUnprocessable();
        $this->assertNull($user->fresh()->level);
    }

    public function test_free_quota_reached_gives_a_clear_message_and_does_not_consume_the_assessment(): void
    {
        $this->geminiResponse = Http::response(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED']], 429);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/evaluation/connexion')
            ->assertServiceUnavailable()
            ->assertJsonPath('quota', true)
            ->assertJsonPath('message', 'Le professeur a atteint sa limite de conversations pour le moment. Réessayez un peu plus tard.');

        $this->assertNull($user->assessment()->first());
    }

    public function test_missing_api_key_is_reported_without_calling_google(): void
    {
        config(['services.gemini.key' => null]);

        $this->actingAs(User::factory()->create())->postJson('/evaluation/connexion')
            ->assertServiceUnavailable()
            ->assertJsonPath('quota', false);

        Http::assertNothingSent();
    }

    public function test_session_page_is_shown_to_a_new_learner(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/evaluation')
            ->assertOk()
            ->assertSee('Évaluation gratuite')
            ->assertSee('Commencer')
            ->assertSee('5:00')
            ->assertSee('js/live-session.js');
    }

    public function test_guests_cannot_get_a_token(): void
    {
        $this->postJson('/evaluation/connexion')->assertUnauthorized();
        Http::assertNothingSent();
    }
}
