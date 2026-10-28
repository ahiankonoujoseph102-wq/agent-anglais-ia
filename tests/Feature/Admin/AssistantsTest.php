<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\User;
use App\Support\Admin\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Rôle « Assistant » : un compte Talkami existant nommé par l'administrateur principal, qui ouvre seulement les
 * pages de AdminAccess::ASSISTANT_ROUTES ; trace de qui a validé ou refusé chaque paiement.
 */
class AssistantsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $assistant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Joseph Principal']);
        $this->assistant = User::factory()->create(['name' => 'Akossiwa Aide']);
        $this->assistant->forceFill(['role' => Role::Assistant])->save();
    }

    private function pendingPayment(string $name = 'Kossi Payeur'): Payment
    {
        $learner = User::factory()->create(['name' => $name]);

        return Payment::query()->forceCreate([
            'user_id' => $learner->id, 'plan' => 'essentiel', 'plan_name' => 'Essentiel', 'daily_minutes' => 15, 'duration_days' => 30,
            'amount' => 7500, 'currency' => 'XOF', 'country' => 'TG', 'operator' => 'flooz', 'operator_name' => 'Flooz',
            'recipient_number' => '+22890000000', 'proof_path' => 'proofs/'.$learner->id.'.jpg', 'status' => PaymentStatus::Pending,
        ]);
    }

    public function test_an_assistant_cannot_open_the_settings(): void
    {
        $this->actingAs($this->assistant)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($this->assistant)->put(route('admin.settings.update'), ['contact' => ['email' => 'pirate@exemple.com']])->assertForbidden();
        $this->actingAs($this->assistant)->get('/admin/reglages')->assertForbidden();
    }

    public function test_an_assistant_opens_only_the_allowed_pages(): void
    {
        $learner = User::factory()->create();

        foreach (['admin.payments.pending', 'admin.users.index', 'admin.subscribers.index', 'admin.inactive.index', 'admin.origins.index', 'admin.ambassadors.index'] as $route) {
            $this->actingAs($this->assistant)->get(route($route))->assertOk();
        }

        $this->actingAs($this->assistant)->get(route('admin.users.show', $learner))->assertOk();
        $this->actingAs($this->assistant)->get('/admin')->assertRedirect();

        // Coûts, consommation, diagnostics, historique, vidéos, alertes, voix : refusés, même adresse tapée à la main.
        foreach (['/admin/consommation', '/admin/diagnostic-texte', '/admin/diagnostic-voix', '/admin/paiements/historique',
            '/admin/videos', '/admin/alertes', '/admin/voix', '/admin/icones'] as $path) {
            $this->actingAs($this->assistant)->get($path)->assertForbidden();
        }

        // Actions de l'administrateur principal : refusées.
        $this->actingAs($this->assistant)->post(route('admin.users.grant', $learner), ['plan' => 'essentiel', 'days' => 7, 'confirmed' => 1])->assertForbidden();
        $this->actingAs($this->assistant)->post(route('admin.users.password', $learner), ['confirmed' => 1])->assertForbidden();
        $this->actingAs($this->assistant)->delete(route('admin.assessments.destroy', $learner), ['confirmed' => 1])->assertForbidden();
        $this->actingAs($this->assistant)->post(route('admin.users.assistant', $learner), ['confirmed' => 1])->assertForbidden();
        $this->assertSame(Role::Learner, $learner->fresh()->role);

        // Un apprenant ordinaire n'entre toujours pas.
        $this->actingAs($learner)->get(route('admin.payments.pending'))->assertForbidden();
    }

    public function test_every_admin_route_outside_the_list_is_refused_to_an_assistant(): void
    {
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! str_starts_with($name, 'admin.') || str_starts_with($name, 'admin.recovery')) {
                continue;
            }

            $this->assertTrue(AdminAccess::allows($this->admin, $name), $name);
            $this->assertSame(in_array($name, AdminAccess::ASSISTANT_ROUTES, true), AdminAccess::allows($this->assistant, $name), $name);
        }

        foreach (AdminAccess::ASSISTANT_ROUTES as $name) {
            $this->assertTrue(Route::has($name), $name);
        }

        $this->assertNotContains('admin.settings.edit', AdminAccess::ASSISTANT_ROUTES);
        $this->assertNotContains('admin.usage.index', AdminAccess::ASSISTANT_ROUTES);
        $this->assertNotContains('admin.text-diagnostic.index', AdminAccess::ASSISTANT_ROUTES);
        $this->assertNotContains('admin.users.assistant', AdminAccess::ASSISTANT_ROUTES);
    }

    public function test_the_assistant_menu_shows_only_what_it_can_open(): void
    {
        $response = $this->actingAs($this->assistant)->get(route('admin.users.index'))->assertOk();

        foreach (['Paiements en attente', 'Abonnés payants', 'Inactifs', 'Inscrits', 'Ambassadeurs', 'Provenance'] as $tab) {
            $response->assertSee($tab);
        }

        foreach (['Réglages', 'Consommation', 'Diagnostic des fournisseurs', 'Diagnostic de la voix', 'Historique', 'Vidéos', 'Alertes', 'Essai des voix'] as $tab) {
            $response->assertDontSee('> '.$tab.'</a>', false);
        }

        $response->assertDontSee(route('admin.settings.edit'), false)->assertDontSee(route('admin.usage.index'), false);

        // Fiche d'un inscrit : lecture, sans les actions du principal.
        $learner = User::factory()->create();
        $this->actingAs($this->assistant)->get(route('admin.users.show', $learner))->assertOk()
            ->assertDontSee('Générer un mot de passe provisoire')
            ->assertDontSee('Nommer assistant')
            ->assertDontSee('id="acces-offert"', false)
            ->assertDontSee('id="conversations"', false)
            ->assertSee('Abonnements')
            ->assertSee('Paiements');

        // Connexion d'un assistant : il arrive dans l'administration ; l'en-tête lui montre le lien.
        $this->actingAs($this->assistant)->get(route('dashboard'))->assertSee('Administration');
    }

    public function test_an_assistant_validates_or_refuses_and_the_history_shows_who(): void
    {
        $approved = $this->pendingPayment('Kossi Payeur');
        $rejected = $this->pendingPayment('Ama Refusee');

        $this->actingAs($this->assistant)->post(route('admin.payments.approve', $approved), ['confirmed' => 1])->assertRedirect();
        $this->actingAs($this->assistant)->post(route('admin.payments.reject', $rejected), ['confirmed' => 1])->assertRedirect();

        $this->assertSame(PaymentStatus::Validated, $approved->fresh()->status);
        $this->assertSame($this->assistant->id, $approved->fresh()->reviewed_by);
        $this->assertSame(PaymentStatus::Rejected, $rejected->fresh()->status);
        $this->assertSame($this->assistant->id, $rejected->fresh()->reviewed_by);

        $this->actingAs($this->admin)->get(route('admin.payments.index'))->assertOk()
            ->assertSee('Traité par')
            ->assertSee('Validé par Akossiwa Aide (assistant)')
            ->assertSee('Refusé par Akossiwa Aide (assistant)');

        // La fiche de l'apprenant le montre aussi.
        $this->actingAs($this->admin)->get(route('admin.users.show', $approved->user))->assertSee('Validé par Akossiwa Aide (assistant)');
    }

    public function test_only_the_main_admin_appoints_or_removes_an_assistant_from_the_learner_page(): void
    {
        $learner = User::factory()->create(['name' => 'Yao Futur']);

        $this->actingAs($this->admin)->get(route('admin.users.show', $learner))->assertOk()
            ->assertSee('id="role"', false)->assertSee('Nommer assistant');

        $this->actingAs($this->admin)->post(route('admin.users.assistant', $learner), ['confirmed' => 1])
            ->assertRedirect(route('admin.users.show', $learner).'#role');
        $this->assertSame(Role::Assistant, $learner->fresh()->role);
        $this->actingAs($learner->fresh())->get(route('admin.subscribers.index'))->assertOk();
        $this->actingAs($learner->fresh())->get(route('admin.settings.edit'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('admin.users.show', $learner))->assertSee('Retirer le rôle d\'assistant', false);
        $this->actingAs($this->admin)->delete(route('admin.users.assistant.remove', $learner), ['confirmed' => 1])->assertRedirect();
        $this->assertSame(Role::Learner, $learner->fresh()->role);
        $this->actingAs($learner->fresh())->get(route('admin.subscribers.index'))->assertForbidden();

        // Jamais un administrateur, jamais sans confirmation.
        $other = User::factory()->admin()->create();
        $this->actingAs($this->admin)->post(route('admin.users.assistant', $other), ['confirmed' => 1])->assertForbidden();
        $this->assertSame(Role::Admin, $other->fresh()->role);
        $this->actingAs($this->admin)->post(route('admin.users.assistant', $learner))->assertOk();
        $this->assertSame(Role::Learner, $learner->fresh()->role);
    }
}
