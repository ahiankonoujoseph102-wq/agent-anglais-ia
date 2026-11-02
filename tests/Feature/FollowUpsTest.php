<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Mail\FollowUpMail;
use App\Models\LearnerMail;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Reminders\FollowUps;
use App\Support\Trial;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Relances des inscrits sans formule payée : un e-mail par étape, jamais deux fois, jamais à qui a refusé, jamais à un
 * abonné payant ; lancement de l'offre (évaluation à refaire et séance d'essai) ; page « Relances » de l'administration.
 */
class FollowUpsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-06 10:00:00', 'Africa/Lome'));
        Mail::fake();
        config([
            'platform.trial.enabled' => true,
            'platform.follow_ups.enabled' => true,
            'mail.default' => 'smtp',
            'mail.from.address' => 'equipe@example.test',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.username' => 'utilisateur-smtp',
            'mail.mailers.smtp.password' => 'mot-de-passe-smtp',
        ]);
    }

    private function signedUp(array $attributes = [], int $daysAgo = 2): User
    {
        $user = User::factory()->create($attributes + ['email' => 'eleve'.random_int(1000, 9999).'@example.test']);
        $user->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

        return $user->fresh();
    }

    private function assessed(array $attributes = []): User
    {
        $user = $this->signedUp($attributes);
        $user->forceFill(['level' => Level::A2])->save();
        $user->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 300, 'level' => 'A2', 'status' => 'completed', 'ended_at' => now()->subDays(2)]);

        return $user->fresh();
    }

    public function test_a_learner_without_assessment_receives_one_email_then_never_again(): void
    {
        $user = $this->signedUp();

        $result = FollowUps::run();
        $this->assertSame(1, $result['sent']);
        Mail::assertSent(FollowUpMail::class, function (FollowUpMail $mail) use ($user) {
            $html = $mail->render();

            return $mail->hasTo($user->email)
                && $mail->body['subject'] === 'Votre évaluation orale gratuite vous attend'
                && str_contains($html, 'Passer mon évaluation gratuite')
                && str_contains($html, '15 minutes de vrai cours')
                && str_contains($html, 'Ne plus recevoir ces e-mails');
        });

        FollowUps::run();
        Mail::assertSentCount(1);
        $this->assertSame(LearnerMail::SENT, $user->learnerMails()->sole()->status);
    }

    public function test_after_the_assessment_the_trial_is_offered_and_after_the_trial_the_plans_with_an_honest_report(): void
    {
        $user = $this->assessed();
        FollowUps::run();
        Mail::assertSent(FollowUpMail::class, fn (FollowUpMail $mail) => $mail->body['subject'] === 'Vos 15 minutes de cours offertes vous attendent'
            && str_contains($mail->render(), 'Commencer mon essai gratuit'));

        Trial::claim($user);
        $user->subscriptions()->update(['status' => SubscriptionStatus::Expired->value, 'ends_at' => now()->subHours(3)]);
        $this->travel(3)->days();

        FollowUps::run();
        Mail::assertSent(FollowUpMail::class, function (FollowUpMail $mail) {
            $html = html_entity_decode($mail->render(), ENT_QUOTES);

            return $mail->body['subject'] === 'Votre essai est terminé : on continue ensemble ?'
                && str_contains($html, 'Choisir ma formule')
                && str_contains($html, 'rien n\'est prélevé automatiquement')
                // Aucune séance faite : jamais « Bravo ».
                && ! str_contains($html, 'Bravo')
                && str_contains($html, 'Donner mon avis');
        });
    }

    public function test_never_to_a_paying_learner_nor_the_team_nor_someone_who_said_stop_and_no_email_is_noted_for_whatsapp(): void
    {
        $paid = $this->signedUp();
        Subscription::factory()->for($paid)->forPlan('semaine')->create();
        $assistant = $this->signedUp();
        $assistant->forceFill(['role' => Role::Assistant])->save();
        $stopped = $this->signedUp();
        $stopped->forceFill(['follow_up_opt_out_at' => now()])->save();
        $noEmail = $this->signedUp(['email' => null]);

        FollowUps::run();

        Mail::assertNothingSent();
        $this->assertSame(0, $paid->learnerMails()->count());
        $this->assertSame(0, $assistant->learnerMails()->count());
        $this->assertSame(0, $stopped->learnerMails()->count());
        $this->assertSame(LearnerMail::NO_EMAIL, $noEmail->learnerMails()->sole()->status);
    }

    public function test_the_stop_link_asks_then_stops_every_follow_up(): void
    {
        $user = $this->signedUp();
        $url = FollowUps::stopUrl($user);

        $this->get(route('follow-ups.stop', $user))->assertForbidden();
        $this->get($url)->assertOk()->assertSee('Ne plus recevoir ces e-mails');
        $this->assertNull($user->fresh()->follow_up_opt_out_at);

        $this->post(URL::signedRoute('follow-ups.stop.store', ['user' => $user->id]))->assertOk()->assertSeeText('C\'est noté');
        $this->assertNotNull($user->fresh()->follow_up_opt_out_at);

        FollowUps::run();
        Mail::assertNothingSent();
    }

    public function test_the_offer_resets_the_assessment_of_learners_who_never_paid_and_queues_one_email(): void
    {
        $free = $this->assessed();
        $paid = $this->assessed();
        Subscription::factory()->for($paid)->forPlan('semaine')->create(['status' => SubscriptionStatus::Expired, 'ends_at' => now()->subDay()]);

        // Simulation : rien n'est touché.
        Artisan::call('learners:offer-trial');
        $this->assertNotNull($free->fresh()->assessment);

        Artisan::call('learners:offer-trial', ['--force' => true]);
        $this->assertNull($free->fresh()->assessment);
        $this->assertNull($free->fresh()->level);
        $this->assertSame(LearnerMail::OFFER, $free->learnerMails()->sole()->campaign);
        $this->assertNotNull($paid->fresh()->assessment);
        $this->assertSame(0, $paid->learnerMails()->count());

        // Relancée : le compte déjà prévenu n'est jamais remis à zéro une seconde fois.
        $free->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 300, 'level' => 'B1', 'status' => 'completed', 'ended_at' => now()]);
        Artisan::call('learners:offer-trial', ['--force' => true]);
        $this->assertNotNull($free->fresh()->assessment);

        FollowUps::run();
        Mail::assertSent(FollowUpMail::class, fn (FollowUpMail $mail) => str_starts_with($mail->body['subject'], 'Nouveau sur '.config('platform.name')));
    }

    public function test_two_follow_ups_are_never_closer_than_the_gap(): void
    {
        $user = $this->signedUp();
        $user->learnerMails()->create(['campaign' => LearnerMail::OFFER, 'status' => LearnerMail::SENT, 'sent_at' => now()->subHour()]);
        $user->learnerMails()->create(['campaign' => LearnerMail::NO_TRIAL, 'status' => LearnerMail::PENDING]);
        $user->forceFill(['level' => Level::A2])->save();
        $user->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 300, 'level' => 'A2', 'status' => 'completed', 'ended_at' => now()->subDays(2)]);

        FollowUps::run();
        Mail::assertNothingSent();

        $this->travel(2)->days();
        FollowUps::run();
        Mail::assertSentCount(1);
    }

    public function test_the_follow_ups_page_shows_each_stage_and_opens_whatsapp_with_the_message(): void
    {
        $user = $this->assessed(['name' => 'Ama Kodjo', 'email' => null]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.follow-ups.index'))->assertOk()
            ->assertSee('Ama Kodjo')
            ->assertSeeText('Évaluation faite, essai pas commencé')
            ->assertSeeText('Pas d\'adresse');

        $response = $this->get(route('admin.follow-ups.whatsapp', $user));
        $this->assertStringStartsWith('https://wa.me/', $response->headers->get('Location'));
        $this->assertStringContainsString(rawurlencode('Vos 15 minutes de cours offertes'), $response->headers->get('Location'));
        $this->assertSame(LearnerMail::SENT, $user->learnerMails()->where('campaign', 'wa_no_trial')->sole()->status);

        $assistant = User::factory()->create();
        $assistant->forceFill(['role' => Role::Assistant])->save();
        $this->actingAs($assistant)->get(route('admin.follow-ups.index'))->assertOk();
        $this->actingAs($this->signedUp())->get(route('admin.follow-ups.index'))->assertForbidden();
    }
}
