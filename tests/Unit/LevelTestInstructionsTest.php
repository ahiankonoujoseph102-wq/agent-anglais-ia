<?php

namespace Tests\Unit;

use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\TeacherSetup;
use App\Support\Live\OpenAiRealtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bilan de fin de niveau : il décide de l'attestation. Un vrai test (pas une leçon), du facile au difficile,
 * en anglais, un résultat juste (jamais donné pour faire plaisir) et un avis précis. Mêmes consignes chez les deux fournisseurs.
 */
class LevelTestInstructionsTest extends TestCase
{
    use RefreshDatabase;

    private function levelTestSetup(): array
    {
        $user = new User(['name' => 'Afi']);
        $user->level = Level::A2;

        return TeacherSetup::levelTest($user, Level::A2, 10);
    }

    public function test_the_level_test_is_a_fair_test_in_english_from_easy_to_harder(): void
    {
        $text = $this->levelTestSetup()['systemInstruction']['parts'][0]['text'];

        $this->assertStringContainsString('The test is IN ENGLISH; French only through WHEN THE LEARNER DOES NOT UNDERSTAND, and every help counts in your decision.', $text);
        $this->assertStringContainsString('FROM EASY TO HARDER: start with the first units, then go to the later ones.', $text);
        $this->assertStringContainsString('A TEST, NOT A LESSON (this overrides the corrections of ACTIVE LISTENING)', $text);
        $this->assertStringContainsString('THE TRUE RESULT, never given to please: judge only what you really heard.', $text);
        $this->assertStringContainsString('They do not pass if they needed French or your help for most answers.', $text);
        $this->assertStringContainsString('each with an example you really heard', $text);
        // Le personnage commun (cours en anglais, échelle de compréhension) reste là.
        $this->assertStringContainsString('THE LESSON IS IN ENGLISH', $text);
        $this->assertStringContainsString('WHEN THE LEARNER DOES NOT UNDERSTAND', $text);
    }

    public function test_the_function_asks_for_a_precise_feedback_and_the_units_really_heard(): void
    {
        $setup = $this->levelTestSetup();
        $function = $setup['tools'][0]['functionDeclarations'][0];

        $this->assertSame(TeacherSetup::RECORD_LEVEL_TEST_RESULT, $function['name']);
        $this->assertStringContainsString('"vous"', $function['parameters']['properties']['feedback']['description']);
        $this->assertStringContainsString('where the difficulties were heard', $function['parameters']['properties']['units_to_review']['description']);
        $this->assertSame(['passed', 'feedback'], $function['parameters']['required']);
    }

    public function test_openai_receives_exactly_the_same_level_test_instructions(): void
    {
        config(['services.openai.realtime_model' => 'gpt-realtime-2.1-mini']);
        $setup = $this->levelTestSetup();

        $this->assertSame($setup['systemInstruction']['parts'][0]['text'], OpenAiRealtime::session($setup)['instructions']);
    }
}
