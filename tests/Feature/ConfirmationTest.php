<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\Payment;
use App\Models\User;
use App\Support\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/**
 * Aucune fenêtre du navigateur (confirm(), alert(), prompt()) sur le site. Les actions
 * graves passent par une fenêtre intégrée (public/js/app.js) ou, sans JavaScript, par
 * une page de confirmation du serveur ; les autres actions sont immédiates.
 */
class ConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_browser_dialog_anywhere_in_the_site(): void
    {
        $files = array_merge(
            File::allFiles(resource_path('views')),
            File::files(public_path('js')),
        );

        $found = [];

        foreach ($files as $file) {
            /** @var SplFileInfo $file */
            $code = $file->getContents();

            // confirm( alert( prompt( appelés seuls ou via window. — pas deferredPrompt.prompt() (installation).
            if (preg_match_all('/(?:^|[^\w.$])(?:window\s*\.\s*)?(confirm|alert|prompt)\s*\(|window\s*\.\s*(confirm|alert|prompt)\b/m', $code, $matches)) {
                $found[] = $file->getRelativePathname().' : '.implode(', ', array_filter(array_merge($matches[1], $matches[2])));
            }
        }

        $this->assertSame([], $found, 'Fenêtres du navigateur trouvées');
    }

    private function payment(User $learner): Payment
    {
        $plan = Plan::find('essentiel');
        Storage::fake('local');
        config(['filesystems.private_disk' => 'local']);
        Storage::disk('local')->put('payment-proofs/x.png', 'image');

        return $learner->payments()->create([
            'plan' => $plan->key, 'plan_name' => $plan->name, 'daily_minutes' => $plan->dailyMinutes,
            'duration_days' => $plan->durationDays, 'amount' => $plan->price, 'currency' => 'FCFA',
            'country' => 'TG', 'operator' => 'flooz', 'operator_name' => 'Flooz',
            'recipient_number' => '+228 90 00 00 22', 'proof_path' => 'payment-proofs/x.png',
        ]);
    }

    public function test_grave_admin_forms_use_the_integrated_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        $learner = User::factory()->create();
        $learner->assessment()->create(['status' => AssessmentStatus::Completed, 'started_at' => now(), 'allowed_seconds' => 300, 'level' => Level::A2]);
        $this->payment($learner);

        $this->actingAs($admin)->get('/admin/paiements')->assertOk()
            ->assertSee('data-confirm="payment.approve"', false)
            ->assertSee('data-confirm-title="Valider ce paiement ?"', false)
            ->assertSee('data-confirm="payment.reject"', false);

        $this->actingAs($admin)->get(route('admin.users.show', $learner))->assertOk()
            ->assertSee('data-confirm="password.temporary"', false)
            ->assertSee('data-confirm="assessment.reset"', false);

        $this->actingAs($admin)->get('/admin/consommation')->assertOk()
            ->assertSee('data-confirm="assessment.reset"', false);

        // Conversation vocale : « Terminer » avant la fin, fenêtre intégrée (textes du serveur).
        $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()
            ->assertSee('data-stop-confirm=', false)
            ->assertSee('Terminer maintenant ?');
    }

    public function test_without_javascript_the_server_asks_for_confirmation_then_acts(): void
    {
        $admin = User::factory()->admin()->create();
        $payment = $this->payment(User::factory()->create());

        // Envoi sans confirmed=1 (JavaScript absent) : rien n'est fait, page de confirmation.
        $this->actingAs($admin)
            ->from('/admin/paiements')
            ->post(route('admin.payments.reject', $payment), ['reason' => 'Capture illisible.'])
            ->assertOk()
            ->assertSee('Refuser ce paiement ?')
            ->assertSee('L\'accès de l\'apprenant est coupé aussitôt')
            ->assertSee('<input type="hidden" name="reason" value="Capture illisible.">', false)
            ->assertSee('<input type="hidden" name="confirmed" value="1">', false)
            ->assertSee('<input type="hidden" name="_origin" value="'.url('/admin/paiements').'">', false)
            ->assertSee('Confirmer le refus')
            ->assertSee('Annuler')
            ->assertSee('href="'.url('/admin/paiements').'"', false);
        $this->assertTrue($payment->fresh()->isPending());

        // « Confirmer » sur cette page (le navigateur l'envoie depuis l'adresse de l'action) :
        // action faite, retour direct sur la page d'origine avec le message (pas d'erreur 405).
        $this->actingAs($admin)
            ->from(route('admin.payments.reject', $payment))
            ->post(route('admin.payments.reject', $payment), ['reason' => 'Capture illisible.', 'confirmed' => 1, '_origin' => url('/admin/paiements')])
            ->assertRedirect('/admin/paiements')
            ->assertSessionHas('status');
        $this->assertSame('Capture illisible.', $payment->fresh()->rejection_reason);

        // Une adresse d'origine extérieure au site est ignorée.
        $other = $this->payment(User::factory()->create());
        $this->actingAs($admin)
            ->from(route('admin.payments.approve', $other))
            ->post(route('admin.payments.approve', $other), ['confirmed' => 1, '_origin' => 'https://exemple.invalid/piege'])
            ->assertRedirect(route('admin.payments.approve', $other));
    }

    public function test_every_grave_action_waits_for_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        $learner = User::factory()->create();
        $learner->assessment()->create(['status' => AssessmentStatus::Completed, 'started_at' => now(), 'allowed_seconds' => 300, 'level' => Level::A2]);
        $payment = $this->payment($learner);
        $password = $learner->password;

        $this->actingAs($admin)->post(route('admin.payments.approve', $payment))->assertOk()->assertSee('Valider ce paiement ?');
        $this->actingAs($admin)->post(route('admin.users.password', $learner))->assertOk()->assertSee('Générer un mot de passe provisoire ?');
        $this->actingAs($admin)->delete(route('admin.assessments.destroy', $learner))->assertOk()
            ->assertSee('Réinitialiser l\'évaluation ?')
            ->assertSee('name="_method" value="DELETE"', false);

        $this->assertTrue($payment->fresh()->isPending());
        $this->assertSame($password, $learner->fresh()->password);
        $this->assertNotNull($learner->assessment()->first());

        // Les apprenants n'y ont toujours pas accès.
        $this->actingAs($learner)->post(route('admin.payments.approve', $payment), ['confirmed' => 1])->assertForbidden();
    }

    public function test_status_messages_appear_at_the_top_and_disappear_by_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/reglages', ['contact' => ['email' => 'equipe@example.test']])
            ->assertRedirect(route('admin.settings.edit'));

        $this->actingAs($admin)->withSession(['status' => 'Réglages enregistrés.'])->get('/admin/reglages')
            ->assertSee('class="toast" role="status" data-toast', false)
            ->assertSee('--toast-delay: 4s', false)
            ->assertSee('Réglages enregistrés.')
            ->assertSee('data-toast-close', false);
    }
}
