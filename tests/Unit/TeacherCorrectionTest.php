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
            $this->assertStringContainsString('1) A clue first, never the answer', $text, $kind);
            $this->assertStringContainsString('Not found after ONE try: \'We say "…".\'', $text, $kind);
            $this->assertStringContainsString('3) WHY in one short sentence', $text, $kind);
            $this->assertStringContainsString('4) The learner says the WHOLE sentence again, correctly.', $text, $kind);
            $this->assertStringContainsString('The goal is to be understood, never a native accent.', $text, $kind);
            // Jamais complaisant (B5).
            $this->assertStringContainsString('Never let a wrong form pass as right to please the learner. If they insist, stay warm and accurate', $text, $kind);
            // Pendant la parole libre : pas d'interruption pour les petites fautes, retour à la fin de la conversation.
            $this->assertStringContainsString('DURING FREE CONVERSATION (STEP 3): let the learner speak, do NOT interrupt for small mistakes', $text, $kind);
            // Vérifier par une question qui fait utiliser (B1), jamais par « C'est clair ? ».
            $this->assertStringContainsString('ask ONE tiny question that makes them USE or CHOOSE it', $text, $kind);
            // Une seule règle de correction : la règle générale renvoie à HOW TO CORRECT.
            $this->assertStringContainsString('Mistakes: follow HOW TO CORRECT when it is given below', $text, $kind);
            $this->assertSame(1, substr_count($text, 'HOW TO CORRECT (daily lesson'), $kind);
        }
    }

    public function test_the_daily_lesson_teaches_pitfalls_by_contrast_and_asks_to_say_it_again_better(): void
    {
        $text = TeacherSetup::instructions('lesson', $this->learner(), 15);

        $this->assertStringContainsString('PITFALL OF THE DAY', $text);
        $this->assertStringContainsString('by CONTRAST', $text);
        $this->assertStringContainsString('the same contrast at most twice more in the lesson', $text);
        // Retour sur la langue à la fin de la conversation : une réussite, puis une ou deux erreurs expliquées, redites mieux.
        $this->assertStringContainsString('LANGUAGE FEEDBACK (the last minute of this step, before the summary): first ONE thing they said well', $text);
        $this->assertStringContainsString('what they said, the right form, WHY, and they say it again once, better', $text);
        $this->assertStringContainsString('ONE checking question (HOW YOU TEACH), never "Is it clear?"', $text);
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
