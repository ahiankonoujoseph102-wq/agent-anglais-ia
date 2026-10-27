<?php

namespace Tests\Feature\Program;

use App\Enums\Level;
use App\Models\LessonBoard;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Curriculum\Curriculum;
use App\Support\Curriculum\LessonBoards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Piège du jour au tableau : une des difficultés des francophones de l'unité (programme officiel), une par leçon à
 * tour de rôle, ajoutée à la lecture (tableau gardé ou de secours) : aucun appel au modèle texte, aucun tableau refait.
 */
class BoardPitfallTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => null, 'services.openai.key' => null]);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton'])]);
    }

    private function learner(): User
    {
        $user = User::factory()->level(Level::A1)->create(['name' => 'Afi', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('intensif')->create();
        $this->buildProgram($user, Level::A1);

        return $user->fresh();
    }

    public function test_each_lesson_of_a_unit_gets_one_pitfall_of_the_programme_in_turn(): void
    {
        $program = $this->buildProgram(User::factory()->create(), Level::A1);
        $unit = $program->unit(1);

        foreach ($program->programLessons()->where('unit_number', 1)->get() as $lesson) {
            $lesson->setRelation('program', $program);
            $expected = $unit->pitfalls[($lesson->lesson_number - 1) % count($unit->pitfalls)];

            $this->assertSame($expected, LessonBoards::pitfall($lesson));
            $this->assertSame($expected, LessonBoards::for($lesson, $program)['pitfall']);
        }

        // Chaque unité de chaque niveau en a au moins un.
        foreach (Level::cases() as $level) {
            foreach (Curriculum::units($level) as $u) {
                $this->assertNotSame([], $u->pitfalls, "{$level->value} unité {$u->number}");
            }
        }
    }

    public function test_a_stored_board_gets_it_too_without_being_prepared_again(): void
    {
        $user = $this->learner();
        $program = $user->activeProgram()->first();
        $lesson = $program->currentLesson()->first()->setRelation('program', $program);
        LessonBoard::query()->create([
            'key' => LessonBoards::key($lesson, $program), 'level' => 'A1', 'unit_number' => $lesson->unit_number,
            'lesson_number' => $lesson->lesson_number, 'situation_index' => $lesson->situation_index, 'goal' => 'work',
            'content' => LessonBoardTest::board(), 'provider' => 'gemini',
        ]);

        $board = LessonBoards::for($lesson, $program);

        $this->assertSame('My family', $board['title']);
        $this->assertSame(LessonBoards::pitfall($lesson), $board['pitfall']);
        $this->assertSame(1, LessonBoard::count());
    }

    public function test_the_learner_sees_it_on_the_board_and_teacher_joe_teaches_it_by_contrast(): void
    {
        $user = $this->learner();
        $program = $user->activeProgram()->first();
        $pitfall = LessonBoards::pitfall($program->currentLesson()->first()->setRelation('program', $program));

        $this->actingAs($user)->get('/seance')->assertOk()
            ->assertSee('Attention, piège')
            ->assertSee($pitfall);

        config(['services.gemini.key' => 'cle-secrete-du-serveur']);
        $text = $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json('setup.systemInstruction.parts.0.text');

        $this->assertStringContainsString('- Pitfall of the day (in French, from the programme; teach it by contrast): '.$pitfall, $text);
        $this->assertStringContainsString('also on the screen under « Attention, piège »', $text);
    }
}
