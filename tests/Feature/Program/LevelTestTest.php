<?php

namespace Tests\Feature\Program;

use App\Enums\Level;
use App\Enums\LevelTestStatus;
use App\Enums\ProgramLessonStatus;
use App\Enums\ProgramStatus;
use App\Models\Attestation;
use App\Models\Program;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

class LevelTestTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-test']);
        Http::fake([
            '*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton']),
            // Modèle texte indisponible : le programme suivant utilise le texte de présentation type.
            '*/interactions' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503),
        ]);
        $this->freezeSecond();
    }

    /**
     * Apprenant qui a terminé les 12 unités de son niveau.
     */
    private function learnerReadyForTest(Level $level = Level::A2): User
    {
        $user = User::factory()->create(['name' => 'Edem Kossi']);
        $user->level = $level;
        $user->save();
        Subscription::factory()->for($user)->create();

        $program = $this->buildProgram($user, $level, ['goal' => 'studies', 'occupation' => 'Étudiant en droit', 'study_days' => [1, 3, 5]]);
        $program->programLessons()->update(['status' => ProgramLessonStatus::Completed, 'completed_at' => now()]);
        $program->update(['status' => ProgramStatus::LevelTestDue]);

        return $user->fresh();
    }

    public function test_level_test_is_only_open_when_the_twelve_units_are_done(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->for($user)->create();
        $this->buildProgram($user);

        $this->actingAs($user)->get('/bilan-niveau')->assertRedirect('/espace');
        $this->actingAs($user)->postJson('/bilan-niveau/connexion')->assertStatus(409);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'auth_tokens'));
    }

    public function test_test_instructions_list_the_units_of_the_level(): void
    {
        $user = $this->learnerReadyForTest();

        $this->actingAs($user)->get('/bilan-niveau')->assertOk()->assertSee('Bilan de fin de niveau A2');
        $this->actingAs($user)->postJson('/bilan-niveau/connexion')
            ->assertOk()
            ->assertJsonPath('remaining_seconds', 600);

        Http::assertSent(function (Request $request) {
            $setup = $request['bidiGenerateContentSetup'];
            $text = $setup['systemInstruction']['parts'][0]['text'];

            return str_contains($text, 'END-OF-LEVEL ORAL TEST for level A2')
                && str_contains($text, '12. Projets et prévisions')
                && $setup['tools'][0]['functionDeclarations'][0]['name'] === 'record_level_test_result';
        });
    }

    public function test_passed_test_moves_to_next_level_rebuilds_program_and_issues_attestation(): void
    {
        $user = $this->learnerReadyForTest(Level::A2);
        $oldProgram = $user->activeProgram;

        $this->actingAs($user)->postJson('/bilan-niveau/connexion')->assertOk();
        $this->travel(8)->minutes();

        $response = $this->actingAs($user)->postJson('/bilan-niveau/resultat', [
            'passed' => true,
            'feedback' => 'Très bonne aisance, vocabulaire riche.',
            'units_to_review' => [],
        ])->assertOk()
            ->assertJsonPath('passed', true)
            ->assertJsonPath('next_level', 'B1');

        $this->actingAs($user)->postJson('/bilan-niveau/fin')->assertOk();

        $user->refresh();
        $this->assertSame(Level::B1, $user->level);

        $test = $user->levelTests()->sole();
        $this->assertSame(LevelTestStatus::Passed, $test->status);
        $this->assertSame(480, $test->used_seconds);

        $this->assertSame(ProgramStatus::Completed, $oldProgram->fresh()->status);
        $this->assertFalse($oldProgram->fresh()->is_active);

        // Nouveau programme B1, mêmes réponses au questionnaire, reparti de zéro.
        $newProgram = $user->activeProgram()->sole();
        $this->assertSame(Level::B1, $newProgram->level);
        $this->assertSame('Étudiant en droit', $newProgram->occupation);
        $this->assertSame([1, 3, 5], $newProgram->study_days);
        $this->assertSame(0, $newProgram->progressPercent());
        $this->assertSame(36, $newProgram->programLessons()->count());
        $this->assertStringContainsString('Bienvenue dans votre programme B1, Edem Kossi', $newProgram->content['intro']);

        $attestation = $user->attestations()->sole();
        $this->assertSame(Level::A2, $attestation->level);
        $this->assertMatchesRegularExpression('/^[A-Z]{2}-A2-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $attestation->code);
        $response->assertJsonPath('attestation_url', route('attestation.download', $attestation));

        // Le résultat ne peut pas être envoyé deux fois.
        $this->actingAs($user)->postJson('/bilan-niveau/resultat', ['passed' => false, 'feedback' => 'x', 'units_to_review' => [1]])->assertConflict();
    }

    public function test_failed_test_reopens_the_units_to_review(): void
    {
        $user = $this->learnerReadyForTest(Level::B1);
        $program = $user->activeProgram;

        $this->actingAs($user)->postJson('/bilan-niveau/connexion')->assertOk();

        // Pas de résultat accepté avant deux minutes de bilan.
        $this->actingAs($user)->postJson('/bilan-niveau/resultat', ['passed' => true, 'feedback' => 'Trop rapide.'])->assertConflict();
        $this->travel(5)->minutes();

        // Le professeur doit indiquer au moins une unité à reprendre.
        $this->actingAs($user)->postJson('/bilan-niveau/resultat', ['passed' => false, 'feedback' => 'Pas encore.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('units_to_review');

        $this->actingAs($user)->postJson('/bilan-niveau/resultat', [
            'passed' => false,
            'feedback' => 'La grammaire du passé est encore fragile.',
            'units_to_review' => [2, 6],
        ])->assertOk()
            ->assertJsonPath('passed', false)
            ->assertJsonPath('units_to_review', [2, 6]);

        $user->refresh();
        $this->assertSame(Level::B1, $user->level);
        $this->assertSame(0, $user->attestations()->count());

        $program->refresh();
        $this->assertSame(ProgramStatus::Active, $program->status);
        $this->assertSame(
            [2, 6],
            $program->programLessons()->where('status', ProgramLessonStatus::Pending)->pluck('unit_number')->unique()->values()->all(),
        );
        $this->assertSame(6, $program->programLessons()->where('status', ProgramLessonStatus::Pending)->count());
        $this->assertTrue($program->programLessons()->where('status', ProgramLessonStatus::Pending)->get()->every(fn ($lesson) => $lesson->scheduled_on->isFuture()));

        // Les séances reprennent sur les unités à revoir, et l'espace l'explique.
        $this->actingAs($user->fresh())->get('/espace')
            ->assertSee('La grammaire du passé est encore fragile.')
            ->assertSee('Unités à reprendre :')
            ->assertSee('Unité 2');
    }

    public function test_attestation_pdf_is_downloadable_by_its_owner_only_and_verifiable(): void
    {
        $user = $this->learnerReadyForTest(Level::B2);
        $this->actingAs($user)->postJson('/bilan-niveau/connexion')->assertOk();
        $this->travel(5)->minutes();
        $this->actingAs($user)->postJson('/bilan-niveau/resultat', ['passed' => true, 'feedback' => 'Excellent.'])->assertOk();

        $attestation = Attestation::query()->sole();

        $pdf = $this->actingAs($user->fresh())->get(route('attestation.download', $attestation))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="attestation-anglais-B2-edem-kossi.pdf"')
            ->getContent();

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
        $this->assertStringContainsString('ATTESTATION DE NIVEAU EN ANGLAIS', $pdf);
        $this->assertStringContainsString('Edem Kossi', $pdf);
        $this->assertStringContainsString($attestation->code, $pdf);
        // Toujours « attestation », jamais « certificat » ni « diplôme ».
        $this->assertStringNotContainsStringIgnoringCase('certificat', $pdf);
        $this->assertStringNotContainsStringIgnoringCase(mb_convert_encoding('diplôme', 'Windows-1252', 'UTF-8'), $pdf);

        $this->actingAs($user->fresh())->get('/espace')
            ->assertSee('Mes attestations de niveau')
            ->assertSee('Télécharger l\'attestation', false)
            ->assertDontSee('certificat')
            ->assertDontSee('diplôme');

        $this->actingAs(User::factory()->create())->get(route('attestation.download', $attestation))->assertNotFound();

        $this->get(route('attestation.verify', $attestation->code))
            ->assertOk()
            ->assertSee('Attestation authentique')
            ->assertSee('Edem Kossi')
            ->assertSee('B2');
        $this->get(route('attestation.verify', 'FAUX-CODE'))->assertSee('Aucune attestation');
    }

    public function test_after_c2_the_learner_gets_the_attestation_and_keeps_practising(): void
    {
        $user = $this->learnerReadyForTest(Level::C2);

        $this->actingAs($user)->postJson('/bilan-niveau/connexion')->assertOk();
        $this->travel(5)->minutes();
        $this->actingAs($user)->postJson('/bilan-niveau/resultat', ['passed' => true, 'feedback' => 'Maîtrise remarquable.'])
            ->assertOk()
            ->assertJsonPath('next_level', null);

        $user = $user->fresh();
        $this->assertSame(Level::C2, $user->level);
        $this->assertNull($user->activeProgram);
        $this->assertSame(Level::C2, $user->attestations()->sole()->level);
        $this->assertSame(0, Program::query()->where('is_active', true)->count());

        // Conversation libre : les séances restent possibles.
        $this->actingAs($user)->get('/seance')->assertOk();
    }
}
