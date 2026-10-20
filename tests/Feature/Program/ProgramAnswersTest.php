<?php

namespace Tests\Feature\Program;

use App\Enums\Goal;
use App\Enums\Level;
use App\Enums\Profile;
use App\Enums\ProgramLessonStatus;
use App\Models\Program;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * « Modifier mes objectifs » : le questionnaire pré-rempli, enregistré sans perdre la progression ; ancien
 * programme gardé si le modèle texte échoue ; limite par jour ; programme d'un autre compte refusé ;
 * Teacher Joe utilise les nouvelles réponses dès la séance suivante.
 */
class ProgramAnswersTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    private const NEW_ANSWERS = [
        1 => ['goal' => 'interview'],
        2 => ['profile' => 'student', 'occupation' => 'Étudiante en master de droit', 'interests' => 'débats'],
        3 => ['study_days' => [1, 3, 5], 'study_time' => '20:00'],
        4 => ['difficulties' => ['speaking', 'vocabulary']],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Aucun fournisseur du modèle texte réglé : la présentation utilise son texte type.
        config(['services.gemini.key' => null, 'services.openai.key' => null]);
        $this->freezeSecond();
    }

    private function learner(): User
    {
        $user = User::factory()->create(['name' => 'Afi']);
        $user->level = Level::A2;
        $user->save();
        Subscription::factory()->for($user)->create();
        $this->buildProgram($user, null, ['goal' => 'work', 'occupation' => 'Comptable']);

        return $user->fresh();
    }

    /**
     * Les 4 écrans, avec les nouvelles réponses ; renvoie la réponse du dernier envoi.
     */
    private function answer(User $user, Program $program, array $answers = self::NEW_ANSWERS)
    {
        $response = null;

        foreach ($answers as $step => $values) {
            $response = $this->actingAs($user)->post(route('program.answers.update', [$program, $step]), $values);
        }

        return $response;
    }

    public function test_the_button_is_shown_only_to_a_learner_with_a_programme(): void
    {
        $withoutProgramme = User::factory()->create();
        Subscription::factory()->for($withoutProgramme)->create();
        $this->actingAs($withoutProgramme)->get(route('dashboard'))->assertOk()->assertDontSee('Modifier mes objectifs');

        $user = $this->learner();
        $program = $user->activeProgram;
        $url = route('program.answers.edit', [$program, 1]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Modifier mes objectifs')->assertSee($url, false);
        $this->actingAs($user)->get(route('program.show'))->assertOk()->assertSee('Modifier mes objectifs')->assertSee($url, false);
    }

    public function test_the_questionnaire_opens_with_the_current_answers_and_every_option(): void
    {
        $user = $this->learner();
        $program = $user->activeProgram;

        $page = $this->actingAs($user)->get(route('program.answers.edit', [$program, 1]))->assertOk()
            ->assertSee('Modifier mes objectifs')
            ->assertSee('Retour à mon programme, sans rien changer');
        $this->assertMatchesRegularExpression('/value="work"\s+checked/', $page->getContent());

        foreach (Goal::cases() as $goal) {
            $page->assertSee($goal->label());
        }

        $step2 = $this->get(route('program.answers.edit', [$program, 2]))->assertOk()->assertSee('value="Comptable"', false);
        foreach (Profile::cases() as $profile) {
            $step2->assertSee($profile->label());
        }

        $this->get(route('program.answers.edit', [$program, 4]))->assertOk()->assertSee('Enregistrer mes objectifs');

        // Mêmes règles de validation que le questionnaire.
        $this->post(route('program.answers.update', [$program, 1]), ['goal' => 'football'])->assertSessionHasErrors('goal');
        $this->post(route('program.answers.update', [$program, 2]), ['occupation' => ''])->assertSessionHasErrors('occupation');
    }

    public function test_new_answers_are_saved_and_the_progress_is_kept(): void
    {
        $user = $this->learner();
        $program = $user->activeProgram;
        $subscription = $user->activeSubscription;
        $lessons = $program->programLessons()->orderBy('position')->get();

        // Deux leçons faites, une troisième commencée (séance en cours).
        foreach ($lessons->take(2) as $done) {
            $done->update(['status' => ProgramLessonStatus::Completed, 'completed_at' => now()]);
        }
        $current = $lessons[2];
        $session = $user->lessons()->create(['date' => today(), 'allowed_seconds' => 900, 'used_seconds' => 120, 'status' => 'in_progress', 'started_at' => now()->subMinutes(2), 'program_id' => $program->id, 'program_lesson_id' => $current->id]);
        $before = $program->programLessons()->orderBy('position')->get()->keyBy('id');
        $progress = $program->progressPercent();

        $this->answer($user, $program)
            ->assertRedirect(route('program.show'))
            ->assertSessionHas('status', 'Vos objectifs sont enregistrés. Teacher Joe en tiendra compte dès votre prochaine séance.');

        $program->refresh();
        $this->assertSame(Goal::Interview, $program->goal);
        $this->assertSame(Profile::Student, $program->profile);
        $this->assertSame('Étudiante en master de droit', $program->occupation);
        $this->assertSame([1, 3, 5], $program->study_days);
        $this->assertSame(['speaking', 'vocabulary'], $program->difficulties);

        // Même programme, même niveau, même progression, même abonnement, même historique.
        $this->assertSame(1, $user->programs()->count());
        $this->assertTrue($program->is_active);
        $this->assertSame(Level::A2, $program->level);
        $this->assertSame(Level::A2, $user->fresh()->level);
        $this->assertSame($progress, $program->progressPercent());
        $this->assertSame($subscription->id, $user->fresh()->activeSubscription->id);
        $this->assertSame(1, $user->lessons()->count());
        $this->assertSame('in_progress', $session->fresh()->status->value);

        $after = $program->programLessons()->orderBy('position')->get()->keyBy('id');
        $this->assertSame($before->keys()->all(), $after->keys()->all());

        foreach ($before as $id => $lesson) {
            $this->assertSame($lesson->status, $after[$id]->status);
            $this->assertSame($lesson->unit_number, $after[$id]->unit_number);
        }

        // Leçons faites et leçon de la séance en cours : situation inchangée.
        foreach ([$lessons[0]->id, $lessons[1]->id, $current->id] as $id) {
            $this->assertSame($before[$id]->situation_index, $after[$id]->situation_index);
        }

        // Chaque unité garde ses trois situations, sans doublon.
        foreach ($after->groupBy('unit_number') as $unit) {
            $this->assertSame([0, 1, 2], $unit->pluck('situation_index')->sort()->values()->all());
        }

        // Le calendrier suit les nouveaux jours.
        foreach ($after->where('status', ProgramLessonStatus::Pending) as $pending) {
            $this->assertContains($pending->scheduled_on->dayOfWeekIso, [1, 3, 5]);
        }
    }

    public function test_a_text_model_failure_keeps_the_former_programme(): void
    {
        $user = $this->learner();
        $program = $user->activeProgram;
        $before = $program->programLessons()->orderBy('position')->get(['id', 'situation_index', 'scheduled_on'])->toArray();
        $intro = $program->content['intro'];

        // Gemini réglé mais en panne, pas d'OpenAI : la présentation ne peut pas être rédigée.
        config(['services.gemini.key' => 'cle-test']);
        Http::fake(['*' => Http::response(['error' => ['message' => 'panne']], 500)]);

        $this->answer($user, $program)
            ->assertRedirect(route('program.show'))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'Votre programme actuel est gardé, sans aucune perte'));

        $program->refresh();
        $this->assertSame(Goal::Work, $program->goal);
        $this->assertNull($program->profile);
        $this->assertSame('Comptable', $program->occupation);
        $this->assertSame($intro, $program->content['intro']);
        $this->assertSame($before, $program->programLessons()->orderBy('position')->get(['id', 'situation_index', 'scheduled_on'])->toArray());

        // Un échec ne compte pas dans la limite du jour.
        config(['services.gemini.key' => null]);
        $this->answer($user, $program)->assertSessionHas('status', fn (string $status) => str_starts_with($status, 'Vos objectifs sont enregistrés.'));
    }

    public function test_answers_can_be_changed_three_times_a_day(): void
    {
        $user = $this->learner();
        $program = $user->activeProgram;

        foreach (['interview', 'confidence', 'study_abroad'] as $goal) {
            $this->answer($user, $program, [1 => ['goal' => $goal]] + self::NEW_ANSWERS)
                ->assertSessionHas('status', fn (string $status) => str_starts_with($status, 'Vos objectifs sont enregistrés.'));
        }

        $message = 'Vous avez déjà modifié vos objectifs 3 fois aujourd\'hui. Vous pourrez les modifier de nouveau demain.';
        $this->actingAs($user)->get(route('program.answers.edit', [$program, 1]))->assertRedirect(route('program.show'))->assertSessionHas('status', $message);
        $this->post(route('program.answers.update', [$program, 1]), ['goal' => 'clients'])->assertSessionHas('status', $message);
        $this->assertSame(Goal::StudyAbroad, $program->fresh()->goal);

        // Le lendemain, c'est de nouveau possible.
        $this->travelTo(now()->addDay()->startOfDay()->addHour());
        $this->actingAs($user)->get(route('program.answers.edit', [$program, 1]))->assertOk();
    }

    public function test_another_account_cannot_open_or_change_the_programme(): void
    {
        $program = $this->learner()->activeProgram;
        $intruder = $this->learner();

        $this->actingAs($intruder)->get(route('program.answers.edit', [$program, 1]))->assertForbidden();
        $this->actingAs($intruder)->post(route('program.answers.update', [$program, 1]), ['goal' => 'clients'])->assertForbidden();
        $this->assertSame(Goal::Work, $program->fresh()->goal);

        $this->get(route('program.answers.edit', [$program, 1]))->assertForbidden();
        auth()->logout();
        $this->get(route('program.answers.edit', [$program, 1]))->assertRedirect(route('login'));
    }

    public function test_the_teacher_uses_the_new_answers_at_the_next_session(): void
    {
        $user = $this->learner();
        $this->answer($user, $user->activeProgram);

        config(['services.gemini.key' => 'cle-test']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton'])]);
        $this->actingAs($user->fresh())->postJson('/seance/connexion')->assertOk();

        Http::assertSent(function (Request $request) {
            $text = $request['bidiGenerateContentSetup']['systemInstruction']['parts'][0]['text'] ?? '';

            return str_contains($text, 'Main goal: interview')
                && str_contains($text, 'Profile: student')
                && str_contains($text, 'Occupation / situation: Étudiante en master de droit')
                && str_contains($text, 'INTERVIEW MODE')
                && str_contains($text, 'Objectif du programme : préparer un entretien')
                && str_contains($text, 'bouton « Modifier mes objectifs »');
        });
    }
}
