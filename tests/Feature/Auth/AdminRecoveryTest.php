<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Mail\AdminRecoveryMail;
use App\Models\User;
use App\Support\Admin\AdminRecovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Accès retrouvé par l'administrateur : lien à usage unique de 30 minutes, envoyé par e-mail, jamais affiché.
 */
class AdminRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.exemple.test',
            'mail.mailers.smtp.username' => 'identifiant',
            'mail.mailers.smtp.password' => 'secret-smtp',
            'mail.from.address' => 'envoi@exemple.test',
        ]);
        Mail::fake();
    }

    private function admin(array $attributes = []): User
    {
        $user = User::factory()->create(['phone' => '+22890000000', 'password' => 'ancien-mot-de-passe'] + $attributes);
        $user->role = Role::Admin;
        $user->save();

        return $user;
    }

    /** @return array{0: int, 1: string} */
    private function command(): array
    {
        $code = Artisan::call('app:send-admin-recovery', ['--no-interaction' => true]);

        return [$code, Artisan::output()];
    }

    private function sentLink(int $index = -1): string
    {
        $mails = Mail::sent(AdminRecoveryMail::class)->values();
        $this->assertNotEmpty($mails);

        return $mails->get($index < 0 ? $mails->count() + $index : $index)->link;
    }

    private function token(string $link): string
    {
        $this->assertSame(route('admin.recovery'), strtok($link, '#'));

        return substr($link, strpos($link, '#') + 1);
    }

    public function test_the_command_emails_a_link_to_the_recovery_address_and_shows_neither_link_nor_password(): void
    {
        Log::spy();
        $admin = $this->admin();
        User::factory()->create();

        [$code, $output] = $this->command();

        $this->assertSame(0, $code);
        Mail::assertSent(AdminRecoveryMail::class, 1);
        Mail::assertSent(AdminRecoveryMail::class, fn ($mail) => $mail->hasTo('amitalk62@gmail.com') && $mail->admin->is($admin) && $mail->minutes === 30);

        $token = $this->token($this->sentLink());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertStringNotContainsString($token, $output);
        $this->assertStringNotContainsString('recuperation-administrateur', $output);
        $this->assertStringNotContainsString('amitalk62@gmail.com', $output);
        $this->assertStringContainsString('am•••••••@gmail.com', $output);
        $this->assertStringNotContainsString('+22890000000', $output);
        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');

        // L'e-mail dit à quel numéro le compte est lié ; le mot de passe ne change pas encore.
        $html = Mail::sent(AdminRecoveryMail::class)->first()->render();
        $this->assertStringContainsString('+228 90 00 00 00', $html);
        $this->assertTrue(Hash::check('ancien-mot-de-passe', $admin->fresh()->password));
    }

    public function test_the_link_logs_in_once_and_requires_a_new_password(): void
    {
        $admin = $this->admin();
        $this->command();
        $token = $this->token($this->sentLink());

        $this->get('/recuperation-administrateur')->assertOk()->assertSee('Me connecter')->assertDontSee($token);

        $this->post('/recuperation-administrateur', ['token' => $token])->assertRedirect(route('password.change'));
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue($admin->fresh()->must_change_password);
        $this->get('/admin/paiements')->assertRedirect(route('password.change'));

        $this->put('/mot-de-passe/nouveau', ['password' => 'nouveau-mot-de-passe', 'password_confirmation' => 'nouveau-mot-de-passe']);
        $this->assertFalse($admin->fresh()->must_change_password);
        $this->get('/admin/paiements')->assertOk();

        // Deuxième usage : refusé.
        $this->post('/deconnexion');
        $this->post('/recuperation-administrateur', ['token' => $token])->assertRedirect(route('admin.recovery'))->assertSessionHasErrors('token');
        $this->assertGuest();
    }

    public function test_the_link_expires_after_30_minutes(): void
    {
        $this->admin();
        $this->command();
        $token = $this->token($this->sentLink());

        $this->travel(31)->minutes();

        $this->post('/recuperation-administrateur', ['token' => $token])->assertSessionHasErrors('token');
        $this->assertGuest();
    }

    public function test_a_new_link_replaces_the_previous_one_and_a_wrong_token_is_refused(): void
    {
        $admin = $this->admin();
        $this->command();
        $old = $this->token($this->sentLink());
        $this->command();
        $new = $this->token($this->sentLink());

        $this->post('/recuperation-administrateur', ['token' => $old])->assertSessionHasErrors('token');
        $this->post('/recuperation-administrateur', ['token' => str_repeat('a', 64)])->assertSessionHasErrors('token');
        $this->post('/recuperation-administrateur', ['token' => 'pas-un-jeton'])->assertSessionHasErrors('token');
        $this->assertGuest();

        $this->post('/recuperation-administrateur', ['token' => $new])->assertRedirect(route('password.change'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_nothing_is_sent_when_the_mailer_would_keep_the_link_in_a_log(): void
    {
        $this->admin();
        config(['mail.default' => 'log']);

        [$code, $output] = $this->command();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('aucun lien envoyé', $output);
        Mail::assertNothingSent();
    }

    public function test_without_any_admin_nothing_is_sent(): void
    {
        User::factory()->create();

        [$code] = $this->command();

        $this->assertSame(1, $code);
        Mail::assertNothingSent();
    }

    public function test_a_learner_account_can_never_be_reached(): void
    {
        $learner = User::factory()->create();
        $this->admin();
        $this->command();
        $token = $this->token($this->sentLink());

        $learner->role = Role::Admin;
        $learner->save();

        $this->post('/recuperation-administrateur', ['token' => $token]);
        $this->assertAuthenticatedAs(User::where('phone', '+22890000000')->first());
    }

    public function test_no_link_is_ever_sent_without_running_the_command(): void
    {
        // Ni migration, ni tâche planifiée, ni démarrage de l'application n'envoie de lien : seulement la commande.
        $files = [
            ...glob(database_path('migrations/*.php')),
            base_path('routes/console.php'),
            ...glob(base_path('bootstrap/*.php')),
            ...glob(app_path('Providers/*.php')),
        ];

        foreach ($files as $file) {
            $code = file_get_contents($file);
            $this->assertStringNotContainsString('AdminRecovery', $code, $file);
            $this->assertStringNotContainsString('send-admin-recovery', $code, $file);
        }
    }

    public function test_the_mask_hides_the_address(): void
    {
        $this->assertSame('am•••••••@gmail.com', AdminRecovery::mask('amitalk62@gmail.com'));
    }
}
