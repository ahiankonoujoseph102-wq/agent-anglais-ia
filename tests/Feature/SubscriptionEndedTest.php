<?php

namespace Tests\Feature;

use App\Enums\ErrorStatus;
use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\PaymentAlertMail;
use App\Mail\SubscriptionEndedMail;
use App\Models\LearnerReminder;
use App\Models\PushSubscription;
use App\Models\Review;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Admin\Subscribers;
use App\Support\Alerts\PushResult;
use App\Support\Alerts\PushTransport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Fin d'une formule payée sans renouvellement : bilan de l'élève (faits seulement, jamais de faux compliment),
 * invitation à continuer et à donner un avis honnête (minutes offertes), au nom de son professeur ; l'administrateur
 * est prévenu et son bouton WhatsApp ouvre le même bilan.
 */
class SubscriptionEndedTest extends TestCase
{
    use RefreshDatabase;

    private EndedFakePush $push;

    protected function setUp(): void
    {
        parent::setUp();

        // Vendredi 6 novembre 2026, 15 heures à Lomé : la formule s'est terminée à 14 heures.
        $this->travelTo(CarbonImmutable::parse('2026-11-06 15:00:00', 'Africa/Lome'));
        Mail::fake();
        config([
            'alerts.emails' => ['admin@example.test'],
            'alerts.vapid.public_key' => 'PUBLIC-KEY-FICTIVE-0123456789',
            'alerts.vapid.private_key' => 'PRIVATE-KEY-SECRET-0123456789',
            'alerts.vapid.subject' => 'mailto:equipe@example.test',
            'mail.default' => 'smtp',
            'mail.from.address' => 'equipe@example.test',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.username' => 'utilisateur-smtp',
            'mail.mailers.smtp.password' => 'mot-de-passe-smtp',
        ]);
        $this->push = new EndedFakePush;
        $this->app->instance(PushTransport::class, $this->push);
    }

    private function learner(bool $worked = true): array
    {
        $user = User::factory()->create(['name' => 'Afi Mensah', 'email' => 'afi@example.test', 'welcomed_at' => now(), 'country' => 'TG']);
        $user->forceFill(['level' => Level::A2])->save();
        $subscription = Subscription::factory()->for($user)->forPlan('essentiel')->create([
            'starts_at' => CarbonImmutable::parse('2026-10-07 14:00:00', 'Africa/Lome'),
            'ends_at' => CarbonImmutable::parse('2026-11-06 14:00:00', 'Africa/Lome'),
            'status' => SubscriptionStatus::Expired,
        ]);

        if ($worked) {
            foreach ([[5, 2], [4, 2]] as $index => [$fluency, $grammar]) {
                $lesson = $user->lessons()->create(['date' => today(), 'started_at' => now()->subDays(10 + $index), 'ended_at' => now()->subDays(10 + $index), 'allowed_seconds' => 900, 'used_seconds' => 900, 'status' => LessonStatus::Completed]);
                $lesson->forceFill(['score_fluency' => $fluency, 'score_grammar' => $grammar, 'created_at' => now()->subDays(10 + $index)])->save();
            }
            $user->learnerErrors()->create(['wrong' => 'I have 20 years', 'correct' => "I'm 20 years old", 'explanation' => 'L\'âge se dit avec « be ».', 'status' => ErrorStatus::Open]);
        }

        // L'administrateur a un téléphone abonné aux alertes.
        PushSubscription::query()->forceCreate(['user_id' => User::factory()->admin()->create()->id, 'endpoint_hash' => hash('sha256', 'admin'), 'endpoint' => 'https://push.example.test/admin', 'public_key' => 'cle', 'auth_token' => 'auth', 'content_encoding' => 'aes128gcm']);

        return [$user->fresh(), $subscription->fresh()];
    }

    public function test_a_learner_who_worked_receives_a_true_report_once_and_the_admin_is_told(): void
    {
        [$user] = $this->learner();

        Artisan::call('learners:remind');
        Artisan::call('learners:remind');

        Mail::assertSent(SubscriptionEndedMail::class, 1);
        $mail = Mail::sent(SubscriptionEndedMail::class)->first();
        $this->assertTrue($mail->hasTo('afi@example.test'));
        $html = html_entity_decode($mail->render(), ENT_QUOTES);
        $this->assertStringContainsString('Votre formule Essentiel s\'est terminée le 6 novembre 2026', $html);
        $this->assertStringContainsString('Bravo pour votre travail : 2 séances, environ 30 minutes à parler anglais.', $html);
        $this->assertStringContainsString('Votre niveau : A2 (Élémentaire).', $html);
        $this->assertStringContainsString('Votre point fort : aisance. À travailler en priorité : grammaire.', $html);
        $this->assertStringContainsString('on ne dit pas « I have 20 years », on dit « I\'m 20 years old »', $html);
        $this->assertStringContainsString('Continuer avec une nouvelle formule', $html);
        $this->assertStringContainsString(route('payment.create', 'essentiel'), $html);
        $this->assertStringContainsString('Donner mon avis', $html);
        $this->assertStringContainsString('15 minutes de séance vous sont offertes', $html);
        $this->assertStringContainsString('quelle que soit votre note', $html);
        $this->assertStringContainsString('Teacher Joe', $html);
        $this->assertStringContainsString('Rien n\'est prélevé automatiquement', $html);

        $this->assertSame(1, LearnerReminder::query()->where(['user_id' => $user->id, 'kind' => LearnerReminder::ENDED])->count());

        Mail::assertSent(PaymentAlertMail::class, fn (PaymentAlertMail $alert) => $alert->alert->kind === 'subscription_ended' && $alert->hasTo('admin@example.test'));
        $this->assertStringNotContainsString(substr($user->phone, -6), Mail::sent(PaymentAlertMail::class)->first()->render());
    }

    public function test_no_false_praise_for_a_learner_who_did_not_work(): void
    {
        $this->learner(worked: false);

        Artisan::call('learners:remind');

        $html = html_entity_decode(Mail::sent(SubscriptionEndedMail::class)->first()->render(), ENT_QUOTES);
        $this->assertStringNotContainsString('Bravo', $html);
        $this->assertStringContainsString('Nous n\'avons pas eu l\'occasion de travailler ensemble', $html);
        // Aucune évaluation ni séance : l'avis ne peut pas encore être donné, il n'est pas demandé.
        $this->assertStringNotContainsString('Donner mon avis', $html);
    }

    public function test_nothing_when_renewed_already_reviewed_or_too_late(): void
    {
        // Déjà renouvelé : un paiement en vérification.
        [$user, $subscription] = $this->learner();
        $user->payments()->forceCreate([
            'plan' => 'essentiel', 'plan_name' => 'Essentiel', 'daily_minutes' => 15, 'duration_days' => 30, 'amount' => 7500, 'currency' => 'XOF',
            'country' => 'TG', 'operator' => 'flooz', 'operator_name' => 'Flooz', 'recipient_number' => '+22890000000', 'proof_path' => 'p.jpg', 'status' => PaymentStatus::Pending,
        ]);
        Artisan::call('learners:remind');
        Mail::assertNotSent(SubscriptionEndedMail::class);

        // Trois jours plus tard, plus rien n'est envoyé pour cette formule.
        $user->payments()->delete();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 15:00:00', 'Africa/Lome'));
        Artisan::call('learners:remind');
        Mail::assertNotSent(SubscriptionEndedMail::class);
    }

    public function test_the_review_is_not_asked_again_once_given(): void
    {
        [$user] = $this->learner();
        $review = new Review(['rating' => 5, 'body' => 'Très bonnes séances, merci beaucoup !']);
        $review->forceFill(['user_id' => $user->id, 'status' => ReviewStatus::Published, 'kind' => 'subscriber'])->save();

        Artisan::call('learners:remind');

        $this->assertStringNotContainsString('Donner mon avis', Mail::sent(SubscriptionEndedMail::class)->first()->render());
    }

    public function test_the_admin_whatsapp_button_opens_the_same_report(): void
    {
        [, $subscription] = $this->learner();

        $message = Subscribers::message($subscription->load('user'));

        $this->assertStringContainsString('Votre formule Essentiel s\'est terminée le 6 novembre 2026.', $message);
        $this->assertStringContainsString('Bravo pour votre travail : 2 séances', $message);
        $this->assertStringContainsString('À travailler en priorité : grammaire.', $message);
        $this->assertStringContainsString(route('payment.plans'), $message);
        $this->assertStringContainsString(route('reviews.edit'), $message);
        $this->assertStringContainsString('15 minutes de séance vous sont offertes', $message);
    }
}

class EndedFakePush implements PushTransport
{
    /** @var list<array<string, mixed>> */
    public array $sent = [];

    public function send(PushSubscription $subscription, array $payload): PushResult
    {
        $this->sent[] = $payload;

        return PushResult::sent();
    }
}
