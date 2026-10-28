<?php

namespace Tests\Unit;

use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\TeacherSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Débit de Teacher Joe : UNE règle (config teacher.pace), donnée à toutes les conversations, avec la ligne du niveau
 * de l'élève (niveau inconnu pendant l'évaluation : commencer lentement, puis s'adapter).
 */
class TeacherPaceTest extends TestCase
{
    use RefreshDatabase;

    private function learner(?Level $level): User
    {
        $user = new User(['name' => 'Afi']);
        $user->level = $level;

        return $user;
    }

    /**
     * @return array<string, array{0: string, 1: ?Level, 2: string}>
     */
    public static function conversations(): array
    {
        return [
            'évaluation, niveau inconnu' => ['assessment', null, 'unknown'],
            'évaluation, ancien niveau ignoré' => ['assessment', Level::C1, 'unknown'],
            'séance du jour A1' => ['lesson', Level::A1, 'A1'],
            'séance du jour A2' => ['lesson', Level::A2, 'A2'],
            'séance du jour B1' => ['lesson', Level::B1, 'B1'],
            'séance du jour B2' => ['lesson', Level::B2, 'B2'],
            'séance du jour C1' => ['lesson', Level::C1, 'C1'],
            'séance du jour C2' => ['lesson', Level::C2, 'C2'],
            'Mes cours A2' => ['course', Level::A2, 'A2'],
            'bilan de niveau B1' => ['level_test', Level::B1, 'B1'],
        ];
    }

    #[DataProvider('conversations')]
    public function test_every_conversation_gets_the_pace_of_the_learner_level(string $kind, ?Level $level, string $pace): void
    {
        $text = TeacherSetup::instructions($kind, $this->learner($level), 10);

        $this->assertSame(1, substr_count($text, 'SPEAKING PACE (the only pace rule):'));
        $this->assertStringContainsString("SPEAKING PACE (the only pace rule):\n".config("teacher.pace.levels.{$pace}"), $text);
        $this->assertStringNotContainsString(':level_pace', $text);
        $this->assertSame($pace, TeacherSetup::paceLevel($kind, $this->learner($level)));

        // Juste après le personnage, avant le déroulé de l'activité.
        $this->assertLessThan(strpos($text, 'American English') ?: PHP_INT_MAX, strpos($text, 'SPEAKING PACE'));
    }

    public function test_the_pace_by_level_is_very_slow_then_slow_to_moderate_then_natural(): void
    {
        foreach (['A1', 'A2'] as $level) {
            $this->assertStringContainsString('very slow and very articulated', config("teacher.pace.levels.{$level}"));
            $this->assertStringContainsString('A short pause after each key word and after each model sentence', config("teacher.pace.levels.{$level}"));
        }

        foreach (['B1', 'B2'] as $level) {
            $this->assertStringContainsString('slow to moderate', config("teacher.pace.levels.{$level}"));
        }

        foreach (['C1', 'C2'] as $level) {
            $this->assertStringContainsString('natural', config("teacher.pace.levels.{$level}"));
        }

        $this->assertStringContainsString('start SLOWLY', config('teacher.pace.levels.unknown'));
        $this->assertStringContainsString('then adapt at once to their first answers', config('teacher.pace.levels.unknown'));
    }

    public function test_key_words_sentences_to_repeat_and_slowing_down_until_the_end(): void
    {
        $rules = (string) config('teacher.pace.rules');

        $this->assertStringContainsString('KEY WORD of the day: say it ALONE, slowly; then in a short sentence; then alone again.', $rules);
        $this->assertStringContainsString('SENTENCE TO REPEAT: say it slowly TWICE, then wait.', $rules);
        $this->assertStringContainsString('« je ne comprends pas », « plus lentement », « répétez »', $rules);
        $this->assertStringContainsString('"I don\'t understand", "Slowly, please"', $rules);
        $this->assertStringContainsString('or answers something unrelated: slow down at once', $rules);
        $this->assertStringContainsString('KEEP this slower pace until the end', $rules);
        // Pauses courtes : une longue fait croire à l'élève que c'est son tour (et la sécurité du micro tient la voix pour finie).
        $this->assertStringContainsString('one to two seconds at most', $rules);
        $this->assertDoesNotMatchRegularExpression('/google|gemini|openai|chatgpt/i', $rules.implode(' ', config('teacher.pace.levels')));
    }

    public function test_no_other_instruction_gives_a_contradictory_pace(): void
    {
        $texts = [
            config('teacher.persona'), config('teacher.assessment'), config('teacher.course'), config('teacher.lesson'),
            ...array_values(config('teacher.level_guidance')), ...array_values(config('teacher.lesson_language')),
        ];

        foreach ($texts as $text) {
            $this->assertDoesNotMatchRegularExpression('/natural from B2|(?<!AND )PACE:|at a natural pace|at a moderate pace|moderate for B1/', (string) $text);
        }

        $this->assertStringContainsString('slow down clearly (see SPEAKING PACE)', config('teacher.persona'));
    }
}
