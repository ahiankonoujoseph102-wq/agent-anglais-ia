<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Enums\SubscriptionStatus;
use App\Mail\FollowUpMail;
use App\Mail\PaymentAlertMail;
use App\Models\AdminAlert;
use App\Models\LearnerMail;
use App\Models\MailJournal;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Alerts\MailDigest;
use App\Support\Reminders\FollowUps;
use App\Support\Trial;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Relances espacées après l'essai et après la fin d'une formule payée (petits lots, un jour sur deux), et résumé
 * des e-mails envoyés au fondateur.
 */
class FollowUpSeriesTest extends TestCase
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
            'platform.follow_ups.sales.per_day' => 2,
            // Les invitations à laisser un avis ont leurs propres tests (ReviewInvitationsTest).
            'platform.reviews.emails_per_day' => 0,
            'alerts.emails' => ['fondateur@example.test'],
            'mail.default' => 'smtp',
            'mail.from.address' => 'equipe@example.test',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.username' => 'utilisateur-smtp',
            'mail.mailers.smtp.password' => 'mot-de-passe-smtp',
        ]);
    }

    private function lapsed(int $endedDaysAgo, array $attributes = []): User
    {
        $user = User::factory()->level(Level::A2)->create($attributes + ['email' => 'ancien'.random_int(1000, 9999).'@example.test']);
        Subscription::factory()->for($user)->forPlan('essentiel')->create([
            'status' => SubscriptionStatus::Expired,
            'starts_at' => now()->subDays(30 + $endedDaysAgo),
            'ends_at' => now()->subDays($endedDaysAgo),
        ]);

        return $user->fresh();
    }

    private function afterTrial(int $endedDaysAgo): User
    {
        $user = User::factory()->level(Level::A2)->create(['email' => 'essai'.random_int(1000, 9999).'@example.test']);
        $user->forceFill(['created_at' => now()->subDays($endedDaysAgo + 2)])->save();
        $user->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 300, 'level' => 'A2', 'status' => 'completed', 'ended_at' => now()->subDays($endedDaysAgo + 1)]);
        Trial::claim($user->fresh());
        $user->subscriptions()->update(['status' => SubscriptionStatus::Expired->value, 'ends_at' => now()->subDays($endedDaysAgo)]);

        return $user->fresh();
    }

    public function test_a_former_subscriber_gets_three_spaced_follow_ups_then_nothing(): void
    {
        $user = $this->lapsed(3);

        FollowUps::run();
        Mail::assertSent(FollowUpMail::class, function (FollowUpMail $mail) use ($user) {
            $html = html_entity_decode($mail->render(), ENT_QUOTES);

            return $mail->hasTo($user->email)
                && $mail->body['subject'] === 'On reprend vos séances ?'
                && str_contains($html, 'Renouveler ma formule Essentiel')
                && str_contains($html, 'rien n\'est prélevé automatiquement');
        });

        $this->travel(7)->days();
        FollowUps::run();
        Mail::assertSent(FollowUpMail::class, fn (FollowUpMail $mail) => $mail->body['subject'] === 'Ne laissez pas vos progrès s\'arrêter'
            && $mail->body['links'] !== []);

        $this->travel(11)->days();
        FollowUps::run();
        Mail::assertSent(FollowUpMail::class, fn (FollowUpMail $mail) => str_starts_with($mail->body['subject'], 'Un dernier message de')
            && str_contains($mail->render(), 'dernier message'));

        $this->travel(20)->days();
        FollowUps::run();
        Mail::assertSentCount(3);
        $this->assertSame(3, $user->learnerMails()->whereIn('campaign', LearnerMail::LAPSED)->count());
    }

    public function test_never_a_former_subscriber_who_renewed_or_has_a_payment_pending(): void
    {
        $renewed = $this->lapsed(5);
        Subscription::factory()->for($renewed)->forPlan('semaine')->create();

        FollowUps::run();
        Mail::assertNothingSent();
        $this->assertSame(0, $renewed->learnerMails()->count());
    }

    public function test_after_the_trial_two_more_follow_ups_go_out_in_small_batches_one_day_out_of_two(): void
    {
        // Trois personnes dont l'essai est fini depuis quatre jours, bilan déjà parti.
        $users = collect(range(1, 3))->map(function () {
            $user = $this->afterTrial(4);
            $user->learnerMails()->create(['campaign' => LearnerMail::TRIAL_ENDED, 'status' => LearnerMail::SENT, 'sent_at' => now()->subDays(4)]);

            return $user;
        });

        FollowUps::run();
        // Au plus deux par jour d'envoi.
        Mail::assertSentCount(2);
        Mail::assertSent(FollowUpMail::class, fn (FollowUpMail $mail) => str_starts_with($mail->body['subject'], 'Votre anglais, la suite avec')
            && str_contains($mail->render(), 'Pourquoi continuer maintenant'));

        // Le lendemain : jamais chaque jour.
        $this->travel(1)->days();
        FollowUps::run();
        Mail::assertSentCount(2);

        // Le surlendemain : la troisième personne.
        $this->travel(1)->days();
        FollowUps::run();
        Mail::assertSentCount(3);
        $this->assertSame(3, LearnerMail::query()->where('campaign', LearnerMail::TRIAL_ENDED_2)->where('status', LearnerMail::SENT)->count());
        $this->assertCount(3, $users);
    }

    public function test_every_email_to_a_learner_is_summed_up_for_the_founder_once(): void
    {
        $learner = User::factory()->create(['name' => 'Ama Kodjo', 'email' => 'ama@example.test']);
        // Un vrai envoi (sans simulation), pour que l'événement « e-mail parti » soit déclenché.
        Mail::swap(new MailManager($this->app));

        Mail::mailer('array')->to('ama@example.test')->send(new FollowUpMail($learner, [
            'subject' => 'On reprend vos séances ?', 'title' => 'Titre', 'lines' => ['Ligne'], 'button' => 'Bouton',
            'url' => route('dashboard'), 'review' => null, 'links' => [], 'label' => 'Formule terminée, première relance',
        ], FollowUps::stopUrl($learner)));

        $row = MailJournal::query()->sole();
        $this->assertSame($learner->id, $row->user_id);
        $this->assertSame('Relance : Formule terminée, première relance', $row->kind);

        // Les e-mails de l'équipe ne sont jamais notés.
        Mail::mailer('array')->to('fondateur@example.test')->send(new PaymentAlertMail(MailDigest::message(MailJournal::query()->get())));
        $this->assertSame(1, MailJournal::query()->count());

        Mail::fake();
        $this->assertSame(1, MailDigest::send());
        Mail::assertSent(PaymentAlertMail::class, fn (PaymentAlertMail $mail) => $mail->hasTo('fondateur@example.test')
            && str_contains($mail->render(), 'Ama Kodjo')
            && str_contains($mail->render(), 'On reprend vos séances'));
        $this->assertSame('mail_digest', AdminAlert::query()->sole()->kind);

        // Déjà résumé : rien de plus.
        $this->assertSame(0, MailDigest::send());
        Mail::assertSentCount(1);
    }
}
