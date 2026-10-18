<?php

namespace Tests\Feature\Admin;

use App\Enums\SubscriptionStatus;
use App\Mail\PaymentAlertMail;
use App\Models\AdminAlert;
use App\Models\Payment;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\WhatsappPaymentClick;
use App\Support\Alerts\AdminAlerts;
use App\Support\Alerts\PushResult;
use App\Support\Alerts\PushTransport;
use App\Support\Alerts\Redact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ConfiguresPayments;
use Tests\TestCase;

/**
 * Alertes de paiement : notification (faux transport, aucun envoi réel) et e-mail (Mail::fake).
 */
class PaymentAlertsTest extends TestCase
{
    use ConfiguresPayments;
    use RefreshDatabase;

    private FakePush $push;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.private_disk' => null, 'filesystems.default' => 'local']);
        Storage::fake('local');
        $this->freezeSecond();
        $this->configurePayments();
        $this->fakeProofReader($this->proofReading());
        Mail::fake();

        config([
            'alerts.emails' => ['admin@example.test'],
            'alerts.vapid.public_key' => 'PUBLIC-KEY-FICTIVE-0123456789',
            'alerts.vapid.private_key' => 'PRIVATE-KEY-SECRET-0123456789',
            'alerts.vapid.subject' => 'mailto:admin@example.test',
            'mail.default' => 'smtp',
            'mail.from.address' => 'expediteur@example.test',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.username' => 'utilisateur-smtp',
            'mail.mailers.smtp.password' => 'MOT-DE-PASSE-SMTP-SECRET',
        ]);

        $this->push = new FakePush;
        $this->app->instance(PushTransport::class, $this->push);
    }

    private function subscribeDevice(string $name = 'Android, Chrome'): PushSubscription
    {
        $admin = User::factory()->admin()->create();
        $subscription = new PushSubscription([
            'endpoint_hash' => PushSubscription::hashOf('https://push.example.test/'.$name),
            'endpoint' => 'https://push.example.test/'.$name,
            'public_key' => 'cle-publique-appareil',
            'auth_token' => 'secret-appareil',
            'device' => $name,
        ]);
        $subscription->user_id = $admin->id;
        $subscription->save();

        return $subscription;
    }

    private function sendCapture(?User $user = null): User
    {
        $user ??= User::factory()->create(['name' => 'Kossi Mensah']);
        $this->actingAs($user)->post('/abonnement/essentiel', [
            'country' => 'TG',
            'operator' => 'flooz',
            'proof' => $this->proofImage('capture.png', 640 + $user->id),
        ])->assertRedirect();

        return $user;
    }

    public function test_a_capture_that_opens_the_provisional_access_alerts_both_channels(): void
    {
        $this->subscribeDevice();
        $user = $this->sendCapture();

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(SubscriptionStatus::Provisional, $payment->subscription->status);

        $this->assertCount(1, $this->push->sent);
        $payload = $this->push->sent[0];
        $this->assertStringContainsString('Nouveau paiement : 7', $payload['title']);
        $this->assertStringContainsString('Kossi Mensah', $payload['body']);
        $this->assertStringContainsString('accès provisoire ouvert', $payload['body']);
        $this->assertStringContainsString('/admin/paiements#paiement-'.$payment->id, $payload['url']);

        Mail::assertSent(PaymentAlertMail::class, function (PaymentAlertMail $mail) use ($payment) {
            $this->assertTrue($mail->hasTo('admin@example.test'));
            $this->assertSame('Voir le paiement', $mail->alert->buttonLabel);
            $mail->assertSeeInHtml('Kossi Mensah');
            $mail->assertSeeInHtml('Togo');
            $mail->assertSeeInHtml('Essentiel');
            $mail->assertSeeInHtml('Flooz');
            $mail->assertSeeInHtml('Accès provisoire de 24 heures ouvert');
            $mail->assertSeeInHtml('/admin/paiements#paiement-'.$payment->id);

            return true;
        });

        $this->assertSame(['mail' => 'sent', 'push' => 'sent'], AdminAlert::query()->orderBy('channel')->pluck('status', 'channel')->all());
        $this->assertNotNull($user);
    }

    public function test_a_capture_that_waits_for_verification_says_why_in_plain_words(): void
    {
        $this->subscribeDevice();
        $this->fakeProofReader($this->proofReading(['amount' => 1000]));
        $this->sendCapture();

        $payment = Payment::query()->firstOrFail();
        $this->assertNull($payment->provisionalSubscription());
        $this->assertStringContainsString('à vérifier', $this->push->sent[0]['body']);

        Mail::assertSent(PaymentAlertMail::class, function (PaymentAlertMail $mail) {
            $mail->assertSeeInHtml('En attente de votre vérification : le montant lu est trop faible');

            return true;
        });
    }

    public function test_the_alert_never_contains_the_transaction_number_the_capture_or_the_phone(): void
    {
        $this->subscribeDevice();
        $user = $this->sendCapture();

        $payment = Payment::query()->firstOrFail();
        $everything = json_encode($this->push->sent).serialize(Mail::sent(PaymentAlertMail::class)->map(fn ($mail) => $mail->alert)->all())
            .AdminAlert::query()->get()->toJson();

        foreach (['TX 2026.00123', $payment->transaction_key, $payment->proof_path, $payment->proof_hash, $user->phone, 'PRIVATE-KEY-SECRET', 'MOT-DE-PASSE-SMTP'] as $secret) {
            $this->assertStringNotContainsString((string) $secret, $everything);
        }
    }

    public function test_a_failing_notification_never_blocks_the_payment_and_is_noted_without_secret(): void
    {
        $this->subscribeDevice();
        $this->push->failWith = 'Erreur avec PRIVATE-KEY-SECRET-0123456789 sur https://push.example.test/abc';
        $this->sendCapture();

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(SubscriptionStatus::Provisional, Payment::query()->firstOrFail()->subscription->status);

        $failure = AdminAlert::query()->where('channel', 'push')->firstOrFail();
        $this->assertSame(AdminAlert::FAILED, $failure->status);
        $this->assertStringNotContainsString('PRIVATE-KEY-SECRET', (string) $failure->reason);
        $this->assertStringNotContainsString('https://', (string) $failure->reason);
        // L'e-mail part quand même.
        Mail::assertSent(PaymentAlertMail::class);
    }

    public function test_a_failing_mail_never_blocks_the_payment_and_is_noted(): void
    {
        $this->subscribeDevice();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connexion refusée avec MOT-DE-PASSE-SMTP-SECRET'));

        $this->sendCapture();

        $this->assertSame(1, Payment::query()->count());
        $failure = AdminAlert::query()->where('channel', 'mail')->firstOrFail();
        $this->assertSame(AdminAlert::FAILED, $failure->status);
        $this->assertStringNotContainsString('MOT-DE-PASSE-SMTP', (string) $failure->reason);
        $this->assertSame(AdminAlert::SENT, AdminAlert::query()->where('channel', 'push')->firstOrFail()->status);
    }

    public function test_an_expired_device_is_deleted(): void
    {
        $this->subscribeDevice('ancien');
        $this->subscribeDevice('actuel');
        $this->push->goneEndpoints = ['https://push.example.test/ancien'];

        $this->sendCapture();

        $this->assertSame(['actuel'], PushSubscription::query()->pluck('device')->all());
        $this->assertSame(AdminAlert::SENT, AdminAlert::query()->where('channel', 'push')->firstOrFail()->status);
    }

    public function test_without_any_setting_nothing_is_sent_and_nothing_fails(): void
    {
        config(['alerts.emails' => [], 'alerts.vapid.public_key' => null, 'alerts.vapid.private_key' => null, 'alerts.vapid.subject' => null]);

        $this->sendCapture();

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame([], $this->push->sent);
        Mail::assertNothingSent();
        $this->assertSame(0, AdminAlert::query()->count());
    }

    public function test_the_expiry_alert_is_sent_once_two_hours_before_the_end(): void
    {
        $this->subscribeDevice();
        $this->sendCapture();
        $this->push->sent = [];
        $payment = Payment::query()->firstOrFail();

        // 24 heures d'accès : trop tôt pour prévenir.
        Artisan::call('payments:alert-expiring');
        $this->assertSame([], $this->push->sent);

        // Dans les deux dernières heures : une alerte.
        $this->travel(22)->hours();
        $this->travel(30)->minutes();
        Artisan::call('payments:alert-expiring');
        $this->assertCount(1, $this->push->sent);
        $this->assertStringContainsString('À valider vite', $this->push->sent[0]['title']);
        $this->assertStringContainsString('/admin/paiements#paiement-'.$payment->id, $this->push->sent[0]['url']);
        $this->assertNotNull($payment->fresh()->expiry_alert_sent_at);

        // Jamais deux fois.
        $this->travel(10)->minutes();
        Artisan::call('payments:alert-expiring');
        $this->assertCount(1, $this->push->sent);
    }

    public function test_no_expiry_alert_for_a_payment_already_decided(): void
    {
        $this->subscribeDevice();
        $this->sendCapture();
        $this->push->sent = [];
        $payment = Payment::query()->firstOrFail();
        $payment->approve(User::factory()->admin()->create());

        $this->travel(23)->hours();
        Artisan::call('payments:alert-expiring');

        $this->assertSame([], $this->push->sent);
    }

    public function test_a_learner_from_another_country_taps_whatsapp_the_click_is_recorded_then_redirected_and_alerted(): void
    {
        $this->subscribeDevice();
        $user = User::factory()->create(['name' => 'Awa Ndiaye', 'country' => 'CM', 'phone' => '+237677123456']);

        $page = $this->actingAs($user)->get('/abonnement/essentiel')->assertOk();
        $page->assertSee(route('payment.whatsapp', 'essentiel'), false);

        $response = $this->actingAs($user)->get(route('payment.whatsapp', 'essentiel'));
        $response->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/', $response->headers->get('Location'));
        $this->assertSame(1, WhatsappPaymentClick::query()->where('user_id', $user->id)->count());

        $this->assertCount(1, $this->push->sent);
        $this->assertStringContainsString('Paiement demandé sur WhatsApp', $this->push->sent[0]['title']);
        $this->assertStringContainsString('Awa Ndiaye', $this->push->sent[0]['body']);
        $this->assertStringContainsString('Cameroun', $this->push->sent[0]['body']);
        $this->assertStringNotContainsString('677123456', json_encode($this->push->sent));
        Mail::assertSent(PaymentAlertMail::class);

        // Un deuxième clic tout de suite : enregistré, mais une seule alerte.
        $this->actingAs($user)->get(route('payment.whatsapp', 'essentiel'))->assertRedirect();
        $this->assertSame(2, WhatsappPaymentClick::query()->count());
        $this->assertCount(1, $this->push->sent);

        // Longtemps après : nouvelle alerte.
        $this->travel(2)->hours();
        $this->actingAs($user)->get(route('payment.whatsapp', 'essentiel'))->assertRedirect();
        $this->assertCount(2, $this->push->sent);
    }

    public function test_a_learner_who_can_pay_online_is_sent_to_the_payment_page_not_whatsapp(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('payment.whatsapp', 'essentiel'))->assertRedirect(route('payment.create', 'essentiel'));
        $this->assertSame(0, WhatsappPaymentClick::query()->count());
        $this->assertSame([], $this->push->sent);
    }

    public function test_a_whatsapp_alert_failure_does_not_stop_the_redirection(): void
    {
        $this->subscribeDevice();
        $this->push->failWith = 'panne';
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('panne'));
        $user = User::factory()->create(['country' => 'CM', 'phone' => '+237677123456']);

        $this->actingAs($user)->get(route('payment.whatsapp', 'essentiel'))->assertRedirect();

        $this->assertSame(1, WhatsappPaymentClick::query()->count());
    }

    public function test_alert_pages_are_for_administrators_only(): void
    {
        $learner = User::factory()->create();
        $routes = [
            ['get', '/admin/alertes'],
            ['post', '/admin/alertes/abonnement'],
            ['delete', '/admin/alertes/abonnement'],
            ['post', '/admin/alertes/essai-notification'],
            ['post', '/admin/alertes/essai-email'],
        ];

        foreach ($routes as [$method, $url]) {
            $this->get('/')->assertOk();
            $this->actingAs($learner)->{$method}($url)->assertForbidden();
        }

        auth()->logout();
        $this->get('/admin/alertes')->assertRedirect('/connexion');
    }

    public function test_the_alerts_page_shows_states_devices_and_the_last_twenty_alerts(): void
    {
        $this->subscribeDevice('Pixel');
        foreach (range(1, 25) as $i) {
            AdminAlert::create(['kind' => 'test', 'channel' => $i % 2 ? 'push' : 'mail', 'status' => $i === 25 ? 'failed' : 'sent', 'title' => 'Alerte numéro '.$i, 'reason' => $i === 25 ? 'délai dépassé' : null]);
        }

        $page = $this->actingAs(User::factory()->admin()->create())->get('/admin/alertes')->assertOk();
        $page->assertSee('Recevoir les alertes sur ce téléphone')
            ->assertSee('Envoyer une alerte d\'essai', false)
            ->assertSee('Envoyer un e-mail d\'essai', false)
            ->assertSee('Téléphones abonnés : <strong>1</strong>', false)
            ->assertSee('Pixel')
            ->assertSee('Alerte numéro 25')
            ->assertSee('délai dépassé')
            ->assertDontSee('Alerte numéro 5<', false)
            ->assertDontSee('PRIVATE-KEY-SECRET')
            ->assertDontSee('MOT-DE-PASSE-SMTP')
            ->assertDontSee('PUBLIC-KEY-FICTIVE-0123456789</', false);
        $this->assertSame(20, substr_count($page->getContent(), 'Alerte numéro'));
    }

    public function test_the_alerts_page_says_when_nothing_is_set_up(): void
    {
        config(['alerts.emails' => [], 'alerts.vapid.public_key' => null, 'alerts.vapid.private_key' => null, 'alerts.vapid.subject' => null]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/alertes')->assertOk()->assertSee('non réglées')->assertSee('non réglé');
        $this->actingAs($admin)->post('/admin/alertes/essai-notification')->assertRedirect('/admin/alertes');
        $this->get('/admin/alertes')->assertSee('VAPID_PUBLIC_KEY');
        $this->post('/admin/alertes/essai-email')->assertRedirect('/admin/alertes');
        $this->get('/admin/alertes')->assertSee('ADMIN_ALERT_EMAIL');
    }

    public function test_test_buttons_send_and_show_the_result(): void
    {
        $this->subscribeDevice();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/alertes/essai-notification')->assertRedirect('/admin/alertes');
        $this->assertCount(1, $this->push->sent);
        $this->get('/admin/alertes')->assertSee('Notification d&#039;essai envoyée', false);

        $this->post('/admin/alertes/essai-email')->assertRedirect('/admin/alertes');
        Mail::assertSent(PaymentAlertMail::class);
        $this->get('/admin/alertes')->assertSee('E-mail d&#039;essai envoyé', false);

        $this->push->failWith = 'refusé';
        $this->post('/admin/alertes/essai-notification');
        $this->get('/admin/alertes')->assertSee('Notification d&#039;essai non envoyée : refusé', false);
    }

    public function test_an_admin_registers_and_removes_a_phone(): void
    {
        $admin = User::factory()->admin()->create();
        $body = ['endpoint' => 'https://push.example.test/abc', 'keys' => ['p256dh' => 'cle', 'auth' => 'authentification'], 'contentEncoding' => 'aes128gcm'];

        $this->actingAs($admin)->postJson('/admin/alertes/abonnement', $body, ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14; Pixel 6) Chrome/130'])->assertOk();
        // Le même téléphone deux fois : une seule ligne.
        $this->postJson('/admin/alertes/abonnement', $body)->assertOk();
        $this->assertSame(1, PushSubscription::query()->count());
        $this->assertSame('Android, Chrome', PushSubscription::query()->firstOrFail()->device);

        // Plusieurs appareils.
        $this->postJson('/admin/alertes/abonnement', ['endpoint' => 'https://push.example.test/def'] + $body)->assertOk();
        $this->assertSame(2, PushSubscription::query()->count());

        // Adresse qui n'est pas en https : refusée.
        $this->postJson('/admin/alertes/abonnement', ['endpoint' => 'http://push.example.test/x'] + $body)->assertUnprocessable();

        $this->deleteJson('/admin/alertes/abonnement', ['endpoint' => 'https://push.example.test/abc'])->assertOk();
        $this->assertSame(1, PushSubscription::query()->count());
    }

    public function test_the_red_counter_in_the_header_counts_pending_and_provisional_payments(): void
    {
        $this->sendCapture();
        $this->sendCapture(User::factory()->create(['name' => 'Ama Koffi']));

        $html = $this->actingAs(User::factory()->admin()->create())->get('/admin/alertes')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/badge badge-alert[^>]*>\s*2\s*</', $html);
    }

    public function test_redaction_hides_keys_addresses_and_long_tokens(): void
    {
        $text = Redact::reason('Échec sur https://fcm.example.test/send/AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA avec PRIVATE-KEY-SECRET-0123456789 et BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB');

        $this->assertStringNotContainsString('PRIVATE-KEY-SECRET', $text);
        $this->assertStringNotContainsString('https://', $text);
        $this->assertStringNotContainsString('BBBBBBBB', $text);
    }

    public function test_the_service_worker_shows_prominent_notifications_and_opens_the_payment(): void
    {
        $worker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("addEventListener('push'", $worker);
        $this->assertStringContainsString('requireInteraction: true', $worker);
        $this->assertStringContainsString("addEventListener('notificationclick'", $worker);
        // Le service worker garde ses garanties : pages jamais en cache, envois jamais interceptés.
        $this->assertStringContainsString("request.method !== 'GET'", $worker);
        $this->assertStringContainsString('pageResponse(request)', $worker);
        $this->assertTrue(app(AdminAlerts::class)->pushConfigured());
    }
}

/** Faux transport de notifications : garde ce qu'il aurait envoyé, n'envoie rien. */
class FakePush implements PushTransport
{
    /** @var list<array<string, mixed>> */
    public array $sent = [];

    public ?string $failWith = null;

    /** @var list<string> */
    public array $goneEndpoints = [];

    public function send(PushSubscription $subscription, array $payload): PushResult
    {
        if (in_array($subscription->endpoint, $this->goneEndpoints, true)) {
            return PushResult::gone();
        }

        if ($this->failWith !== null) {
            return PushResult::failed(Redact::reason($this->failWith));
        }

        $this->sent[] = $payload;

        return PushResult::sent();
    }
}
