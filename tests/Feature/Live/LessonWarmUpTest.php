<?php

namespace Tests\Feature\Live;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Gemini\TeacherSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Début de séance : l'élève RETROUVE la phrase à retenir de la veille, puis parle de son devoir (rappel actif).
 * Bilan : ce que l'élève sait maintenant FAIRE. Aucun nouvel appel de fonction, aucun appel réel.
 */
class LessonWarmUpTest extends TestCase
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

    private function systemText(User $user): string
    {
        return $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json('setup.systemInstruction.parts.0.text');
    }

    public function test_the_lesson_starts_by_recalling_yesterdays_sentence_and_homework(): void
    {
        $user = $this->learner();
        $user->lessons()->create([
            'subscription_id' => $user->activeSubscription->id, 'date' => today()->subDay(), 'allowed_seconds' => 900,
            'used_seconds' => 900, 'started_at' => now()->subDay(), 'status' => LessonStatus::Completed,
        ])->forceFill([
            'summary' => '"I am from Lomé." = Je viens de Lomé. Vous savez maintenant vous présenter.',
            'homework' => 'Dites trois phrases sur votre ville.', 'reported_at' => now()->subDay(),
        ])->save();

        $text = $this->systemText($user);

        $this->assertStringContainsString('WARM-UP (one minute at most', $text);
        // D'abord une vraie question ouverte sur sa vie, à son niveau, puis le rappel de la veille.
        $this->assertStringContainsString('(1) ONE real open question about their life, at their level', $text);
        $this->assertStringContainsString('A2: "What did you do today?"', $text);
        $this->assertStringContainsString('make them RECALL the sentence to remember from its meaning', $text);
        $this->assertStringContainsString('the first word as a clue if needed', $text);
        $this->assertStringContainsString('ONE question on the homework (no reproach if not done)', $text);
        $this->assertStringContainsString('- Summary: "I am from Lomé." = Je viens de Lomé.', $text);
        $this->assertStringContainsString('- Homework given: Dites trois phrases sur votre ville.', $text);
        $this->assertStringContainsString('Use them for the WARM-UP, after the open question', $text);
        // Ordre du premier tour : accueil, comment travailler, rappel, sujet du jour.
        $this->assertStringContainsString('then HOW TO WORK WITH ME, then the WARM-UP, then today\'s topic', $text);
        $this->assertStringContainsString('do the WARM-UP (an open question, then the recall if there was a previous lesson)', TeacherSetup::cue('lesson_start', ['minutes' => 15]));
    }

    public function test_the_first_lesson_has_no_warm_up_and_the_summary_says_what_the_learner_can_do(): void
    {
        $data = $this->actingAs($this->learner())->postJson('/seance/connexion')->assertOk()->json('setup');
        $text = $data['systemInstruction']['parts'][0]['text'];

        $this->assertStringContainsString('- None: this is the first lesson of the programme (no warm-up today).', $text);
        $this->assertStringContainsString('what the learner can now DO', $text);
        $this->assertStringContainsString('« Aujourd\'hui, vous savez … »', $text);
        $this->assertStringContainsString('never a promise of result', $text);
        $this->assertStringContainsString('« Today, you can … »', TeacherSetup::cue('lesson_warning', ['remaining' => 2]));

        // Toujours UNE seule fonction ; le résumé commence par la phrase à retenir (rappel de la séance suivante).
        $functions = $data['tools'][0]['functionDeclarations'];
        $this->assertCount(1, $functions);
        $this->assertSame(TeacherSetup::END_LESSON, $functions[0]['name']);
        $this->assertStringContainsString('Start with the sentence to remember, in English between double quotes', $functions[0]['parameters']['properties']['summary']['description']);
    }
}
