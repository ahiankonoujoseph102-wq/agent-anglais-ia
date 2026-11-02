<?php

namespace Tests\Feature;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Mail\LessonReminderMail;
use App\Models\LearnerPushSubscription;
use App\Models\LearnerReminder;
use App\Models\PushSubscription;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AccessGrant;
use App\Support\Alerts\PushResult;
use App\Support\Alerts\PushTransport;
use App\Support\Gemini\TeacherSetup;
use App\Support\MailQuota;
use App\Support\Plan;
use App\Support\Reminders\LessonReminders;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Suivi des élèves payants : e-mail obligatoire et demandé en séance, heure des séances, rappels (30 minutes et
 * 1 minute avant), relance d'une séance manquée, programme peu suivi dit au début de la séance, en français.
 */
class LearnerFollowUpTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    private FakeLearnerPush $push;

    protected function setUp(): void
    {
        parent::setUp();

        // Mercredi 4 novembre 2026, 17 h 30 à Lomé (UTC).
        $this->travelTo(CarbonImmutable::parse('2026-11-04 17:30:00', 'Africa/Lome'));
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
        $this->push = new FakeLearnerPush;
        $this->app->instance(PushTransport::class, $this->push);
    }

    private function learner(?string $email = 'afi@example.test', array $answers = []): User
    {
        $user = User::factory()->level(Level::A1)->create(['name' => 'Afi Mensah', 'email' => $email, 'welcomed_at' => now(), 'country' => 'TG']);
        Subscription::factory()->for($user)->forPlan('essentiel')->create(['starts_at' => now()->subDays(10)]);
        $this->buildProgram($user, Level::A1, $answers);

        return $user->fresh();
    }

    private function device(User $user): LearnerPushSubscription
    {
        $device = new LearnerPushSubscription([
            'endpoint_hash' => LearnerPushSubscription::hashOf('https://push.example.test/'.$user->id),
            'endpoint' => 'https://push.example.test/'.$user->id,
            'public_key' => 'cle', 'auth_token' => 'auth', 'content_encoding' => 'aes128gcm',
        ]);
        $device->user_id = $user->id;
        $device->save();

        return $device;
    }

    private function heldLesson(User $user, string $date): void
    {
        $user->lessons()->create([
            'subscription_id' => $user->activeSubscription->id, 'date' => $date, 'allowed_seconds' => 900, 'used_seconds' => 600,
            'started_at' => now(), 'ended_at' => now(), 'status' => LessonStatus::Completed,
        ]);
    }

    private function remind(): void
    {
        Artisan::call('learners:remind');
    }

    public function test_a_reminder_goes_thirty_minutes_before_by_email_and_notification_once(): void
    {
        $user = $this->learner();
        $this->device($user);

        $this->remind();
        $this->remind();

        $this->assertCount(1, $this->push->sent);
        $this->assertSame('Teacher Joe vous attend à 18 heures', $this->push->sent[0]['title']);
        $this->assertSame('/seance', $this->push->sent[0]['url']);
        Mail::assertSent(LessonReminderMail::class, 1);
        Mail::assertSent(LessonReminderMail::class, fn ($mail) => $mail->hasTo('afi@example.test') && $mail->kind === LearnerReminder::SOON);
        $this->assertDatabaseHas('learner_reminders', ['user_id' => $user->id, 'kind' => 'soon', 'push_status' => 'sent', 'mail_status' => 'sent']);
    }

    public function test_the_last_reminder_one_minute_before_is_a_notification_only(): void
    {
        $user = $this->learner();
        $this->device($user);
        $this->travelTo(CarbonImmutable::parse('2026-11-04 17:59:00', 'Africa/Lome'));

        $this->remind();

        $this->assertSame('C\'est l\'heure de votre séance !', $this->push->sent[0]['title']);
        Mail::assertNothingSent();
        $this->assertDatabaseHas('learner_reminders', ['user_id' => $user->id, 'kind' => 'now', 'mail_status' => 'skipped']);
    }

    public function test_a_missed_lesson_is_followed_up_two_hours_later_once_but_not_after_a_lesson(): void
    {
        $user = $this->learner();
        $done = $this->learner('kofi@example.test');
        $this->heldLesson($done, '2026-11-04');
        $this->travelTo(CarbonImmutable::parse('2026-11-04 20:03:00', 'Africa/Lome'));

        $this->remind();
        $this->remind();

        Mail::assertSent(LessonReminderMail::class, 1);
        Mail::assertSent(LessonReminderMail::class, fn ($mail) => $mail->hasTo('afi@example.test') && $mail->kind === LearnerReminder::MISSED);
        $this->assertDatabaseMissing('learner_reminders', ['user_id' => $done->id]);
    }

    public function test_no_reminder_on_a_day_off_or_without_a_time(): void
    {
        $dayOff = $this->learner('a@example.test', ['study_days' => [1, 2]]);
        $noTime = $this->learner('b@example.test', ['study_time' => null]);

        $this->remind();

        Mail::assertNothingSent();
        $this->assertSame(0, LearnerReminder::query()->count());
        $this->assertTrue(LessonReminders::follows($dayOff));
        $this->assertNull(LessonReminders::studyTime($noTime));
    }

    /**
     * Demande du fondateur (2 novembre 2026) : un accès OFFERT reçoit aussi les rappels de séance (e-mail et
     * notification) ; la séance d'essai gratuite, non (elle a ses propres relances). L'e-mail reste facultatif
     * pour un accès offert.
     */
    public function test_a_granted_access_gets_reminders_but_not_a_free_trial(): void
    {
        $granted = User::factory()->level(Level::A1)->create(['email' => 'c@example.test', 'country' => 'TG', 'welcomed_at' => now()]);
        AccessGrant::offer($granted, User::factory()->admin()->create(), Plan::find('essentiel'), 7, null);
        $this->buildProgram($granted, Level::A1);
        $this->device($granted);

        $trial = User::factory()->level(Level::A1)->create(['email' => 'd@example.test', 'country' => 'TG']);
        Subscription::factory()->for($trial)->forPlan('essentiel')->create(['is_trial' => true, 'granted_days' => 2, 'price' => 0]);
        $this->buildProgram($trial, Level::A1);

        $this->remind();

        $this->assertSame([$granted->id], LearnerReminder::query()->pluck('user_id')->all());
        Mail::assertSent(LessonReminderMail::class, fn ($mail) => $mail->hasTo('c@example.test'));
        $this->assertCount(1, $this->push->sent);
        $this->assertFalse(LessonReminders::follows($granted->fresh()));
        $this->assertTrue(LessonReminders::remindable($granted->fresh()));
        $this->assertFalse(LessonReminders::remindable($trial->fresh()));
    }

    /**
     * Une séance manquée (même une seule) : à la séance suivante, avant l'échauffement, le professeur le dit en
     * français, conseille de ne plus la manquer, puis fait la leçon. Une fois par séance manquée.
     */
    public function test_a_single_missed_lesson_is_mentioned_once_at_the_next_lesson(): void
    {
        $user = $this->learner();
        $user->activeProgram()->first()->forceFill(['created_at' => now()->subDays(10)])->save();
        // Tous les jours de travail : tenue lundi 2 et mardi... manquée seulement hier (mardi 3).
        foreach (['2026-10-29', '2026-10-30', '2026-10-31', '2026-11-01', '2026-11-02'] as $date) {
            $this->heldLesson($user, $date);
        }

        $this->assertSame('2026-11-03', LessonReminders::lastMissedDay($user)?->toDateString());
        $this->assertFalse(LessonReminders::missesOften($user));

        $start = $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json('cues.start');
        $this->assertStringContainsString('« Vous avez manqué votre séance hier. »', $start);
        $this->assertStringContainsString('try not to miss it again', $start);
        $this->assertStringContainsString('« Heure de mes séances »', $start);
        $this->assertStringNotContainsString('ne suivez pas souvent', $start);

        // Une seule fois pour cette séance manquée.
        $this->assertSame('', LessonReminders::sessionNote($user->fresh()));
    }

    public function test_nothing_is_said_when_the_last_lesson_was_held_or_on_a_granted_access_it_is_said_too(): void
    {
        $user = $this->learner();
        $user->activeProgram()->first()->forceFill(['created_at' => now()->subDays(10)])->save();
        $this->heldLesson($user, '2026-11-03');
        $this->assertNull(LessonReminders::lastMissedDay($user));

        $granted = User::factory()->level(Level::A1)->create(['email' => 'g@example.test', 'country' => 'TG', 'welcomed_at' => now()]);
        $this->travelTo(now()->subDays(5));
        AccessGrant::offer($granted, User::factory()->admin()->create(), Plan::find('essentiel'), 30, null);
        $this->buildProgram($granted, Level::A1);
        $this->travelBack();
        $this->travelTo(CarbonImmutable::parse('2026-11-04 17:30:00', 'Africa/Lome'));

        // Accès offert, aucune séance depuis cinq jours : ses jours manqués comptent aussi (message « programme peu suivi »).
        $this->assertGreaterThanOrEqual(3, LessonReminders::missedDays($granted->fresh()));
        $this->assertStringContainsString('(PROGRAMME)', LessonReminders::sessionNote($granted->fresh()));
    }

    public function test_reminder_emails_keep_a_reserve_for_reviews_alerts_and_videos(): void
    {
        $user = $this->learner();
        config(['mail.daily_limit' => 300, 'platform.reminders.mail_reserve' => 300]);

        $this->assertFalse(MailQuota::allowsReminder());
        $this->remind();

        Mail::assertNothingSent();
        $this->assertDatabaseHas('learner_reminders', ['user_id' => $user->id, 'kind' => 'soon', 'mail_status' => 'quota']);
    }

    public function test_learner_phones_are_kept_apart_from_admin_alert_phones(): void
    {
        $user = $this->learner();

        $this->actingAs($user)->postJson(route('reminders.device'), [
            'endpoint' => 'https://push.example.test/abc', 'keys' => ['p256dh' => 'cle', 'auth' => 'auth'],
        ])->assertOk();

        $this->assertSame(1, LearnerPushSubscription::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, PushSubscription::query()->count());

        $this->actingAs($user)->deleteJson(route('reminders.device.remove'), ['endpoint' => 'https://push.example.test/abc'])->assertOk();
        $this->assertSame(0, LearnerPushSubscription::query()->count());
    }

    public function test_the_learner_chooses_the_time_of_their_lessons_in_my_space(): void
    {
        $user = $this->learner(answers: ['study_time' => null]);

        $this->actingAs($user)->get('/espace')
            ->assertSee('Heure de mes séances')
            ->assertSee('Vous n\'avez pas encore choisi l\'heure de vos séances.', false)
            ->assertSee('Recevoir mes rappels sur ce téléphone');

        $this->actingAs($user)->put(route('reminders.time'), ['study_time' => '07:30'])->assertRedirect(route('dashboard').'#rappels');
        $this->assertSame('07:30', LessonReminders::studyTime($user->fresh()));

        $this->actingAs($user)->get('/espace')->assertSee('<strong>7 heures 30</strong>', false);
    }

    public function test_the_email_is_required_for_a_paying_subscriber_only(): void
    {
        $paying = $this->learner();
        $free = User::factory()->create(['email' => 'libre@example.test']);

        $this->actingAs($paying)->put(route('account.update'), ['email' => ''])->assertSessionHasErrors('email');
        $this->assertSame('afi@example.test', $paying->fresh()->email);
        $this->actingAs($paying)->get(route('account.edit'))->assertSee('Obligatoire pour les abonnés');

        $this->actingAs($free)->put(route('account.update'), ['email' => ''])->assertSessionHasNoErrors();
        $this->assertNull($free->fresh()->email);
    }

    public function test_the_teacher_asks_for_the_email_in_french_before_the_lesson_and_the_field_is_on_the_screen(): void
    {
        $user = $this->learner(null);

        $this->actingAs($user)->get('/seance')->assertSee('id="email-ask"', false)->assertSee('Votre adresse e-mail');
        $response = $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        $start = $response->json('cues.start');

        $this->assertStringContainsString('BEFORE the WARM-UP and the lesson, in French', $start);
        $this->assertStringContainsString('(EMAIL)', $start);
        $this->assertStringContainsString('Vous n\'avez pas besoin de quitter la séance.', $start);
        $this->assertStringNotContainsString('(PROGRAMME)', $start);
        $this->assertStringContainsString('saved their e-mail address', $response->json('cues.email_saved'));

        $this->actingAs($user)->postJson(route('lesson.email'), ['email' => ' Afi@Example.TEST '])->assertOk()->assertJson(['saved' => true]);
        $this->assertSame('afi@example.test', $user->fresh()->email);
        $this->actingAs($user->fresh())->get('/seance')->assertDontSee('id="email-ask"', false);
    }

    public function test_a_learner_who_often_misses_hears_it_kindly_once_a_week(): void
    {
        $user = $this->learner();
        $user->activeProgram()->first()->forceFill(['created_at' => now()->subDays(10)])->save();
        // Une seule séance tenue sur les 7 derniers jours de travail (tous les jours) : 6 jours manqués.
        $this->heldLesson($user, '2026-11-02');

        $this->assertSame(6, LessonReminders::missedDays($user));
        $this->assertTrue(LessonReminders::missesOften($user));

        $start = $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json('cues.start');
        $this->assertStringContainsString('J\'ai constaté que vous ne suivez pas souvent votre programme.', $start);
        $this->assertStringContainsString('« Heure de mes séances »', $start);
        $this->assertStringNotContainsString('(EMAIL)', $start);

        // Une fois par semaine au plus.
        $this->assertSame('', LessonReminders::sessionNote($user->fresh()));
        Cache::flush();
        $this->assertNotSame('', LessonReminders::sessionNote($user->fresh()));
    }

    public function test_nothing_changes_at_the_start_of_a_lesson_when_all_is_well(): void
    {
        $user = $this->learner();

        $start = $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json('cues.start');

        $this->assertStringNotContainsString('BEFORE the WARM-UP and the lesson', $start);
        $this->assertStringContainsString('STOP: wait for their answer. Greeting, duration and question in simple ENGLISH; only HOW TO WORK WITH ME (and any French note below) in French. Do not announce the topic and do not teach anything in this turn. Then follow the WARM-UP', $start);
    }

    public function test_serious_matters_are_said_in_french(): void
    {
        $instructions = TeacherSetup::lesson($this->learner(), 15, null, null, 3)['systemInstruction']['parts'][0]['text'];

        $this->assertStringContainsString('SERIOUS MATTERS ARE SAID IN FRENCH', $instructions);
        $this->assertStringContainsString('then go back to English for the lesson', $instructions);
    }
}

/** Faux transport de notifications : garde ce qu'il aurait envoyé, n'envoie rien. */
class FakeLearnerPush implements PushTransport
{
    /** @var list<array<string, mixed>> */
    public array $sent = [];

    public function send(PushSubscription $subscription, array $payload): PushResult
    {
        $this->sent[] = $payload;

        return PushResult::sent();
    }
}
