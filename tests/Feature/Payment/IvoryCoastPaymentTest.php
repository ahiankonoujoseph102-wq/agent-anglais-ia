<?php

namespace Tests\Feature\Payment;

use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentChecks;
use App\Support\PaymentCountry;
use App\Support\PaymentOperator;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ConfiguresPayments;
use Tests\TestCase;

/**
 * Côte d'Ivoire : Orange Money, MTN Mobile Money et Wave, numéros à 10 chiffres.
 * Les numéros viennent de la configuration (valeurs par défaut). Aucun appel réel à Gemini.
 */
class IvoryCoastPaymentTest extends TestCase
{
    use ConfiguresPayments;
    use RefreshDatabase;

    private static function operator(string $key): PaymentOperator
    {
        return PaymentCountry::find('CI')->operator($key);
    }

    /**
     * Règle « Bon destinataire » pour un numéro lu sur la capture.
     *
     * @return array<string, mixed>
     */
    private function recipientCheck(string $operatorKey, ?string $read, string $operatorRead = 'Orange Money'): array
    {
        $checks = PaymentChecks::evaluate($this->proofReading([
            'operator' => $operatorRead,
            'recipient_number' => $read,
        ]), self::operator($operatorKey), 5000, hash('sha256', (string) $read.$operatorKey));

        return collect($checks->checks)->firstWhere('key', 'recipient');
    }

    public function test_ivory_coast_and_its_three_operators_are_offered_with_the_same_plans(): void
    {
        $user = User::factory()->create();

        $this->assertSame('225', PaymentCountry::find('CI')->dialCode);
        $this->actingAs($user)->get('/abonnement/essentiel')->assertOk()->assertSee('Côte d&#039;Ivoire', false);

        foreach (['orange_money' => 'Orange Money', 'mtn' => 'MTN Mobile Money', 'wave' => 'Wave'] as $key => $name) {
            $operator = self::operator($key);
            $this->assertSame(10, strlen($operator->localNumber()), "{$name} : numéro ivoirien à 10 chiffres");

            $this->actingAs($user)->get("/abonnement/intensif?pays=CI&operateur={$key}")
                ->assertOk()
                ->assertSee($name)
                ->assertSee($operator->displayNumber())
                ->assertSee($operator->holder)
                ->assertSee('data-copy="'.$operator->localNumber().'"', false)
                // Même prix que dans les autres pays.
                ->assertSee("9\u{202F}000\u{00A0}FCFA", false);
        }

        $this->get('/')->assertSee('Côte d&#039;Ivoire', false)->assertSee('MTN Mobile Money');
    }

    public function test_settings_page_lists_the_three_operators_and_an_empty_field_restores_the_default(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/reglages')
            ->assertOk()
            ->assertSee('name="operators[CI][orange_money][number]"', false)
            ->assertSee('name="operators[CI][mtn][number]"', false)
            ->assertSee('name="operators[CI][wave][holder]"', false);

        $default = self::operator('mtn')->localNumber();

        SiteSettings::save([SiteSettings::operatorNumberKey('CI', 'mtn') => '+225 05 00 00 00 01']);
        $this->assertSame('0500000001', self::operator('mtn')->localNumber());

        SiteSettings::save([SiteSettings::operatorNumberKey('CI', 'mtn') => '']);
        $this->assertSame($default, self::operator('mtn')->localNumber());
    }

    /**
     * Le bon numéro, écrit de plusieurs façons.
     *
     * @return array<string, array{0: callable(string): string}>
     */
    public static function rightNumberFormats(): array
    {
        return [
            '10 chiffres collés' => [fn (string $local) => $local],
            'par paires' => [fn (string $local) => trim(chunk_split($local, 2, ' '))],
            '+225 et espaces' => [fn (string $local) => '+225 '.trim(chunk_split($local, 2, ' '))],
            '+225 collé' => [fn (string $local) => '+225'.$local],
            '225 sans +' => [fn (string $local) => '225'.$local],
            '00225' => [fn (string $local) => '00225 '.$local],
            'points et tirets' => [fn (string $local) => '+225-'.substr($local, 0, 2).'.'.substr($local, 2, 4).'.'.substr($local, 6)],
        ];
    }

    #[DataProvider('rightNumberFormats')]
    public function test_the_right_ivorian_number_passes_the_recipient_rule(callable $format): void
    {
        foreach (['orange_money', 'mtn', 'wave'] as $key) {
            $read = $format(self::operator($key)->localNumber());
            $check = $this->recipientCheck($key, $read);

            $this->assertTrue($check['passed'], "{$key} : « {$read} » devrait être accepté. ".$check['reason']);
        }
    }

    public function test_a_wrong_number_fails_the_recipient_rule(): void
    {
        $orange = self::operator('orange_money')->localNumber();
        $mtn = self::operator('mtn')->localNumber();

        // Numéro d'un autre opérateur, numéro inconnu, et numéro dont seuls les 2 premiers chiffres diffèrent.
        $this->assertFalse($this->recipientCheck('orange_money', '+225 '.$mtn)['passed']);
        $this->assertFalse($this->recipientCheck('mtn', $orange)['passed']);
        $this->assertFalse($this->recipientCheck('wave', '+225 01 23 45 67 89')['passed']);
        $this->assertFalse($this->recipientCheck('orange_money', '+225 01'.substr($orange, 2))['passed']);
        // Numéro masqué ou non lu.
        $this->assertFalse($this->recipientCheck('orange_money', substr($orange, 0, 4).'****'.substr($orange, -2))['passed']);
        $this->assertFalse($this->recipientCheck('orange_money', null)['passed']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function operatorNamesOnCaptures(): array
    {
        return [
            'Orange Money CI' => ['orange_money', 'Orange Money CI'],
            'Orange Money Côte d\'Ivoire' => ['orange_money', 'Orange Money Côte d\'Ivoire'],
            'MTN Mobile Money' => ['mtn', 'MTN Mobile Money'],
            'MTN MoMo' => ['mtn', 'MTN MoMo'],
            'MoMo seul' => ['mtn', 'MoMo'],
            'Wave' => ['wave', 'Wave'],
            'Wave Côte d\'Ivoire' => ['wave', 'Wave Côte d\'Ivoire'],
        ];
    }

    #[DataProvider('operatorNamesOnCaptures')]
    public function test_the_operator_is_recognised_on_ivorian_captures(string $key, string $read): void
    {
        $checks = PaymentChecks::evaluate($this->proofReading([
            'operator' => $read,
            'recipient_number' => self::operator($key)->localNumber(),
        ]), self::operator($key), 5000, hash('sha256', $read));

        $this->assertTrue(collect($checks->checks)->firstWhere('key', 'readable')['passed'], $read);
    }

    public function test_another_operator_on_the_capture_is_refused(): void
    {
        $checks = PaymentChecks::evaluate($this->proofReading(['operator' => 'Wave']), self::operator('orange_money'), 5000, hash('sha256', 'x'));

        $this->assertFalse(collect($checks->checks)->firstWhere('key', 'readable')['passed']);
    }

    public function test_a_capture_paid_to_the_ivorian_number_opens_the_provisional_access(): void
    {
        config(['filesystems.private_disk' => 'local']);
        Storage::fake('local');
        $this->freezeSecond();
        $wave = self::operator('wave');
        $this->fakeProofReader($this->proofReading([
            'operator' => 'Wave',
            'recipient_number' => '+225 '.trim(chunk_split($wave->localNumber(), 2, ' ')),
            'recipient_name' => $wave->holder,
        ]));

        $user = User::factory()->create();
        $this->actingAs($user)->post('/abonnement/essentiel', [
            'country' => 'CI',
            'operator' => 'wave',
            'proof' => $this->proofImage(),
        ])->assertRedirect();

        $payment = Payment::query()->sole();
        $this->assertSame('CI', $payment->country);
        $this->assertSame(5000, $payment->amount);
        $this->assertTrue($payment->checksPassed());
        $this->assertNotNull($user->fresh()->activeSubscription);
    }
}
