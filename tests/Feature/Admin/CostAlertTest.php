<?php

namespace Tests\Feature\Admin;

use App\Enums\LessonStatus;
use App\Mail\PaymentAlertMail;
use App\Models\AdminAlert;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Live\CostWatch;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Alerte de coût par abonné : coût estimé des séances depuis le début de la formule en cours, comparé au prix ;
 * au-delà du seuil des « Réglages », l'abonné est signalé dans « Consommation » et l'administrateur prévenu une fois.
 */
class CostAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config([
            'platform.cost_alert' => ['percent' => 50, 'xof_per_usd' => 600],
            'services.gemini.pricing' => [
                'input_per_million' => 3.0, 'output_per_million' => 12.0,
                'input_text_per_million' => 0.75, 'output_text_per_million' => 4.5,
                'cached_text_per_million' => 0.75, 'cached_audio_per_million' => 3.0,
            ],
            'alerts.emails' => ['admin@example.test'],
            'mail.default' => 'smtp',
            'mail.from.address' => 'expediteur@example.test',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.username' => 'utilisateur-smtp',
            'mail.mailers.smtp.password' => 'mot-de-passe-smtp',
        ]);
    }

    /**
     * Une formule Semaine (2 000 FCFA, soit 3,33 $ à 600 FCFA le dollar ; seuil à 50 % : 1,67 $) et une séance au coût donné.
     */
    private function subscriberWithLessonCost(string $name, int $inputAudioTokens): Subscription
    {
        $user = User::factory()->create(['name' => $name]);
        $subscription = Subscription::factory()->forPlan('semaine')->create(['user_id' => $user->id]);
        $user->lessons()->create(['date' => today(), 'started_at' => now()->subHour(), 'allowed_seconds' => 900, 'used_seconds' => 900, 'status' => LessonStatus::Completed])
            ->forceFill(['input_tokens' => $inputAudioTokens, 'output_tokens' => 0, 'input_audio_tokens' => $inputAudioTokens, 'input_text_tokens' => 0, 'turns' => 10])->save();

        return $subscription;
    }

    public function test_a_subscriber_over_the_threshold_is_listed_and_alerted_once(): void
    {
        // 1 000 000 jetons audio × 3 $ = 3 $ : 90 % du prix de la formule Semaine.
        $costly = $this->subscriberWithLessonCost('Kossi Mensah', 1_000_000);
        // 200 000 jetons audio = 0,60 $ : 18 % du prix.
        $this->subscriberWithLessonCost('Afi Agbeko', 200_000);

        $rows = CostWatch::current();
        $this->assertSame([90, 18], $rows->pluck('share')->all());
        $this->assertTrue($rows->first()['over']);
        $this->assertFalse($rows->last()['over']);

        $this->artisan('usage:alert-costs')->assertSuccessful();
        $this->artisan('usage:alert-costs')->assertSuccessful();

        Mail::assertSent(PaymentAlertMail::class, 1);
        $this->assertNotNull($costly->fresh()->cost_alert_sent_at);
        $this->assertSame(1, AdminAlert::query()->where('kind', 'cost_high')->count());
        $this->assertStringContainsString('90 %', AdminAlert::query()->where('kind', 'cost_high')->value('title'));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/consommation')
            ->assertOk()
            ->assertSee('Coût des abonnés (formule en cours)')
            ->assertSee('90 %, au-dessus du seuil')
            ->assertSee('Afi Agbeko')
            ->assertSee('18 %');
    }

    public function test_the_threshold_and_the_exchange_rate_are_set_in_the_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/reglages')->assertOk()->assertSee('Alerte de coût par abonné');

        $this->actingAs($admin)->put('/admin/reglages', ['cost_alert' => ['percent' => '120', 'xof_per_usd' => '650']])
            ->assertRedirect('/admin/reglages');
        SiteSettings::flush();

        $this->assertSame(120, CostWatch::percent());
        $this->assertSame(650, CostWatch::xofPerUsd());

        // Le même abonné n'est plus au-dessus du seuil.
        $this->subscriberWithLessonCost('Kossi Mensah', 1_000_000);
        $this->assertFalse(CostWatch::current()->first()['over']);

        $this->actingAs($admin)->put('/admin/reglages', ['cost_alert' => ['percent' => '0']])
            ->assertSessionHasErrors('cost_alert.percent');
    }

    public function test_a_granted_access_is_compared_to_the_catalogue_price_and_old_sessions_do_not_count(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->forPlan('semaine')->create(['user_id' => $user->id, 'price' => 0, 'granted_days' => 7]);
        // Séance d'AVANT la formule : jamais comptée.
        $user->lessons()->create(['date' => today()->subDays(3), 'started_at' => now()->subDays(3), 'allowed_seconds' => 900, 'used_seconds' => 900, 'status' => LessonStatus::Completed])
            ->forceFill(['input_tokens' => 5_000_000, 'output_tokens' => 0])->save();

        $row = CostWatch::row($subscription);
        $this->assertSame(2000, $row['revenue']);
        $this->assertSame(0.0, $row['cost']);
        $this->assertFalse($row['over']);
    }
}
