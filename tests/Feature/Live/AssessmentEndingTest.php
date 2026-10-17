<?php

namespace Tests\Feature\Live;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\Assessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Fin de l'évaluation : transcription gardée, niveau estimé si le temps finit avant le verdict,
 * formules affichées sous le résultat. Aucun appel réel à Gemini.
 */
class AssessmentEndingTest extends TestCase
{
    use RefreshDatabase;

    /** Réponse simulée du modèle texte (null = niveau B1 estimé). */
    private mixed $textResponse = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur']);
        Http::fake([
            '*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton123']),
            '*/interactions' => fn () => $this->textResponse ?? Http::response(['steps' => [
                ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => "LEVEL: B1\nFEEDBACK: Vous comprenez bien les questions. Travaillez le passé."]]],
            ]]),
        ]);
        $this->freezeSecond();
    }

    private function startWithTranscript(User $user, string $transcript): void
    {
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk();
        $this->travel(1)->minutes();
        $this->actingAs($user)->postJson('/evaluation/signal', ['transcript' => $transcript])->assertOk();
    }

    public function test_signals_keep_the_transcript_of_the_assessment(): void
    {
        $user = User::factory()->create();
        $this->startWithTranscript($user, "Teacher: Hello! What is your name?\nLearner: My name is Afi.");
        $this->actingAs($user)->postJson('/evaluation/signal', ['transcript' => 'Learner: I live in Lomé.'])->assertOk();

        $this->assertSame("Teacher: Hello! What is your name?\nLearner: My name is Afi.\nLearner: I live in Lomé.", $user->assessment()->first()->transcript);
    }

    public function test_time_over_before_the_verdict_gives_a_level_estimated_from_the_conversation(): void
    {
        $user = User::factory()->create();
        $this->startWithTranscript($user, "Teacher: Where do you live?\nLearner: I live in Kara and I work at the market.");

        $this->travel(5)->minutes();
        $this->actingAs($user)->postJson('/evaluation/fin', ['transcript' => 'Learner: Yesterday I go to Lomé.'])
            ->assertOk()
            ->assertJsonPath('level', 'B1')
            ->assertJsonPath('feedback', 'Vous comprenez bien les questions. Travaillez le passé.');

        $assessment = $user->assessment()->first();
        $this->assertSame(AssessmentStatus::Completed, $assessment->status);
        $this->assertSame(Level::B1, $assessment->level);
        $this->assertSame(Level::B1, $user->fresh()->level);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/interactions')
            && str_contains($request['input'], 'Learner: I live in Kara')
            && str_contains($request['input'], 'Learner: Yesterday I go to Lomé.'));
    }

    public function test_closed_page_is_estimated_by_the_scheduled_command_too(): void
    {
        $user = User::factory()->create();
        $this->startWithTranscript($user, "Teacher: What is your job?\nLearner: I am teacher.");

        $this->travel(10)->minutes();
        $this->artisan('live:close-stale')->assertSuccessful();

        $this->assertSame(Level::B1, $user->assessment()->first()->level);
    }

    public function test_without_an_answer_from_the_text_model_the_level_is_a_provisional_a1(): void
    {
        $this->textResponse = Http::response(['error' => ['status' => 'UNAVAILABLE']], 503);
        $user = User::factory()->create();
        $this->startWithTranscript($user, "Teacher: What is your name?\nLearner: Afi.");

        $this->travel(5)->minutes();
        $this->actingAs($user)->postJson('/evaluation/fin')->assertOk()->assertJsonPath('level', 'A1');

        $assessment = $user->assessment()->first();
        $this->assertSame(AssessmentStatus::Completed, $assessment->status);
        $this->assertStringContainsString('niveau A1 provisoire', $assessment->feedback);
    }

    public function test_nothing_said_by_the_learner_means_nothing_to_judge(): void
    {
        $user = User::factory()->create();
        $this->startWithTranscript($user, 'Teacher: Hello! What is your name?');

        $this->travel(5)->minutes();
        $this->actingAs($user)->postJson('/evaluation/fin')->assertOk()->assertJsonPath('level', null);

        $this->assertSame(AssessmentStatus::Abandoned, $user->assessment()->first()->status);
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/interactions'));
    }

    public function test_the_verdict_of_the_teacher_is_never_replaced_by_an_estimate(): void
    {
        $user = User::factory()->create();
        $this->startWithTranscript($user, "Teacher: Tell me about your weekend.\nLearner: I went to the beach with my friends.");
        $this->actingAs($user)->postJson('/evaluation/resultat', ['level' => 'B2', 'feedback' => 'Très bien.'])->assertOk();

        $this->actingAs($user)->postJson('/evaluation/fin')->assertOk()->assertJsonPath('level', 'B2');
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/interactions'));
    }

    public function test_transcript_is_capped(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk();

        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($user)->postJson('/evaluation/signal', ['transcript' => 'Learner: '.str_repeat('a', 7000)])->assertOk();
        }

        $this->assertSame(Assessment::TRANSCRIPT_MAX, mb_strlen($user->assessment()->first()->transcript));
        $this->actingAs($user)->postJson('/evaluation/signal', ['transcript' => str_repeat('a', 9000)])->assertUnprocessable();
    }

    public function test_assessment_screen_has_the_plans_below_the_result_with_orange_buttons(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();

        $this->assertStringContainsString('id="plans-offer"', $html);
        $this->assertStringContainsString('class="btn btn-accent btn-block btn-plan" href="'.route('payment.create', 'essentiel').'"', $html);
        $this->assertStringContainsString('Choisir Essentiel', $html);
        $this->assertStringContainsString('js/conversation-end.js', $html);
        $this->assertStringContainsString('js/teacher-cues.js', $html);
    }
}
