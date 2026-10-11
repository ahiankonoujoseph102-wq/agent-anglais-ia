<?php

namespace Tests\Feature\Program;

use App\Enums\Goal;
use App\Enums\Level;
use App\Enums\ProgramLessonStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Curriculum\Curriculum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    private function subscriber(?Level $level = Level::A2): User
    {
        $user = User::factory()->create(['name' => 'Akossiwa']);
        $user->level = $level;
        $user->save();
        Subscription::factory()->for($user)->create();

        return $user;
    }

    /**
     * Les 4 écrans du questionnaire, dans l'ordre.
     */
    private function answerQuestionnaire(User $user, array $overrides = []): void
    {
        $steps = [
            1 => ['goal' => 'business'],
            2 => ['occupation' => 'Commerçante, importatrice de tissus', 'interests' => 'musique, cuisine'],
            3 => ['study_days' => [2, 4, 6], 'study_time' => '19:30'],
            4 => ['difficulties' => ['pronunciation', 'listening']],
        ];

        foreach ($steps as $step => $answers) {
            $this->actingAs($user)
                ->post(route('program.questionnaire.store', $step), array_merge($answers, $overrides[$step] ?? []))
                ->assertRedirect($step < 4 ? route('program.questionnaire', $step + 1) : route('program.show'));
        }
    }

    public function test_questionnaire_requires_an_active_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('program.questionnaire', 1))->assertRedirect(route('payment.plans'));
        $this->actingAs($user)->get('/programme')->assertRedirect(route('program.questionnaire', 1));
    }

    public function test_four_screens_then_the_program_is_built(): void
    {
        $this->freezeSecond();
        $user = $this->subscriber();

        $this->actingAs($user)->get(route('program.questionnaire', 1))->assertOk()->assertSee('Pourquoi apprenez-vous l\'anglais', false);
        // On ne saute pas d'écran.
        $this->actingAs($user)->get(route('program.questionnaire', 3))->assertRedirect(route('program.questionnaire', 1));
        // Chaque écran est validé.
        $this->actingAs($user)->post(route('program.questionnaire.store', 1), ['goal' => 'football'])->assertSessionHasErrors('goal');

        $this->answerQuestionnaire($user);

        $program = $user->activeProgram()->sole();
        $this->assertSame(Level::A2, $program->level);
        $this->assertSame(Goal::Business, $program->goal);
        $this->assertSame('Commerçante, importatrice de tissus', $program->occupation);
        $this->assertSame([2, 4, 6], $program->study_days);
        $this->assertSame('19:30', $program->study_time);
        $this->assertSame(['pronunciation', 'listening'], $program->difficulties);

        // 12 unités × 3 leçons, dans l'ordre du programme officiel.
        $lessons = $program->programLessons()->get();
        $this->assertCount(36, $lessons);
        $this->assertSame(range(1, 36), $lessons->pluck('position')->all());
        $this->assertSame(
            array_merge(...array_map(fn ($unit) => [$unit, $unit, $unit], range(1, 12))),
            $lessons->pluck('unit_number')->all(),
        );
        $this->assertSame([1, 2, 3], $lessons->where('unit_number', 1)->pluck('lesson_number')->values()->all());

        // Réparties sur les jours choisis (mardi, jeudi, samedi), une leçon par jour, dans l'ordre.
        $dates = $lessons->pluck('scheduled_on');
        $this->assertTrue($dates->every(fn ($date) => in_array($date->dayOfWeekIso, [2, 4, 6], true)));
        $this->assertCount(36, $dates->unique(fn ($date) => $date->toDateString()));
        $this->assertEquals($dates->sort()->values(), $dates->values());

        // Texte type quand le modèle n'est pas disponible (pas de clé en test).
        $this->assertStringContainsString('Akossiwa', $program->content['intro']);

        $user = $user->fresh();
        $this->actingAs($user)->get('/programme')
            ->assertOk()
            ->assertSee('Mon programme A2')
            ->assertSee(Curriculum::unit(Level::A2, 1)->title)
            ->assertSee('0 %')
            ->assertSee('mardi, jeudi et samedi');

        // Le questionnaire ne se refait pas tant que le programme est actif.
        $this->actingAs($user)->get(route('program.questionnaire', 1))->assertRedirect(route('program.show'));
    }

    public function test_situations_linked_to_goal_and_job_come_first(): void
    {
        $user = $this->subscriber(Level::A1);
        $this->answerQuestionnaire($user);

        $program = $user->activeProgram()->sole();

        // Unité 1 du A1 : la situation « Un visiteur du Ghana » (commerce) passe en premier pour une commerçante.
        $first = $program->programLessons()->where('unit_number', 1)->orderBy('lesson_number')->first();
        $first->setRelation('program', $program);

        $this->assertSame('Un visiteur du Ghana', $first->situation()->title);
        $this->assertTrue($first->highlighted);

        // Chaque unité garde ses 3 situations, chacune une fois.
        foreach (range(1, 12) as $unit) {
            $this->assertEqualsCanonicalizing([0, 1, 2], $program->programLessons()->where('unit_number', $unit)->pluck('situation_index')->all());
        }
    }

    public function test_learner_without_level_starts_at_a1_and_gemini_writes_the_introduction(): void
    {
        config(['services.gemini.key' => 'cle-test']);
        Http::fake(['*/v1beta/interactions' => Http::response([
            'status' => 'completed',
            'steps' => [
                ['type' => 'user_input', 'content' => [['type' => 'text', 'text' => '…']]],
                ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'Bienvenue Akossiwa, votre programme est prêt.']]],
            ],
        ])]);

        $user = $this->subscriber(null);
        $this->answerQuestionnaire($user, [1 => ['goal' => 'travel']]);

        $program = $user->activeProgram()->sole();
        $this->assertSame(Level::A1, $program->level);
        $this->assertSame('Bienvenue Akossiwa, votre programme est prêt.', $program->content['intro']);

        Http::assertSent(fn (Request $request) => $request['model'] === 'gemini-3.5-flash-lite'
            && $request['store'] === false
            && $request->hasHeader('x-goog-api-key', 'cle-test')
            && str_contains($request['input'], 'Akossiwa'));
    }

    public function test_lessons_are_blocked_until_the_program_is_built(): void
    {
        $user = $this->subscriber();

        $this->actingAs($user)->get('/seance')->assertRedirect(route('program.questionnaire', 1));
        $this->actingAs($user)->postJson('/seance/connexion')->assertStatus(409);

        $this->answerQuestionnaire($user);

        $this->assertSame(ProgramLessonStatus::Pending, $user->activeProgram()->sole()->currentLesson->status);
    }
}
