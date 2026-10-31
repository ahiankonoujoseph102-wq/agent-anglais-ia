<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Mail\LessonReminderMail;
use App\Models\LearnerPushSubscription;
use App\Models\LearnerReminder;
use App\Models\PushSubscription;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Alerts\PushResult;
use App\Support\Alerts\PushTransport;
use App\Support\Plan;
use App\Support\Reminders\LessonReminders;
use App\Support\Teachers\Teachers;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Fin d'abonnement : la veille du dernier jour, un e-mail et une notification au nom du professeur (lien vers le
 * paiement de la formule) ; à l'avant-dernier et au dernier jour, le professeur le rappelle UNE fois au début de la
 * séance du jour, en français, avec bienveillance, avant la leçon. Jamais pour un abonnement déjà renouvelé.
 */
class SubscriptionEndReminderTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    private FakeRenewalPush $push;

    protected function setUp(): void
    {
        parent::setUp();

        // Jeudi 5 novembre 2026, 11 heures à Lomé : veille du dernier jour (fin le 6 novembre à 14 heures).
        $this->travelTo(CarbonImmutable::parse('2026-11-05 11:00:00', 'Africa/Lome'));
        Mail::fake();
        config([
            'services.gemini.key' => 'cle-secrete-du-serveur',
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
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton']), '*/interactions' => Http::response([], 500)]);
        $this->push = new FakeRenewalPush;
        $this->app->instance(PushTransport::class, $this->push);
    }

    private function learner(string $endsAt = '2026-11-06 14:00:00'): User
    {
        $user = User::factory()->level(Level::A1)->create(['name' => 'Afi Mensah', 'email' => fake()->unique()->safeEmail(), 'welcomed_at' => now(), 'country' => 'TG']);
        Subscription::factory()->for($user)->forPlan('essentiel')->create([
            'starts_at' => CarbonImmutable::parse($endsAt, 'Africa/Lome')->subDays(30),
            'ends_at' => CarbonImmutable::parse($endsAt, 'Africa/Lome'),
        ]);
        $this->buildProgram($user, Level::A1);

        $device = new LearnerPushSubscription([
            'endpoint_hash' => LearnerPushSubscription::hashOf('https://push.example.test/'.$user->id),
            'endpoint' => 'https://push.example.test/'.$user->id,
            'public_key' => 'cle', 'auth_token' => 'auth', 'content_encoding' => 'aes128gcm',
        ]);
        $device->user_id = $user->id;
        $device->save();

        return $user->fresh();
    }

    private function remind(): void
    {
        Artisan::call('learners:remind');
    }

    private function renewPushes(): array
    {
        return array_values(array_filter($this->push->sent, fn ($payload) => $payload['tag'] === 'renouvellement'));
    }

    public function test_the_eve_of_the_last_day_brings_one_email_and_one_notification_signed_by_the_teacher(): void
    {
        $user = $this->learner();

        $this->remind();
        $this->remind();

        $pushes = $this->renewPushes();
        $this->assertCount(1, $pushes);
        $this->assertSame('Votre abonnement se termine demain', $pushes[0]['title']);
        $this->assertStringContainsString('Renouvelez-le pour continuer vos progrès sans interruption.', $pushes[0]['body']);
        $this->assertStringEndsWith('Teacher Joe', $pushes[0]['body']);
        $this->assertSame('/abonnement/essentiel', $pushes[0]['url']);

        Mail::assertSent(LessonReminderMail::class, fn ($mail) => $mail->kind === LearnerReminder::RENEW && $mail->hasTo($user->email));
        $this->assertSame(1, LearnerReminder::query()->where(['user_id' => $user->id, 'kind' => 'renew'])->count());

        $html = (new LessonReminderMail($user, Teachers::forUser($user), LearnerReminder::RENEW, ''))->render();
        $this->assertStringContainsString('se termine demain', $html);
        $this->assertStringContainsString('Renouvelez-le pour continuer vos progrès sans interruption', $html);
        $this->assertStringContainsString('Mixx by Yas ou Flooz', $html);
        $this->assertStringContainsString('Renouveler mon abonnement', $html);
        $this->assertStringContainsString(route('payment.create', 'essentiel'), $html);
        $this->assertStringContainsString('Teacher Joe', $html);
        $this->assertStringContainsString('Rien n\'est prélevé automatiquement', $html);
    }

    public function test_the_renewal_reminder_is_signed_by_teacher_rose_for_her_learners(): void
    {
        $user = $this->learner();
        $user->forceFill(['teacher' => 'rose'])->save();

        $this->remind();

        $this->assertStringEndsWith('Teacher Rose', $this->renewPushes()[0]['body']);
    }

    public function test_no_renewal_reminder_on_another_day_too_early_or_once_renewed(): void
    {
        // Deux jours avant le dernier jour : rien.
        $this->learner('2026-11-07 14:00:00');
        $this->remind();
        $this->assertSame([], $this->renewPushes());

        // Trop tôt le matin : rien encore.
        $this->travelTo(CarbonImmutable::parse('2026-11-05 08:00:00', 'Africa/Lome'));
        $early = $this->learner();
        $this->remind();
        $this->assertSame([], $this->renewPushes());

        // Déjà renouvelé : une formule payée qui finit plus tard.
        $this->travelTo(CarbonImmutable::parse('2026-11-05 11:00:00', 'Africa/Lome'));
        Subscription::factory()->for($early)->forPlan('essentiel')->create([
            'starts_at' => CarbonImmutable::parse('2026-11-06 14:00:00', 'Africa/Lome'),
            'ends_at' => CarbonImmutable::parse('2026-12-06 14:00:00', 'Africa/Lome'),
        ]);
        $this->assertNull(LessonReminders::daysBeforeLastDay($early->fresh()));
        $this->remind();
        $this->assertSame([], $this->renewPushes());
        Mail::assertNotSent(LessonReminderMail::class, fn ($mail) => $mail->kind === LearnerReminder::RENEW);
    }

    public function test_a_payment_being_checked_counts_as_renewed(): void
    {
        $user = $this->learner();
        $plan = Plan::find('essentiel');
        $user->payments()->create([
            'plan' => $plan->key, 'plan_name' => $plan->name, 'daily_minutes' => $plan->dailyMinutes,
            'duration_days' => $plan->durationDays, 'amount' => $plan->price, 'currency' => 'FCFA',
            'country' => 'TG', 'operator' => 'flooz', 'operator_name' => 'Flooz',
            'recipient_number' => '+228 90 00 00 22', 'proof_path' => 'payment-proofs/x.png',
        ]);

        $this->assertNull(LessonReminders::daysBeforeLastDay($user->fresh()));
        $this->remind();
        $this->assertSame([], $this->renewPushes());
    }

    public function test_the_teacher_reminds_it_once_at_the_start_of_the_lesson_on_the_last_two_days(): void
    {
        $user = $this->learner();

        $start = $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json('cues.start');
        $this->assertStringContainsString('(SUBSCRIPTION)', $start);
        $this->assertStringContainsString('« Votre abonnement se termine demain. »', $start);
        $this->assertStringContainsString('present today\'s lesson', $start);
        $this->assertStringContainsString('never invent progress', $start);
        $this->assertStringContainsString('no false urgency', $start);
        $this->assertStringContainsString('switch back to English', $start);

        // Le dernier jour : « aujourd'hui ».
        $this->travelTo(CarbonImmutable::parse('2026-11-06 09:00:00', 'Africa/Lome'));
        $this->assertSame(0, LessonReminders::daysBeforeLastDay($user->fresh()));
        $this->assertStringContainsString('« Votre abonnement se termine aujourd\'hui. »', LessonReminders::sessionNote($user->fresh()));

        // Trois jours avant : rien.
        $this->travelTo(CarbonImmutable::parse('2026-11-03 09:00:00', 'Africa/Lome'));
        $this->assertStringNotContainsString('(SUBSCRIPTION)', LessonReminders::sessionNote($user->fresh()));
    }

    public function test_a_resumed_lesson_does_not_repeat_the_reminder(): void
    {
        $user = $this->learner();

        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        $again = $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json('cues');

        $this->assertStringNotContainsString('(SUBSCRIPTION)', json_encode($again, JSON_UNESCAPED_UNICODE));
    }

    public function test_joe_and_rose_say_the_same_reminder(): void
    {
        $user = $this->learner();
        $joe = LessonReminders::sessionNote($user->fresh());

        $user->forceFill(['teacher' => 'rose'])->save();

        $this->assertNotSame('', $joe);
        $this->assertSame($joe, LessonReminders::sessionNote($user->fresh()));
    }

    public function test_no_renewal_talk_for_a_granted_access(): void
    {
        $user = User::factory()->level(Level::A1)->create(['country' => 'TG', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create([
            'starts_at' => now()->subDays(6), 'ends_at' => CarbonImmutable::parse('2026-11-06 14:00:00', 'Africa/Lome'),
            'granted_days' => 7, 'price' => 0,
        ]);

        $this->assertNull(LessonReminders::daysBeforeLastDay($user->fresh()));
    }
}

class FakeRenewalPush implements PushTransport
{
    /** @var list<array<string, mixed>> */
    public array $sent = [];

    public function send(PushSubscription $subscription, array $payload): PushResult
    {
        $this->sent[] = $payload;

        return PushResult::sent();
    }
}
