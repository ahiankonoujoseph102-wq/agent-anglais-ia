<?php

namespace Tests\Feature\Admin;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\LearnerSituation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Évaluation réinitialisée par l'administrateur : « Mon espace » propose aussitôt de
 * la repasser, et l'ancien niveau n'est plus affiché ni utilisé.
 */
class AssessmentResetTest extends TestCase
{
    use RefreshDatabase;

    private function assessedLearner(?User $user = null): User
    {
        $user ??= User::factory()->create(['name' => 'Kossi']);
        $user->level = Level::A2;
        $user->save();
        $user->assessment()->create([
            'status' => AssessmentStatus::Completed,
            'started_at' => now()->subDay(),
            'allowed_seconds' => 300,
            'used_seconds' => 280,
            'level' => Level::A2,
        ]);

        return $user;
    }

    private function assertInvitedToTheAssessment(User $user): void
    {
        $html = $this->actingAs($user->fresh())->get('/espace')->assertOk()
            ->assertSee('Passez votre évaluation gratuite')
            ->assertSee('class="btn btn-accent btn-lg btn-block btn-next"', false)
            ->assertSee('Pas encore évalué')
            ->assertDontSee('Choisir ma formule')
            ->getContent();

        // L'invitation passe avant tout le reste de l'espace (première carte après la bienvenue).
        $this->assertLessThan(strpos($html, 'class="stats stats-main"'), strpos($html, 'Passez votre évaluation gratuite'));
        $this->assertStringNotContainsString('<div class="value">A2</div>', $html);

        $this->actingAs($user->fresh())->get('/evaluation')->assertOk();
    }

    public function test_defect_reproduced_old_level_without_assessment_still_offers_the_assessment(): void
    {
        // État laissé par l'ancienne réinitialisation : niveau gardé, évaluation supprimée.
        $user = User::factory()->create();
        $user->level = Level::A2;
        $user->save();

        $this->assertInvitedToTheAssessment($user);
    }

    public function test_reset_clears_the_level_and_the_learner_space_offers_the_assessment_first(): void
    {
        $user = $this->assessedLearner();
        $this->actingAs($user)->get('/espace')->assertSee('<div class="value">A2</div>', false)->assertDontSee('Passez votre évaluation gratuite');

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.users.show', $user))
            ->delete(route('admin.assessments.destroy', $user), ['confirmed' => 1])
            ->assertRedirect(route('admin.users.show', $user).'#evaluation')
            ->assertSessionHas('status', 'L\'évaluation de Kossi a été réinitialisée : son espace lui propose de la repasser.');

        $user->refresh();
        $this->assertNull($user->assessment()->first());
        $this->assertNull($user->level);
        $this->assertStringNotContainsString('Niveau : A2', LearnerSituation::describe($user, 'lesson'));
        $this->assertInvitedToTheAssessment($user);
    }

    public function test_it_works_for_the_admin_account_too(): void
    {
        $admin = $this->assessedLearner(User::factory()->admin()->create());

        $this->actingAs($admin)
            ->delete(route('admin.assessments.destroy', $admin), ['confirmed' => 1])
            ->assertRedirect();

        $this->assertInvitedToTheAssessment($admin);
    }

    public function test_the_migration_clears_the_level_of_accounts_reset_before_the_fix(): void
    {
        $reset = User::factory()->create();
        $reset->level = Level::B1;
        $reset->save();
        $assessed = $this->assessedLearner();

        (require database_path('migrations/2026_10_19_000001_clear_level_of_reset_assessments.php'))->up();

        $this->assertNull($reset->fresh()->level);
        $this->assertSame(Level::A2, $assessed->fresh()->level);
    }
}
