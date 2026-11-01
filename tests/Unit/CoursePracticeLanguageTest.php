<?php

namespace Tests\Unit;

use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\TeacherSetup;
use App\Support\Live\OpenAiRealtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Mes cours » (décision du fondateur, 1er novembre 2026) : la leçon de l'école est expliquée en français à tous
 * les niveaux ; à partir de B1, la pratique et les exercices se font en anglais d'abord ; en A1 et A2, le français
 * reste le filet de sécurité. Rien ne change dans les autres conversations.
 */
class CoursePracticeLanguageTest extends TestCase
{
    use RefreshDatabase;

    private const BEGINNER = 'PRACTICE AND EXERCISES (beginner level)';

    private const CONFIDENT = 'PRACTICE AND EXERCISES (intermediate level and above)';

    private function courseText(?Level $level): string
    {
        $user = new User(['name' => 'Ama']);
        $user->level = $level;

        return TeacherSetup::course($user, 10, 'Lesson: the present simple.')['systemInstruction']['parts'][0]['text'];
    }

    public function test_beginners_keep_french_as_a_safety_net_for_practice_and_exercises(): void
    {
        foreach ([null, Level::A1, Level::A2] as $level) {
            $text = $this->courseText($level);

            $this->assertStringContainsString(self::BEGINNER, $text, $level?->value ?? 'inconnu');
            $this->assertStringNotContainsString(self::CONFIDENT, $text);
            $this->assertStringContainsString('give the instruction of an exercise, your hints and the reason of a correction in simple French', $text);
        }
    }

    public function test_from_b1_practice_and_exercises_are_in_english_first(): void
    {
        foreach ([Level::B1, Level::B2, Level::C1, Level::C2] as $level) {
            $text = $this->courseText($level);

            $this->assertStringContainsString(self::CONFIDENT, $text, $level->value);
            $this->assertStringNotContainsString(self::BEGINNER, $text);
            $this->assertStringContainsString('the practice and the exercises happen in ENGLISH FIRST', $text);
            // Le français reste la langue d'explication de la leçon de l'école.
            $this->assertStringContainsString('here French is the MAIN language of EXPLANATION', $text);
            $this->assertStringContainsString('The explanation of the lesson itself and the answers to their questions about it stay in French.', $text);
            $this->assertStringContainsString('(a) Explain what the instruction asks, in the language of PRACTICE AND EXERCISES.', $text);
        }
    }

    public function test_no_marker_is_left_and_other_conversations_do_not_change(): void
    {
        $this->assertStringNotContainsString(':course_practice', $this->courseText(Level::B1));

        $user = new User(['name' => 'Ama']);
        $user->level = Level::B1;

        foreach (['lesson', 'assessment', 'level_test'] as $kind) {
            $text = TeacherSetup::instructions($kind, $user, 10);

            $this->assertStringNotContainsString('PRACTICE AND EXERCISES', $text, $kind);
        }
    }

    public function test_openai_receives_exactly_the_same_course_instructions(): void
    {
        config(['services.openai.realtime_model' => 'gpt-realtime-2.1-mini']);
        $user = new User(['name' => 'Ama']);
        $user->level = Level::B2;
        $setup = TeacherSetup::course($user, 10, 'Lesson: the present simple.');

        $this->assertSame($setup['systemInstruction']['parts'][0]['text'], OpenAiRealtime::session($setup)['instructions']);
    }
}
