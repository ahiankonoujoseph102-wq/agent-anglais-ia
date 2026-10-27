<?php

namespace Tests\Feature\Program;

use App\Enums\Goal;
use App\Enums\Level;
use App\Enums\Profile;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Curriculum\Curriculum;
use App\Support\Curriculum\LessonContext;
use App\Support\Curriculum\ProgramBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Profils (étudiant, enseignant…) et objectifs ajoutés au questionnaire (oser parler, étudier ou voyager à
 * l'étranger, entretien, exposé, clients) ; les anciens objectifs restent ; les programmes déjà construits
 * ne changent pas ; Teacher Joe adapte la pratique et sait jouer l'examinateur d'un entretien.
 */
class SurveyGoalsTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    private const NEW_GOALS = [
        'study_abroad' => "Étudier ou voyager à l'étranger",
        'interview' => 'Préparer un entretien (bourse, admission, visa, stage, embauche)',
        'presentation' => 'Faire un exposé ou une soutenance en anglais',
        'clients' => 'Parler avec des clients ou partenaires',
        'confidence' => 'Avoir confiance et oser parler',
        'media' => 'Comprendre les films, les séries et les chansons',
    ];

    private function subscriber(): User
    {
        $user = User::factory()->create(['name' => 'Edem']);
        $user->level = Level::A2;
        $user->save();
        Subscription::factory()->for($user)->create();

        return $user;
    }

    public function test_the_former_goals_stay_and_the_new_ones_are_offered(): void
    {
        $page = $this->actingAs($this->subscriber())->get(route('program.questionnaire', 1))->assertOk();

        foreach (['Le travail', 'Les études', 'Les voyages', 'Les affaires', 'Un examen', 'La conversation courante', ...array_values(self::NEW_GOALS)] as $label) {
            $page->assertSee($label);
        }

        foreach (self::NEW_GOALS as $value => $label) {
            $this->assertSame($label, Goal::from($value)->label());
            $this->assertNotSame([], array_intersect(Goal::from($value)->tags(), Curriculum::TAGS), $value);
        }
    }

    public function test_the_student_and_teacher_profiles_are_offered_and_optional(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->post(route('program.questionnaire.store', 1), ['goal' => 'interview']);

        $this->get(route('program.questionnaire', 2))->assertOk()
            ->assertSee('Vous êtes… (facultatif)')
            ->assertSee('Étudiant(e)')
            ->assertSee('Enseignant(e)');

        // Facultatif, mais une valeur inconnue est refusée.
        $this->post(route('program.questionnaire.store', 2), ['profile' => 'astronaute', 'occupation' => 'Étudiant'])->assertSessionHasErrors('profile');
        $this->post(route('program.questionnaire.store', 2), ['occupation' => 'Étudiant en droit'])->assertRedirect(route('program.questionnaire', 3));
    }

    public function test_a_new_goal_and_a_profile_are_recorded_and_shape_the_programme(): void
    {
        $user = $this->subscriber();

        foreach ([
            1 => ['goal' => 'interview'],
            2 => ['profile' => 'student', 'occupation' => 'En master de gestion', 'interests' => ''],
            3 => ['study_days' => [1, 3, 5]],
            4 => ['difficulties' => ['speaking']],
        ] as $step => $answers) {
            $this->actingAs($user)->post(route('program.questionnaire.store', $step), $answers);
        }

        $program = $user->activeProgram()->sole();
        $this->assertSame(Goal::Interview, $program->goal);
        $this->assertSame(Profile::Student, $program->profile);

        // Les situations d'entretien (thème « exam » ou « work ») passent en premier dans les unités qui en ont.
        $highlighted = $program->programLessons()->with('program')->where('highlighted', true)->get();
        $this->assertNotEmpty($highlighted);
        foreach ($highlighted->take(5) as $lesson) {
            $tags = $lesson->situation()->tags;
            $this->assertNotSame([], array_intersect($tags, ['exam', 'work', 'studies']), $lesson->situation()->title);
        }
    }

    public function test_the_profile_adds_its_themes(): void
    {
        $this->assertContains('education', ProgramBuilder::profileTags('enseignante'));
        $this->assertSame(['education'], Profile::Teacher->tags());
        $this->assertSame(['studies'], Profile::Student->tags());
    }

    public function test_existing_programmes_keep_their_answers(): void
    {
        $user = $this->subscriber();
        $program = $this->buildProgram($user, null, ['goal' => 'travel']);

        $this->assertSame(Goal::Travel, $program->fresh()->goal);
        $this->assertNull($program->fresh()->profile);
    }

    public function test_the_interview_goal_turns_the_teacher_into_an_examiner(): void
    {
        config(['services.gemini.key' => 'cle-test']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton'])]);
        $user = $this->subscriber();
        $this->buildProgram($user, null, ['goal' => 'interview', 'profile' => 'student', 'occupation' => 'Étudiante en droit']);

        $this->actingAs($user->fresh())->postJson('/seance/connexion')->assertOk();

        Http::assertSent(function (Request $request) {
            $text = $request['bidiGenerateContentSetup']['systemInstruction']['parts'][0]['text'] ?? '';

            return str_contains($text, 'Main goal: interview')
                && str_contains($text, 'Profile: student')
                && str_contains($text, 'INTERVIEW MODE')
                && str_contains($text, 'EXAMINER')
                && str_contains($text, 'ask ONE interview question at a time')
                && str_contains($text, 'WAIT for the full answer')
                && str_contains($text, 'suggest a better, natural way to answer in American English')
                && str_contains($text, 'never promise a scholarship, an admission, a visa or a job');
        });
    }

    public function test_every_new_goal_has_its_own_practice_and_the_site_guide_knows_them(): void
    {
        $guide = file_get_contents(resource_path('teacher/site-guide.md'));

        foreach (self::NEW_GOALS as $value => $label) {
            $this->assertNotEmpty(config("teacher.goal_focus.{$value}"), $value);
            $this->assertStringContainsString('« '.$label.' »', $guide);
        }

        foreach (['« Étudiant(e) »', '« Enseignant(e) »', 'jouer l\'examinateur', 'les questions une par une'] as $text) {
            $this->assertStringContainsString($text, $guide);
        }

        foreach (['TOEFL', 'IELTS', 'bourse garantie'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $guide);
            $this->assertStringNotContainsStringIgnoringCase($forbidden, json_encode(config('teacher')));
        }
    }

    public function test_up_to_two_other_goals_are_recorded_and_shape_the_programme_and_the_lesson(): void
    {
        $user = $this->subscriber();

        $page = $this->actingAs($user)->get(route('program.questionnaire', 1))->assertOk()
            ->assertSeeText('Vous avez d\'autres raisons ? Ajoutez-en deux au plus (facultatif)')
            ->assertSee('name="other_goals[]"', false);

        // Trois autres objectifs : refusé ; l'objectif principal répété : refusé.
        $this->post(route('program.questionnaire.store', 1), ['goal' => 'work', 'other_goals' => ['clients', 'media', 'travel']])->assertSessionHasErrors('other_goals');
        $this->post(route('program.questionnaire.store', 1), ['goal' => 'work', 'other_goals' => ['work']])->assertSessionHasErrors('other_goals.0');

        foreach ([
            1 => ['goal' => 'work', 'other_goals' => ['clients', 'media']],
            2 => ['occupation' => 'Comptable', 'interests' => ''],
            3 => ['study_days' => [1, 3, 5]],
            4 => ['difficulties' => ['speaking']],
        ] as $step => $answers) {
            $this->post(route('program.questionnaire.store', $step), $answers);
        }

        $program = $user->activeProgram()->sole();
        $this->assertSame(Goal::Work, $program->goal);
        $this->assertSame([Goal::Clients, Goal::Media], $program->otherGoals());

        // Les autres objectifs comptent comme le métier (1 point), jamais avant l'objectif principal (2 points).
        $unit = array_values(Curriculum::units(Level::A2))[0];
        $order = ProgramBuilder::orderSituations($unit, Goal::Work, Goal::Clients->tags());
        $this->assertCount(3, $order);

        // Le professeur les connaît, et « Modifier mes objectifs » les reprend.
        $this->assertStringContainsString('- Other goals (for examples and open questions when they fit): Parler avec des clients ou partenaires, Comprendre les films, les séries et les chansons', LessonContext::describe($user->fresh(), $program->fresh(), $program->currentLesson()->firstOrFail(), null, collect()));
        $this->get(route('program.answers.edit', [$program, 1]))->assertOk()
            ->assertSee('value="clients" checked', false);
    }

    public function test_programmes_without_other_goals_and_unknown_values_are_safe(): void
    {
        $user = $this->subscriber();
        $program = $this->buildProgram($user, null, ['goal' => 'travel']);
        $this->assertSame([], $program->fresh()->otherGoals());

        $this->assertSame([Goal::Media], ProgramBuilder::otherGoals(['other_goals' => ['travel', 'inconnu', 'media', 'media']], Goal::Travel));
    }
}
