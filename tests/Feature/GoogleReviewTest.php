<?php

namespace Tests\Feature;

use App\Mail\FollowUpMail;
use App\Models\LearnerMail;
use App\Models\LearnerPushSubscription;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Alerts\PushResult;
use App\Support\Alerts\PushTransport;
use App\Support\Reminders\FollowUps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GoogleReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.exemple.tg',
            'mail.mailers.smtp.username' => 'compte',
            'mail.mailers.smtp.password' => 'secret',
            'mail.from.address' => 'equipe@exemple.tg',
        ]);
    }

    public function test_the_simulation_sends_nothing(): void
    {
        User::factory()->create(['email' => 'kofi@exemple.tg']);

        $this->artisan('learners:google-review')->assertSuccessful();

        $this->assertSame(0, LearnerMail::query()->count());
    }

    public function test_every_learner_is_asked_once_and_never_the_team_or_opted_out(): void
    {
        $learner = User::factory()->create(['email' => 'kofi@exemple.tg']);
        $withoutEmail = User::factory()->create(['email' => null]);
        $optedOut = User::factory()->create(['email' => 'ama@exemple.tg', 'follow_up_opt_out_at' => now()]);
        $admin = User::factory()->admin()->create(['email' => 'admin@exemple.tg']);

        $this->artisan('learners:google-review --force')->assertSuccessful();
        $this->artisan('learners:google-review --force')->assertSuccessful();

        $asked = LearnerMail::query()->where('campaign', LearnerMail::GOOGLE_REVIEW)->pluck('user_id')->all();
        sort($asked);
        $this->assertSame([$learner->id, $withoutEmail->id], $asked);
        $this->assertNotContains($optedOut->id, $asked);
        $this->assertNotContains($admin->id, $asked);
    }

    public function test_the_email_links_to_google_without_any_reward(): void
    {
        Mail::fake();
        $learner = User::factory()->create(['email' => 'kofi@exemple.tg']);

        $this->artisan('learners:google-review --force')->assertSuccessful();
        FollowUps::sendPending();

        Mail::assertSent(FollowUpMail::class, function (FollowUpMail $mail) use ($learner) {
            $html = $mail->render();

            return $mail->hasTo($learner->email)
                && str_contains($html, 'https://g.page/r/CfH06o_5Vb82EAI/review')
                && str_contains($html, 'Laisser mon avis sur Google')
                && ! str_contains($html, 'minutes de cours offertes')
                && ! str_contains($html, '★');
        });
        $this->assertSame(LearnerMail::SENT, LearnerMail::query()->value('status'));
    }

    public function test_the_notification_link_redirects_to_google(): void
    {
        $this->get('/avis-google')->assertRedirect('https://g.page/r/CfH06o_5Vb82EAI/review');
    }

    public function test_a_notification_is_sent_to_the_learner_phones_at_launch(): void
    {
        config([
            'alerts.vapid.public_key' => 'PUBLIC-KEY-FICTIVE-0123456789',
            'alerts.vapid.private_key' => 'PRIVATE-KEY-SECRET-0123456789',
            'alerts.vapid.subject' => 'mailto:equipe@exemple.tg',
        ]);
        $push = new FakeGooglePush;
        $this->app->instance(PushTransport::class, $push);
        $learner = User::factory()->create(['email' => null]);
        $device = new LearnerPushSubscription([
            'endpoint_hash' => LearnerPushSubscription::hashOf('https://push.exemple.tg/1'),
            'endpoint' => 'https://push.exemple.tg/1',
            'public_key' => 'cle', 'auth_token' => 'auth', 'content_encoding' => 'aes128gcm',
        ]);
        $device->user_id = $learner->id;
        $device->save();

        $this->artisan('learners:google-review --force')->assertSuccessful();

        $this->assertCount(1, $push->sent);
        $this->assertSame('/avis-google', $push->sent[0]['url']);
        $this->assertStringContainsString('Google', $push->sent[0]['body']);
    }
}

/** Faux transport de notifications : garde ce qu'il aurait envoyé, n'envoie rien. */
class FakeGooglePush implements PushTransport
{
    /** @var list<array<string, mixed>> */
    public array $sent = [];

    public function send(PushSubscription $subscription, array $payload): PushResult
    {
        $this->sent[] = $payload;

        return PushResult::sent();
    }
}
