<?php

namespace Tests\Feature\Admin;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Enums\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Admin\ViewAs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Suivre chaque apprenant de près : « Suivi des élèves » (séance du jour faite ou non), section « Progression et
 * travail » de la fiche d'un inscrit, et « Voir son espace » en lecture seule (administrateur principal).
 */
class LearnerFollowTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    private function learner(string $name): User
    {
        $user = User::factory()->level(Level::A1)->create(['name' => $name, 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A1);

        return $user->fresh();
    }

    private function lessonToday(User $user, int $seconds, LessonStatus $status = LessonStatus::Completed): void
    {
        $user->lessons()->create([
            'subscription_id' => $user->activeSubscription->id, 'date' => today(), 'allowed_seconds' => 900,
            'used_seconds' => $seconds, 'started_at' => now()->subMinutes(20), 'status' => $status,
        ])->forceFill([
            'summary' => 'Vous savez maintenant donner votre numéro de téléphone.',
            'homework' => 'Dites votre numéro trois fois à voix haute.', 'reported_at' => now(),
            'score_fluency' => 3, 'score_grammar' => 4,
        ])->save();
    }

    public function test_the_follow_page_says_who_did_their_lesson_today(): void
    {
        $kofi = $this->learner('Kofi Mensah');
        $ama = $this->learner('Ama Koffi');
        $this->lessonToday($kofi, 840);

        $page = $this->actingAs(User::factory()->admin()->create())->get('/admin/suivi')->assertOk();

        $page->assertSee('Suivi des élèves')
            ->assertSee('Kofi Mensah')->assertSee('Ama Koffi')
            ->assertSee('Séance faite')->assertSee('Pas encore de séance')
            ->assertSee('14 minutes', false);
        // Ceux qui n'ont pas encore fait leur séance d'abord.
        $this->assertLessThan(strpos($page->getContent(), 'Kofi Mensah'), strpos($page->getContent(), 'Ama Koffi'));

        $this->get('/admin/suivi?aujourdhui=pas-encore')->assertSee('Ama Koffi')->assertDontSee('Kofi Mensah');
        $this->get('/admin/suivi?aujourdhui=faite')->assertSee('Kofi Mensah')->assertDontSee('Ama Koffi');
    }

    public function test_the_team_is_never_in_the_follow_list_and_assistants_can_read_it(): void
    {
        $this->learner('Kofi Mensah');
        $admin = User::factory()->admin()->create(['name' => 'Joseph Admin']);
        Subscription::factory()->for($admin)->forPlan('essentiel')->create();
        $assistant = User::factory()->create(['name' => 'Afi Assistante']);
        $assistant->forceFill(['role' => Role::Assistant])->save();

        $this->actingAs($assistant)->get('/admin/suivi')->assertOk()->assertSee('Kofi Mensah')->assertDontSee('Joseph Admin');
    }

    public function test_the_learner_page_shows_progress_and_all_the_work(): void
    {
        $kofi = $this->learner('Kofi Mensah');
        $this->lessonToday($kofi, 840);
        $kofi->learnerErrors()->create(['wrong' => 'I have 20 years', 'correct' => "I'm 20 years old", 'explanation' => 'L\'âge se dit avec « be ».']);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.users.show', $kofi))->assertOk()
            ->assertSee('Progression et travail')
            ->assertSee('Séance faite')
            ->assertSee('Programme A1')
            ->assertSee('Leçon en cours')
            ->assertSee('Vous savez maintenant donner votre numéro de téléphone.')
            ->assertSee('Dites votre numéro trois fois à voix haute.')
            ->assertSee('I have 20 years')
            ->assertSee('Lire la conversation')
            ->assertSee('Voir son espace (lecture seule)');
    }

    public function test_an_assistant_sees_the_progress_but_never_enters_a_learner_space(): void
    {
        $kofi = $this->learner('Kofi Mensah');
        $assistant = User::factory()->create();
        $assistant->forceFill(['role' => Role::Assistant])->save();

        $this->actingAs($assistant)->get(route('admin.users.show', $kofi))->assertOk()
            ->assertSee('Progression et travail')->assertDontSee('Voir son espace');
        $this->post(route('admin.users.view-as', $kofi))->assertForbidden();
    }

    public function test_the_admin_sees_the_learner_space_read_only_then_comes_back(): void
    {
        $kofi = $this->learner('Kofi Mensah');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.view-as', $kofi))->assertRedirect(route('dashboard'));

        // Ses pages, avec son compte, et le bandeau de lecture seule.
        $this->get('/espace')->assertOk()->assertSee('Lecture seule')->assertSee('Kofi Mensah')->assertSee('Revenir à l\'administration', false);
        $this->get('/programme')->assertOk()->assertSee('Lecture seule');

        // Rien n'est modifié : tout envoi est refusé, et la bienvenue n'est jamais ouverte ni marquée.
        $this->put('/compte', ['email' => 'pirate@example.com'])->assertRedirect()->assertSessionHas('status', ViewAs::READ_ONLY);
        $this->postJson('/seance/connexion')->assertForbidden();
        $this->assertNull($kofi->fresh()->email);
        $kofi->forceFill(['welcomed_at' => null])->save();
        $this->get('/espace')->assertOk();
        $this->assertNull($kofi->fresh()->welcomed_at);
        $this->get('/bienvenue?reecouter=1')->assertRedirect();

        // L'administration reste celle de l'administrateur.
        $this->get('/admin/suivi')->assertOk()->assertDontSee('Lecture seule : vous voyez');

        $this->post(route('admin.view-as.stop'))->assertRedirect(route('admin.users.show', $kofi).'#progression');
        $this->get('/espace')->assertDontSee('Lecture seule');
    }

    public function test_only_a_learner_account_can_be_viewed_and_the_view_expires(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.users.view-as', $other))->assertForbidden();

        $kofi = $this->learner('Kofi Mensah');
        $this->post(route('admin.users.view-as', $kofi));
        $this->travel(ViewAs::MINUTES + 1)->minutes();
        $this->get('/espace')->assertDontSee('Lecture seule');
    }

    public function test_privacy_page_says_the_team_can_look_at_the_learner_space(): void
    {
        $this->get('/confidentialite')->assertOk()->assertSee('en lecture seule');
    }
}
