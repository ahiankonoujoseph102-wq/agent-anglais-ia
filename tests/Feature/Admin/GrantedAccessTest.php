<?php

namespace Tests\Feature\Admin;

use App\Enums\Level;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Gemini\LearnerSituation;
use App\Support\Gemini\SiteGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

/**
 * Accès offert par l'administrateur (démonstrations) : mêmes droits que la formule, jamais un
 * paiement ni un revenu, fin automatique, retrait, consommation repérée à part.
 */
class GrantedAccessTest extends TestCase
{
    use BuildsPrograms, HasCourses, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Équipe']);
        config(['services.gemini.key' => 'cle', 'services.gemini.token_minutes' => 5]);
        $this->freezeSecond();
    }

    /** @param array<string, mixed> $data */
    private function offer(User $learner, array $data = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->post(route('admin.users.grant', $learner), $data + [
            'plan' => 'essentiel',
            'duration' => '3',
            'confirmed' => 1,
        ]);
    }

    private function learnerWithoutAccess(): User
    {
        $learner = User::factory()->level(Level::A2)->create(['name' => 'Yao']);
        // Évaluation déjà passée : « Mon espace » parle alors de la formule, pas de l'évaluation.
        $learner->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 300, 'level' => 'A2', 'status' => 'completed']);

        return $learner;
    }

    private function granted(User $learner, string $plan = 'essentiel', int $days = 3): Subscription
    {
        $preset = in_array($days, config('platform.granted_access.presets'), true);

        $this->offer($learner, ['plan' => $plan, 'duration' => $preset ? (string) $days : 'custom', 'custom_days' => $days])->assertSessionHasNoErrors();

        return $learner->subscriptions()->granted()->firstOrFail();
    }

    public function test_the_administrator_offers_an_access_without_any_payment(): void
    {
        $learner = $this->learnerWithoutAccess();

        $this->offer($learner, ['plan' => 'intensif', 'duration' => '7', 'note' => 'Visite au Collège X'])
            ->assertRedirect(route('admin.users.show', $learner))
            ->assertSessionHas('status');

        $subscription = $learner->subscriptions()->sole();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->isGranted());
        $this->assertSame('intensif', $subscription->plan);
        $this->assertSame(30, $subscription->daily_minutes);
        $this->assertSame(0, $subscription->price);
        $this->assertSame(7, $subscription->granted_days);
        $this->assertSame('Visite au Collège X', $subscription->grant_note);
        $this->assertTrue($subscription->grantedBy->is($this->admin));
        $this->assertTrue($subscription->ends_at->equalTo(now()->addDays(7)));
        // Aucun paiement n'est créé.
        $this->assertSame(0, Payment::count());
    }

    public function test_only_an_administrator_can_offer_or_withdraw_an_access(): void
    {
        $learner = $this->learnerWithoutAccess();

        $this->offer($learner, as: User::factory()->create())->assertForbidden();
        $this->actingAs(User::factory()->create())->delete(route('admin.users.revoke', $learner), ['confirmed' => 1])->assertForbidden();

        auth()->logout();
        $this->post(route('admin.users.grant', $learner), ['plan' => 'essentiel', 'duration' => '1', 'confirmed' => 1])->assertRedirect(route('login'));

        $this->assertSame(0, $learner->subscriptions()->count());
    }

    public function test_the_confirmation_page_recalls_the_plan_and_the_end_date_and_does_nothing(): void
    {
        $learner = $this->learnerWithoutAccess();

        $response = $this->actingAs($this->admin)->post(route('admin.users.grant', $learner), ['plan' => 'intensif', 'duration' => '3'])->assertOk();

        $response->assertSee('Offrir cet accès ?')
            ->assertSee('Intensif')
            ->assertSee(now()->addDays(3)->translatedFormat('j F Y'), false);
        $this->assertSame(0, $learner->subscriptions()->count());

        // La fenêtre intégrée (JavaScript) reçoit le même texte avec des marqueurs à remplir.
        $page = $this->actingAs($this->admin)->get(route('admin.users.show', $learner))->assertOk();
        $page->assertSee('data-confirm="access.grant"', false)->assertSee('{plan}', false)->assertSee('{end}', false);
        $this->assertStringNotContainsString('confirm(', $page->getContent());
    }

    public function test_an_access_is_refused_when_a_paid_access_is_active(): void
    {
        $paid = $this->learner('essentiel');

        $this->offer($paid)->assertSessionHasErrors('plan');
        $this->assertStringContainsString('accès payé', session('errors')->first('plan'));
        $this->assertSame(0, $paid->subscriptions()->granted()->count());

        // Un accès provisoire (paiement en cours de vérification) compte comme un accès payé.
        $provisional = $this->provisionalLearner();
        $this->offer($provisional)->assertSessionHasErrors('plan');
        $this->assertSame(0, $provisional->subscriptions()->granted()->count());
    }

    public function test_a_second_access_is_refused_while_one_is_running_and_allowed_after_it_is_withdrawn(): void
    {
        $learner = $this->learnerWithoutAccess();
        $this->granted($learner);

        $this->offer($learner)->assertSessionHasErrors('plan');
        $this->assertSame(1, $learner->subscriptions()->count());

        $this->actingAs($this->admin)->delete(route('admin.users.revoke', $learner), ['confirmed' => 1])->assertSessionHas('status');

        $this->offer($learner, ['duration' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(2, $learner->subscriptions()->granted()->count());
    }

    public function test_the_duration_is_limited_to_thirty_days(): void
    {
        $learner = $this->learnerWithoutAccess();

        $this->offer($learner, ['duration' => 'custom', 'custom_days' => 31])->assertSessionHasErrors('custom_days');
        $this->offer($learner, ['duration' => 'custom', 'custom_days' => 0])->assertSessionHasErrors('custom_days');
        $this->offer($learner, ['duration' => 'custom'])->assertSessionHasErrors('custom_days');
        $this->offer($learner, ['duration' => '45'])->assertSessionHasErrors('duration');
        $this->offer($learner, ['plan' => 'gratuit'])->assertSessionHasErrors('plan');
        $this->offer($learner, ['note' => str_repeat('a', 201)])->assertSessionHasErrors('note');
        $this->assertSame(0, $learner->subscriptions()->count());

        $this->offer($learner, ['duration' => 'custom', 'custom_days' => 30])->assertSessionHasNoErrors();
        $this->assertSame(30, $learner->subscriptions()->sole()->granted_days);
    }

    public function test_the_presets_and_every_plan_come_from_the_configuration(): void
    {
        foreach (['1', '3', '7'] as $days) {
            $learner = User::factory()->create();
            $this->offer($learner, ['plan' => 'semaine', 'duration' => $days])->assertSessionHasNoErrors();
            $this->assertSame((int) $days, $learner->subscriptions()->sole()->granted_days);
        }

        $page = $this->actingAs($this->admin)->get(route('admin.users.show', $this->learnerWithoutAccess()))->assertOk();
        foreach (['Semaine', 'Essentiel', 'Intensif', '1 jour', '3 jours', '7 jours', 'Autre durée', 'Note facultative', 'Offrir un accès'] as $text) {
            $page->assertSee($text);
        }
    }

    public function test_the_granted_access_gives_the_same_rights_as_the_plan(): void
    {
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton'])]);
        $learner = $this->learnerWithoutAccess();
        $this->granted($learner, 'intensif');
        $this->buildProgram($learner);

        // Séance orale quotidienne avec les minutes de la formule.
        $this->actingAs($learner)->postJson('/seance/connexion')->assertOk()->assertJsonPath('remaining_seconds', 1800);
        $this->actingAs($learner)->postJson('/seance/fin')->assertOk();

        // « Mes cours » : accès, et ses limites habituelles.
        $this->fakeGemini();
        $this->actingAs($learner)->get(route('courses.index'))->assertOk()->assertSee('Ajouter une leçon');
        $lesson = $this->storedLesson($learner);
        $this->actingAs($learner)->postJson(route('courses.connect', $lesson))->assertOk()->assertJsonPath('remaining_seconds', 600);

        $dashboard = $this->actingAs($learner)->get('/espace')->assertOk();
        $dashboard->assertSeeText('Leçons aujourd\'hui : 1 sur 5');
    }

    public function test_the_access_ends_like_a_paid_access_and_invites_to_choose_a_plan(): void
    {
        $learner = $this->learnerWithoutAccess();
        $this->granted($learner, 'essentiel', 1);

        $this->travel(25)->hours();
        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Expired, $learner->subscriptions()->sole()->status);
        $this->assertNull($learner->fresh()->activeSubscription);
        $this->actingAs($learner)->get('/seance')->assertRedirect('/abonnement');
        $this->actingAs($learner)->postJson('/seance/connexion')->assertForbidden();
        $this->actingAs($learner)->get(route('courses.index'))->assertSee('Choisir ma formule');

        $this->actingAs($learner)->get('/espace')->assertOk()
            ->assertSee('Votre accès offert est terminé')
            ->assertSee(route('payment.plans'), false)
            ->assertDontSee('Votre abonnement a expiré');
    }

    public function test_withdrawing_the_access_stops_it_at_once_and_is_kept_in_the_history(): void
    {
        $learner = $this->learnerWithoutAccess();
        $this->granted($learner, 'essentiel', 7);

        $this->actingAs($this->admin)->delete(route('admin.users.revoke', $learner), ['confirmed' => 1])
            ->assertRedirect(route('admin.users.show', $learner));

        $subscription = $learner->subscriptions()->sole();
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertNotNull($subscription->revoked_at);
        $this->assertTrue($subscription->ends_at->lte(now()));
        $this->assertNull($learner->fresh()->activeSubscription);

        $page = $this->actingAs($this->admin)->get(route('admin.users.show', $learner));
        $page->assertSee('retiré le '.now()->format('d/m/Y'))->assertDontSee('Retirer l\'accès offert');

        // Sans confirmation (JavaScript absent), rien n'est fait : page de confirmation.
        $this->granted($learner);
        $this->actingAs($this->admin)->delete(route('admin.users.revoke', $learner))->assertOk()->assertSee('Retirer l\'accès offert ?');
        $this->assertNotNull($learner->fresh()->activeSubscription);
    }

    public function test_withdrawing_the_access_also_stops_a_running_conversation(): void
    {
        $this->fakeGemini();
        $learner = $this->learnerWithoutAccess();
        $this->granted($learner);
        $lesson = $this->storedLesson($learner);
        $this->actingAs($learner)->postJson(route('courses.connect', $lesson))->assertOk();

        $this->actingAs($this->admin)->delete(route('admin.users.revoke', $learner), ['confirmed' => 1]);

        $this->assertSame('interrupted', $learner->courseSessions()->sole()->status->value);
    }

    public function test_a_granted_access_is_never_a_payment_nor_a_revenue(): void
    {
        $learner = $this->learnerWithoutAccess();
        $this->granted($learner, 'intensif', 30);

        $this->assertSame(0, Payment::count());
        $this->assertSame(0, (int) Subscription::query()->granted()->sum('price'));

        // Aucun revenu : le total encaissé du mois ne vient que des paiements validés.
        $paying = $this->learner('semaine');
        Payment::query()->forceCreate([
            'user_id' => $paying->id, 'plan' => 'semaine', 'plan_name' => 'Semaine', 'daily_minutes' => 15, 'duration_days' => 7,
            'amount' => 1500, 'currency' => 'XOF', 'country' => 'TG', 'operator' => 'flooz', 'operator_name' => 'Flooz',
            'recipient_number' => '+22890000000', 'proof_path' => 'proofs/test.jpg', 'status' => PaymentStatus::Validated, 'reviewed_at' => now(), 'reviewed_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->get(route('admin.payments.index'))->assertOk()
            ->assertSee("1\u{202F}500\u{00A0}FCFA", false)
            ->assertDontSee("9\u{202F}000\u{00A0}FCFA", false);
        $this->actingAs($this->admin)->get(route('admin.payments.pending'))->assertOk()->assertDontSee('Yao');
    }

    public function test_the_list_and_the_sheet_show_a_granted_access_apart_from_a_paid_one(): void
    {
        $learner = $this->learnerWithoutAccess();
        $paid = $this->learner('essentiel');
        $this->granted($learner, 'essentiel', 3);
        $until = now()->addDays(3)->format('d/m/Y');

        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()
            ->assertSee('Accès offert jusqu\'au '.$until, false);
        $this->assertSame(1, substr_count($this->actingAs($this->admin)->get(route('admin.users.index'))->getContent(), 'Accès offert jusqu'));

        $this->offer($learner, as: $this->admin);
        $this->actingAs($this->admin)->get(route('admin.users.show', $learner))->assertOk()
            ->assertSee('Accès offert')
            ->assertSee('formule Essentiel')
            ->assertSee('3 jours')
            ->assertSee('Offert par Équipe');
        $this->actingAs($this->admin)->get(route('admin.users.show', $paid))->assertOk()->assertDontSee('badge-grant', false);
    }

    public function test_the_history_keeps_the_note(): void
    {
        $learner = $this->learnerWithoutAccess();
        $this->offer($learner, ['note' => 'Visite au Collège X', 'duration' => '1']);

        $this->actingAs($this->admin)->get(route('admin.users.show', $learner))
            ->assertSee('Visite au Collège X')
            ->assertSee(now()->format('d/m/Y'))
            ->assertSee('1 jour');
    }

    public function test_the_consumption_page_flags_conversations_made_during_a_granted_access(): void
    {
        $this->fakeGemini();
        $learner = $this->learnerWithoutAccess();
        $this->granted($learner, 'essentiel', 3);
        $lesson = $this->storedLesson($learner, 'Leçon offerte');

        $this->actingAs($learner)->postJson(route('courses.connect', $lesson))->assertOk();
        $this->travel(3)->minutes();
        $this->actingAs($learner)->postJson(route('courses.signal'), ['input_tokens' => 800, 'output_tokens' => 200])->assertOk();
        $this->actingAs($learner)->postJson(route('courses.finish'))->assertOk();

        // Une séance d'un apprenant payé n'est pas marquée.
        $paid = $this->learner('essentiel');
        $paidLesson = $this->storedLesson($paid, 'Leçon payée');
        $this->actingAs($paid)->postJson(route('courses.connect', $paidLesson))->assertOk();
        $this->actingAs($paid)->postJson(route('courses.finish'))->assertOk();

        $page = $this->actingAs($this->admin)->get(route('admin.usage.index'))->assertOk();
        $page->assertSee('Pendant un accès offert (démonstrations)');

        $html = $page->getContent();
        $this->assertSame(1, substr_count($html, 'badge badge-grant'));
        $this->assertMatchesRegularExpression('/Séances « Mes cours »<\/div>\s*<div class="value">1<\/div>\s*<div class="detail">\s*3 minutes/', substr($html, strpos($html, 'Pendant un accès offert')));
    }

    public function test_the_dashboard_shows_the_granted_access_and_a_discreet_message_the_last_two_days(): void
    {
        // Heure fixe : « 4 jours et 12 heures plus tard » doit tomber le jour de la fin, à toute heure du test.
        $this->travelTo(now()->setTime(15, 0));

        $learner = $this->learnerWithoutAccess();
        $this->granted($learner, 'essentiel', 5);

        $far = $this->actingAs($learner)->get('/espace')->assertOk();
        $far->assertSee('Accès offert jusqu\'au '.now()->addDays(5)->translatedFormat('j F Y'), false)
            ->assertSee('avec la formule Essentiel')
            ->assertDontSee('se termine demain')
            ->assertDontSee('Renouveler');

        // La veille de la fin.
        $this->travel(4)->days();
        $this->actingAs($learner)->get('/espace')->assertSee('Votre accès offert se termine demain')->assertSee('Choisir ma formule');

        // Le jour de la fin.
        $this->travel(12)->hours();
        $this->actingAs($learner)->get('/espace')->assertSee('Votre accès offert se termine aujourd\'hui');
    }

    public function test_after_a_granted_access_the_learner_follows_the_normal_path_to_a_programme(): void
    {
        $learner = $this->learnerWithoutAccess();
        $this->granted($learner);

        $this->actingAs($learner)->get('/espace')->assertSee('Construisons votre programme')->assertSee(route('program.questionnaire', 1), false);
        $this->actingAs($learner)->get(route('program.questionnaire', 1))->assertOk();
    }

    public function test_a_validated_payment_ends_the_granted_access_and_the_paid_one_starts_now(): void
    {
        $learner = $this->learnerWithoutAccess();
        $grant = $this->granted($learner, 'essentiel', 7);

        $payment = Payment::query()->forceCreate([
            'user_id' => $learner->id, 'plan' => 'essentiel', 'plan_name' => 'Essentiel', 'daily_minutes' => 15, 'duration_days' => 30,
            'amount' => 5000, 'currency' => 'XOF', 'country' => 'TG', 'operator' => 'flooz', 'operator_name' => 'Flooz',
            'recipient_number' => '+22890000000', 'proof_path' => 'proofs/test.jpg', 'status' => PaymentStatus::Pending,
        ]);
        $payment->approve($this->admin);

        $this->assertSame(SubscriptionStatus::Expired, $grant->fresh()->status);
        $paid = $learner->subscriptions()->paid()->sole();
        $this->assertSame(SubscriptionStatus::Active, $paid->status);
        $this->assertTrue($paid->starts_at->lte(now()));
        $this->assertTrue($paid->ends_at->equalTo(now()->addDays(30)));
        $this->assertFalse($learner->fresh()->activeSubscription->isGranted());
    }

    public function test_teacher_joe_knows_about_a_granted_access_without_promising_one(): void
    {
        $learner = $this->learnerWithoutAccess();
        $this->granted($learner, 'essentiel', 3);

        $situation = LearnerSituation::describe($learner->fresh(), 'lesson');
        $this->assertStringContainsString('Accès offert par l\'équipe : formule Essentiel, jusqu\'au '.now()->addDays(3)->format('d/m/Y'), $situation);

        $guide = SiteGuide::text();
        $this->assertStringContainsString('Accès offert', $guide);
        $this->assertStringContainsString('N\'en promets jamais un', $guide);
    }
}
