<?php

namespace Tests\Unit;

use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\TeacherSetup;
use App\Support\Live\OpenAiRealtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Façon de corriger de Teacher Joe : faire chercher, puis donner la bonne forme, puis faire redire la phrase
 * entière ; vérifier la compréhension en faisant parler ; redire mieux ; pièges des francophones par contraste ;
 * jamais complaisant. Mêmes consignes chez Gemini et chez OpenAI, aucune nouvelle fonction.
 */
class TeacherCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function learner(): User
    {
        $user = new User(['name' => 'Afi']);
        $user->level = Level::A2;

        return $user;
    }

    public function test_the_lesson_and_my_courses_let_the_learner_find_the_right_form_first(): void
    {
        foreach (['lesson', 'course'] as $kind) {
            $text = TeacherSetup::instructions($kind, $this->learner(), 15);

            $this->assertStringContainsString('HOW TO CORRECT (daily lesson and « Mes cours »):', $text, $kind);
            $this->assertStringContainsString('1) Let the learner FIND it first', $text, $kind);
            $this->assertStringContainsString('If they do not find it after ONE try, give the right form', $text, $kind);
            $this->assertStringContainsString('and make them say the WHOLE sentence again', $text, $kind);
            $this->assertStringContainsString('The goal is to be understood, never to sound like a native speaker.', $text, $kind);
            // Jamais complaisant (B5).
            $this->assertStringContainsString('If the learner insists that a wrong form is right, stay warm but accurate', $text, $kind);
            // Vérifier en faisant parler, pas seulement « C'est clair ? » (B1).
            $this->assertStringContainsString('A "yes" is not enough', $text, $kind);
            // Une seule règle de correction : la règle générale renvoie à HOW TO CORRECT.
            $this->assertStringContainsString('When the activity below gives "HOW TO CORRECT", follow it instead.', $text, $kind);
            $this->assertStringContainsString('see HOW TO CORRECT', $text, $kind);
            $this->assertSame(1, substr_count($text, 'HOW TO CORRECT (daily lesson'), $kind);
        }
    }

    public function test_the_daily_lesson_teaches_pitfalls_by_contrast_and_asks_to_say_it_again_better(): void
    {
        $text = TeacherSetup::instructions('lesson', $this->learner(), 15);

        $this->assertStringContainsString('PITFALL OF THE DAY', $text);
        $this->assertStringContainsString('by CONTRAST', $text);
        $this->assertStringContainsString('at most twice in the lesson: never turn the lesson into a list of mistakes', $text);
        $this->assertStringContainsString('SAY IT AGAIN, BETTER: once or twice in this step (never more)', $text);
        $this->assertStringContainsString('From B1 upward, correct less on the spot', $text);
        $this->assertStringContainsString('then ask the tiny question that proves it', $text);
    }

    public function test_the_assessment_and_the_level_test_keep_their_short_correction(): void
    {
        foreach (['assessment', 'level_test'] as $kind) {
            $text = TeacherSetup::instructions($kind, $this->learner(), 5);

            $this->assertStringNotContainsString('HOW TO CORRECT (daily lesson', $text, $kind);
            $this->assertStringNotContainsString('SAY IT AGAIN, BETTER', $text, $kind);
        }
    }

    public function test_openai_receives_exactly_the_same_rules_and_still_one_function(): void
    {
        $setup = TeacherSetup::lesson($this->learner(), 15);
        $session = OpenAiRealtime::session($setup);

        $this->assertSame($setup['systemInstruction']['parts'][0]['text'], $session['instructions']);
        $this->assertCount(1, $setup['tools'][0]['functionDeclarations']);
        $this->assertCount(1, $session['tools']);
    }
}
