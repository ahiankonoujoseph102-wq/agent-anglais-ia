<?php

namespace Tests\Feature;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Gemini\WelcomeMessage;
use App\Support\Reviews\TimeBonuses;
use App\Support\Trial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Séance d'essai gratuite : après l'évaluation, une fois par compte, 15 minutes de vrai cours EN TOUT (programme,
 * séance du jour, « Mes cours »), son propre message de bienvenue, et la fin qui mène aux formules.
 */
class TrialTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['platform.trial.enabled' => true, 'services.gemini.key' => 'cle-secrete-du-serveur']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton']), '*' => Http::response([], 500)]);
    }

    private function assessed(array $attributes = []): User
    {
        $user = User::factory()->level(Level::A2)->create(['name' => 'Kossi Mensah', 'welcomed_at' => null] + $attributes);
        $user->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 300, 'level' => 'A2', 'status' => 'completed', 'ended_at' => now()]);

        return $user->fresh();
    }

    private function lesson(User $user, int $used, string $status = 'completed'): void
    {
        $user->lessons()->create([
            'subscription_id' => $user->activeSubscription?->id, 'date' => today(), 'allowed_seconds' => $used, 'used_seconds' => $used,
            'started_at' => now()->subMinutes(20), 'ended_at' => now()->subMinutes(5), 'status' => $status,
        ]);
    }

    public function test_after_the_assessment_the_trial_is_the_next_step_on_the_dashboard_and_the_result_page(): void
    {
        $user = $this->assessed();

        $this->actingAs($user)->get('/espace')->assertOk()
            ->assertSee('15 minutes de cours offertes')
            ->assertSee('Commencer mon essai gratuit')
            ->assertSee(route('trial.claim'), false);

        $this->get('/evaluation')->assertOk()->assertSee('Commencer mon essai gratuit');
    }

    public function test_claiming_opens_a_free_access_of_fifteen_minutes_for_forty_eight_hours_then_its_own_welcome(): void
    {
        $user = $this->assessed();

        $this->actingAs($user)->post(route('trial.claim'))->assertRedirect(route('welcome.show'));

        $trial = $user->subscriptions()->sole();
        $this->assertTrue($trial->isTrial());
        $this->assertSame(0, $trial->price);
        $this->assertSame(15, $trial->daily_minutes);
        $this->assertTrue($trial->isGranted());
        $this->assertSame(SubscriptionStatus::Active, $trial->status);
        $this->assertTrue($trial->ends_at->between(now()->addHours(47), now()->addHours(49)));
        $this->assertSame(0, $user->payments()->count());

        // Son propre message : jamais « formule activée », jamais « temps qui revient chaque jour ».
        $this->get(route('welcome.show'))->assertOk()
            ->assertSeeText('Votre séance d\'essai gratuite est ouverte')
            ->assertDontSee('est activée');
        $texts = WelcomeMessage::texts($user->fresh());
        $all = implode(' ', $texts['paragraphs']);
        $this->assertStringContainsString('séance d\'essai gratuite', $all);
        $this->assertStringContainsString('15 minutes de cours offertes, à utiliser dans les 48 heures', $all);
        $this->assertStringNotContainsString('Félicitations pour votre formule', $all);
        $this->assertStringNotContainsString('revient chaque jour', $all);

        // Vu une fois pour l'essai ; le message d'une future formule payée reste à voir.
        $user->refresh();
        $this->assertNotNull($user->trial_welcomed_at);
        $this->assertNull($user->welcomed_at);
        $this->get('/espace')->assertOk()->assertSee('Essai gratuit : encore 15 minutes');
    }

    public function test_only_once_and_only_for_someone_who_never_paid(): void
    {
        $user = $this->assessed();
        $this->actingAs($user)->post(route('trial.claim'));
        $user->subscriptions()->update(['status' => SubscriptionStatus::Expired->value, 'ends_at' => now()->subMinute()]);
        $this->assertFalse(Trial::eligible($user->fresh()));
        $this->post(route('trial.claim'))->assertRedirect(route('dashboard'));
        $this->assertSame(1, $user->subscriptions()->count());

        $paid = $this->assessed(['phone' => '+22890000101']);
        Subscription::factory()->for($paid)->forPlan('semaine')->create(['status' => SubscriptionStatus::Expired, 'ends_at' => now()->subDay()]);
        $this->assertFalse(Trial::eligible($paid->fresh()));

        $noAssessment = User::factory()->create(['phone' => '+22890000102']);
        $this->assertFalse(Trial::eligible($noAssessment));

        $assistant = $this->assessed(['phone' => '+22890000103']);
        $assistant->forceFill(['role' => Role::Assistant])->save();
        $this->assertFalse(Trial::eligible($assistant->fresh()));

        config(['platform.trial.enabled' => false]);
        $this->assertFalse(Trial::eligible($this->assessed(['phone' => '+22890000104'])));
    }

    public function test_fifteen_minutes_in_all_even_on_the_next_day_then_the_trial_ends_and_leads_to_the_plans(): void
    {
        $user = $this->assessed();
        $this->actingAs($user)->post(route('trial.claim'));
        $user = $user->fresh();

        $this->lesson($user, 600);
        $this->assertSame(300, $user->fresh()->remainingSecondsToday());

        // Le lendemain : le temps de l'essai ne revient pas.
        $this->travel(1)->days();
        $this->assertSame(300, $user->fresh()->remainingSecondsToday());

        $this->lesson($user->fresh(), 240);
        $this->assertSame(60, $user->fresh()->remainingSecondsToday());

        // Moins que le minimum pour une séance : l'essai se termine, « Mon espace » propose les formules.
        Artisan::call('live:close-stale');
        $this->assertSame(SubscriptionStatus::Expired, $user->subscriptions()->sole()->status);
        $this->actingAs($user->fresh())->get('/espace')->assertOk()
            ->assertSee('Votre essai gratuit est terminé')
            ->assertSee('Choisir ma formule')
            ->assertDontSee('Commencer mon essai gratuit');
    }

    public function test_review_minutes_are_kept_for_the_first_real_plan(): void
    {
        $user = $this->assessed();
        $user->timeBonuses()->create(['seconds' => 900, 'reason' => 'review']);
        $this->actingAs($user)->post(route('trial.claim'));

        // Pendant l'essai : 15 minutes, jamais les minutes offertes, qui restent pour la suite.
        $this->assertSame(900, $user->fresh()->remainingSecondsToday());
        TimeBonuses::pin($user->fresh());
        $this->assertNull($user->timeBonuses()->sole()->applied_on);
    }

    public function test_the_teacher_presents_the_trial_and_invites_to_choose_a_plan_at_the_end(): void
    {
        $user = $this->assessed();
        $this->actingAs($user)->post(route('trial.claim'));
        $this->buildProgram($user->fresh(), Level::A2);

        $json = $this->actingAs($user->fresh())->postJson('/seance/connexion')->assertOk()->json();

        $this->assertStringContainsString('FREE TRIAL lesson of 15 minutes', $json['cues']['start']);
        $this->assertStringContainsString('« Mes cours »', $json['cues']['start']);
        $this->assertStringNotContainsString('congratulate and thank them for choosing', $json['cues']['start']);
        $this->assertStringContainsString('C\'était votre séance d\'essai gratuite', $json['session_end']['conclude']);
    }

    public function test_after_paying_the_real_welcome_still_comes(): void
    {
        $user = $this->assessed();
        $this->actingAs($user)->post(route('trial.claim'));
        $this->get(route('welcome.show'))->assertOk();
        $user->subscriptions()->update(['status' => SubscriptionStatus::Expired->value, 'ends_at' => now()->subMinute()]);

        Subscription::factory()->for($user)->forPlan('essentiel')->create();

        $this->actingAs($user->fresh())->get('/espace')->assertRedirect(route('welcome.show'));
        $this->get(route('welcome.show'))->assertOk()->assertSee('Votre formule Essentiel est activée');
    }

    public function test_the_trial_is_never_listed_as_a_paid_subscription_and_no_lesson_left_open_blocks_the_end(): void
    {
        $user = $this->assessed();
        $this->actingAs($user)->post(route('trial.claim'));
        $this->lesson($user->fresh(), 900, LessonStatus::InProgress->value);

        // Une séance en cours n'est jamais coupée par la fin de l'essai.
        $this->assertFalse(Trial::expireIfUsed($user->fresh()));
        $this->assertSame(0, Subscription::query()->paid()->count());
    }
}
