<?php

namespace Tests\Feature\Console;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CreateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_asks_for_the_phone_and_the_password_and_creates_an_admin(): void
    {
        $this->artisan('app:create-admin', ['--name' => 'Joseph'])
            ->expectsQuestion('Numéro de téléphone', '90 00 00 09')
            ->expectsQuestion('Mot de passe (10 caractères au minimum)', 'secret-12345')
            ->expectsQuestion('Confirmer le mot de passe', 'secret-12345')
            ->assertSuccessful();

        $admin = User::firstWhere('phone', '+22890000009');
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertSame('Joseph', $admin->name);
        $this->assertTrue(Hash::check('secret-12345', $admin->password));
    }

    public function test_the_password_and_the_phone_are_never_written_to_the_output_or_the_logs(): void
    {
        Log::spy();

        $this->artisan('app:create-admin')
            ->expectsQuestion('Numéro de téléphone', '90 00 00 09')
            ->expectsQuestion('Mot de passe (10 caractères au minimum)', 'secret-12345')
            ->expectsQuestion('Confirmer le mot de passe', 'secret-12345')
            ->doesntExpectOutputToContain('secret-12345')
            ->doesntExpectOutputToContain('+22890000009')
            ->expectsOutputToContain('+228••••••09')
            ->assertSuccessful();

        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('debug');
    }

    public function test_the_command_has_no_option_that_would_carry_a_secret(): void
    {
        $options = array_keys($this->app->make(Kernel::class)->all()['app:create-admin']->getDefinition()->getOptions());

        $this->assertNotContains('password', $options);
        $this->assertNotContains('phone', $options);
    }

    public function test_it_refuses_two_different_passwords(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Numéro de téléphone', '90000009')
            ->expectsQuestion('Mot de passe (10 caractères au minimum)', 'secret-12345')
            ->expectsQuestion('Confirmer le mot de passe', 'autre-chose-123')
            ->expectsOutputToContain('Les deux mots de passe sont différents.')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_it_promotes_an_existing_learner_and_keeps_the_password_when_left_empty(): void
    {
        $user = User::factory()->create(['phone' => '+22890000009']);

        $this->artisan('app:create-admin')
            ->expectsQuestion('Numéro de téléphone', '+22890000009')
            ->expectsQuestion('Nouveau mot de passe (laisser vide pour conserver l\'actuel)', '')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->isAdmin());
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_it_refuses_an_invalid_phone_or_short_password(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Numéro de téléphone', '12')
            ->assertFailed();

        $this->artisan('app:create-admin')
            ->expectsQuestion('Numéro de téléphone', '90000009')
            ->expectsQuestion('Mot de passe (10 caractères au minimum)', 'court')
            ->expectsQuestion('Confirmer le mot de passe', 'court')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_without_a_terminal_it_reads_the_temporary_variables(): void
    {
        config(['admin.bootstrap.phone' => '90 00 00 09', 'admin.bootstrap.password' => 'secret-12345']);

        $this->artisan('app:create-admin', ['--no-interaction' => true])
            ->expectsOutputToContain('Supprimez maintenant ADMIN_PHONE et ADMIN_PASSWORD')
            ->doesntExpectOutputToContain('secret-12345')
            ->assertSuccessful();

        $admin = User::firstWhere('phone', '+22890000009');
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue(Hash::check('secret-12345', $admin->password));
    }

    public function test_the_name_option_renames_an_existing_administrator_without_touching_the_password(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Administrateur', 'phone' => '+22890000009', 'password' => 'ancien-mot-de-passe']);
        config(['admin.bootstrap.phone' => '90 00 00 09', 'admin.bootstrap.password' => null]);

        $this->artisan('app:create-admin', ['--no-interaction' => true, '--name' => 'Joseph Ahiankonou'])->assertSuccessful();

        $admin->refresh();
        $this->assertSame('Joseph Ahiankonou', $admin->name);
        $this->assertTrue(Hash::check('ancien-mot-de-passe', $admin->password));
        $this->assertTrue($admin->isAdmin());
    }

    public function test_without_a_terminal_and_without_variables_it_fails_with_an_explanation(): void
    {
        config(['admin.bootstrap.phone' => null, 'admin.bootstrap.password' => null]);

        $this->artisan('app:create-admin', ['--no-interaction' => true])
            ->expectsOutputToContain('ADMIN_PHONE')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_the_deployment_guide_uses_the_non_interactive_option(): void
    {
        $guide = file_get_contents(base_path('docs/deploiement-laravel-cloud.md'));

        $this->assertStringContainsString('php artisan app:create-admin --no-interaction', $guide);
        $this->assertStringNotContainsString("php artisan app:create-admin\n", $guide);
        $this->assertStringContainsString('sans espace ni « # »', $guide);
    }
}
