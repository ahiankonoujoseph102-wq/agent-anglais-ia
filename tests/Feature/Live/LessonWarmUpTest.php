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

        $this->assertStringContainsString('WARM-UP (about two minutes at most), a real conversation, ONE question per turn', $text);
        // D'abord comment il va (réponse attendue), une ou deux petites questions, puis ce qu'il a retenu de la veille.
        $this->assertStringContainsString('react naturally ("I\'m fine too, thank you!"', $text);
        $this->assertStringContainsString('ONE or TWO more small, easy questions about their life to put them at ease, one per turn', $text);
        $this->assertStringContainsString('A2: "What did you do today?"', $text);
        $this->assertStringContainsString('(2) CONTINUITY, only if PREVIOUS LESSON below gives a summary (even if it was with another teacher)', $text);
        $this->assertStringContainsString('then a SHORT reminder (one or two sentences) of the key point', $text);
        $this->assertStringContainsString('(3) Only then, in a new turn: today\'s topic', $text);
        $this->assertStringContainsString('the first word as a clue if needed', $text);
        $this->assertStringContainsString('ONE question on the homework (no reproach if not done)', $text);
        $this->assertStringContainsString('- Summary: "I am from Lomé." = Je viens de Lomé.', $text);
        $this->assertStringContainsString('- Homework given: Dites trois phrases sur votre ville.', $text);
        $this->assertStringContainsString('Use them for the CONTINUITY part of the WARM-UP, after the greeting exchange', $text);
        // Ordre du premier tour : accueil, comment travailler, rappel, sujet du jour.
        // Premier tour : salut, durée, « comment travailler avec moi », UNE question, puis on attend.
        $this->assertStringContainsString('then HOW TO WORK WITH ME, then ONE question: how they are today. STOP there and WAIT for the answer. Never present the topic or the board in this first turn.', $text);
        $cue = TeacherSetup::cue('lesson_start', ['minutes' => 15, 'handoff_note' => '.']);
        $this->assertStringContainsString('then ask how they are today and STOP: wait for their answer. Do not announce the topic and do not teach anything in this turn.', $cue);
        $this->assertStringEndsWith('Then follow the WARM-UP, one question per turn.', $cue);
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
