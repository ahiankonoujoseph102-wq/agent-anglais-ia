<?php

namespace Tests\Feature\Live;

use App\Enums\Role;
use App\Models\User;
use App\Support\Gemini\TeacherSetup;
use App\Support\Live\AssessmentVersion;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Nouvelle évaluation derrière l'interrupteur de « Réglages » (désactivée, comptes de test seulement, tout le monde)
 * et case « Compte de test » de la fiche d'un inscrit ; correction de la fin de l'évaluation (bug du 28 octobre 2026 :
 * formules affichées et Teacher Joe muet alors que la séance tournait encore).
 */
class NewAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => Role::Admin])->save();

        return $admin;
    }

    private function setMode(string $mode): void
    {
        SiteSettings::save([AssessmentVersion::SETTING => $mode]);
    }

    private function makeTestAccount(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_test_account' => true])->save();

        return $user;
    }

    public function test_by_default_only_test_accounts_get_the_new_assessment(): void
    {
        $this->assertSame('test', AssessmentVersion::mode());

        $prospect = User::factory()->create();
        $tester = $this->makeTestAccount();

        $this->assertFalse($prospect->fresh()->is_test_account);
        $this->assertFalse(AssessmentVersion::isNewFor($prospect));
        $this->assertTrue(AssessmentVersion::isNewFor($tester));

        $old = TeacherSetup::instructions('assessment', $prospect, 5);
        $new = TeacherSetup::instructions('assessment', $tester, 5);

        // Les prospects gardent l'évaluation actuelle, mot pour mot.
        $this->assertStringContainsString('2. ADAPTIVE QUESTIONS, one at a time', $old);
        $this->assertStringNotContainsString('YOU NOTICE HESITATION', $old);

        // Nouvelle évaluation : accueil humain, hésitation remarquée, français, traduction apprise, vrai niveau.
        $this->assertStringContainsString('ask the learner\'s name ("What\'s your name?") and WAIT', $new);
        $this->assertStringContainsString('Then ask "How are you today?" and WAIT', $new);
        $this->assertStringContainsString('YOU NOTICE HESITATION YOURSELF, without waiting to be asked', $new);
        $this->assertStringContainsString('"I see the question is not clear. Let me say it more simply: …"', $new);
        $this->assertStringContainsString('translate the question into French in one sentence, then ask it again in simple English', $new);
        $this->assertStringContainsString('NEVER go on in English as if nothing happened', $new);
        $this->assertStringContainsString('"Next time, you can say: Can you say it in French, please?"', $new);
        $this->assertStringContainsString('THE TRUE LEVEL, never raised to please', $new);
        $this->assertStringContainsString('even before A1 for now', $new);
        // Même fin : phrase fixe, formules, aucune intelligence artificielle.
        $this->assertStringContainsString('« On se retrouve de l\'autre côté ! »', $new);
        $this->assertStringNotContainsString('artificial intelligence', (string) config('teacher.assessment_new'));
        // Consignes courtes : la nouvelle version n'est pas plus longue que l'actuelle.
        $this->assertLessThanOrEqual(mb_strlen((string) config('teacher.assessment')), mb_strlen((string) config('teacher.assessment_new')));
    }

    public function test_the_switch_disables_or_opens_the_new_assessment_for_everyone(): void
    {
        $prospect = User::factory()->create();
        $tester = $this->makeTestAccount();

        $this->setMode('off');
        $this->assertFalse(AssessmentVersion::isNewFor($tester));
        $this->assertStringNotContainsString('YOU NOTICE HESITATION', TeacherSetup::instructions('assessment', $tester, 5));

        $this->setMode('all');
        $this->assertTrue(AssessmentVersion::isNewFor($prospect));
        $this->assertStringContainsString('YOU NOTICE HESITATION', TeacherSetup::instructions('assessment', $prospect, 5));

        // Les autres conversations ne changent jamais.
        $this->assertStringNotContainsString('YOU NOTICE HESITATION', TeacherSetup::instructions('lesson', $prospect, 15, ['context' => '']));
        $this->assertStringNotContainsString('YOU NOTICE HESITATION', TeacherSetup::instructions('course', $prospect, 10, ['lesson' => '']));
    }

    public function test_the_start_cue_asks_for_the_welcome_only_in_the_new_assessment(): void
    {
        config(['services.gemini.key' => 'cle-de-test']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton'])]);

        $this->actingAs(User::factory()->create())->postJson('/evaluation/connexion')->assertOk()
            ->assertJsonPath('cues.start', TeacherSetup::cue('assessment_start'))
            // Fin sur silence après l'indication de conclusion (pour tout le monde).
            ->assertJsonPath('silent_end_seconds', 10);

        $this->actingAs($this->makeTestAccount())->postJson('/evaluation/connexion')->assertOk()
            ->assertJsonPath('cues.start', TeacherSetup::cue('assessment_start_new'));

        $this->assertStringContainsString('ask the learner\'s name, then wait', TeacherSetup::cue('assessment_start_new'));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/auth_tokens')
            && str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), 'YOU NOTICE HESITATION'));
    }

    public function test_the_admin_marks_a_test_account_from_the_learner_page(): void
    {
        $admin = $this->admin();
        $learner = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.users.show', $learner))->assertOk()
            ->assertSee('id="compte-de-test"', false)
            ->assertSee('Compte de test')
            ->assertSee('Nouvelle évaluation : Comptes de test seulement. Ce compte passe l\'évaluation actuelle.');

        $this->actingAs($admin)->put(route('admin.users.test-account', $learner), ['is_test_account' => '1'])
            ->assertRedirect(route('admin.users.show', $learner).'#compte-de-test');
        $this->assertTrue($learner->fresh()->is_test_account);

        $this->actingAs($admin)->get(route('admin.users.show', $learner))->assertSee('Ce compte passe la nouvelle évaluation.');

        $this->actingAs($admin)->put(route('admin.users.test-account', $learner), ['is_test_account' => '0']);
        $this->assertFalse($learner->fresh()->is_test_account);

        // Jamais par un apprenant, ni par l'inscription.
        $this->actingAs($learner)->put(route('admin.users.test-account', $learner), ['is_test_account' => '1'])->assertForbidden();
        $this->assertFalse($learner->fresh()->is_test_account);
    }

    public function test_the_settings_page_chooses_who_gets_the_new_assessment(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('id="nouvelle-evaluation"', false)
            ->assertSee('Désactivée')->assertSee('Comptes de test seulement')->assertSee('Tout le monde');

        $this->actingAs($admin)->put(route('admin.settings.update'), ['assessment' => ['new_version' => 'all']])->assertRedirect();
        SiteSettings::flush();
        $this->assertSame('all', AssessmentVersion::mode());

        $this->actingAs($admin)->put(route('admin.settings.update'), ['assessment' => ['new_version' => 'n_importe_quoi']])
            ->assertSessionHasErrors('assessment.new_version');
        SiteSettings::flush();
        $this->assertSame('all', AssessmentVersion::mode());
    }

    public function test_plans_appear_only_at_the_real_end_and_the_mic_stays_open_until_the_closing_sentence(): void
    {
        $session = file_get_contents(public_path('js/live-session.js'));

        // Le niveau peut être montré pendant la conversation, jamais les formules.
        $show = substr($session, strpos($session, 'function showAssessmentResult('), 900);
        $this->assertStringNotContainsString("ui.plans?.removeAttribute('hidden')", $show);
        $finish = substr($session, strpos($session, 'async function finish('));
        $this->assertStringContainsString("if (mode === 'assessment' && state.revealed) {\n        ui.plans?.removeAttribute('hidden');", $finish);

        // L'indication de conclusion ne ferme plus le micro.
        $conclude = substr($session, strpos($session, 'state.end.shouldConclude('), 300);
        $this->assertStringNotContainsString('endMicStream()', $conclude);
    }
}
