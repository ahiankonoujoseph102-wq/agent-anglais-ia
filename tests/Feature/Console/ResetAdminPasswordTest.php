<?php

namespace Tests\Feature\Console;

use App\Console\Commands\ResetAdminPassword;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ResetAdminPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->role = Role::Admin;
        $user->save();

        return $user;
    }

    /** Lance la commande sans terminal (comme l'onglet « Commands ») et renvoie le code et la sortie. */
    private function resetPassword(array $options = []): array
    {
        $code = Artisan::call('app:reset-admin-password', $options + ['--no-interaction' => true]);

        return [$code, Artisan::output()];
    }

    private function passwordIn(string $output): string
    {
        $this->assertSame(1, preg_match('/Mot de passe provisoire : (\S+)/', $output, $match));

        return $match[1];
    }

    public function test_it_gives_the_only_admin_a_temporary_password_shown_once_and_never_logged(): void
    {
        Log::spy();
        $admin = $this->admin(['phone' => '+22890000000', 'password' => 'ancien-mot-de-passe']);
        $learner = User::factory()->create(['password' => 'mot-de-passe-eleve']);

        [$code, $output] = $this->resetPassword();

        $this->assertSame(0, $code);
        $password = $this->passwordIn($output);
        $this->assertSame(ResetAdminPassword::PASSWORD_LENGTH, strlen($password));
        $this->assertStringNotContainsString('+22890000000', $output);

        $admin->refresh();
        $this->assertTrue(Hash::check($password, $admin->password));
        $this->assertFalse(Hash::check('ancien-mot-de-passe', $admin->password));
        $this->assertTrue($admin->must_change_password);
        $this->assertSame(Role::Admin, $admin->role);

        // Les autres comptes ne changent pas.
        $this->assertFalse((bool) $learner->fresh()->must_change_password);
        $this->assertTrue(Hash::check('mot-de-passe-eleve', $learner->fresh()->password));

        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('debug');
        Log::shouldNotHaveReceived('notice');
    }

    public function test_the_admin_logs_in_with_it_and_must_choose_a_new_password(): void
    {
        $this->admin(['phone' => '+22890000000']);
        $password = $this->passwordIn($this->resetPassword()[1]);

        $this->post('/connexion', ['phone' => '90 00 00 00', 'password' => $password]);
        $this->assertAuthenticated();

        $this->get('/admin/paiements')->assertRedirect(route('password.change'));
    }

    public function test_it_closes_the_open_sessions_of_the_admin(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->admin();
        $other = User::factory()->create();
        foreach ([$admin, $other] as $user) {
            DB::table('sessions')->insert(['id' => 'session-'.$user->id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        }

        $this->resetPassword();

        $this->assertDatabaseMissing('sessions', ['user_id' => $admin->id]);
        $this->assertDatabaseHas('sessions', ['user_id' => $other->id]);
    }

    public function test_it_fails_without_any_admin(): void
    {
        User::factory()->create();

        [$code, $output] = $this->resetPassword();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Aucun compte administrateur', $output);
    }

    public function test_with_several_admins_it_lists_them_masked_and_asks_for_the_id(): void
    {
        $first = $this->admin(['phone' => '+22890000000', 'password' => 'premier-mot-de-passe']);
        $second = $this->admin(['phone' => '+22890000001']);

        [$code, $output] = $this->resetPassword();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('--id='.$first->id, $output);
        $this->assertStringContainsString('--id='.$second->id, $output);
        $this->assertStringNotContainsString('+22890000000', $output);
        $this->assertStringNotContainsString('Mot de passe provisoire :', $output);
        $this->assertTrue(Hash::check('premier-mot-de-passe', $first->fresh()->password));

        [$code, $output] = $this->resetPassword(['--id' => $second->id]);

        $this->assertSame(0, $code);
        $this->assertTrue(Hash::check($this->passwordIn($output), $second->fresh()->password));
        $this->assertTrue(Hash::check('premier-mot-de-passe', $first->fresh()->password));
    }

    public function test_an_id_that_is_not_an_admin_changes_nothing(): void
    {
        $this->admin();
        $learner = User::factory()->create(['password' => 'mot-de-passe-eleve']);

        [$code] = $this->resetPassword(['--id' => $learner->id]);

        $this->assertSame(1, $code);
        $this->assertTrue(Hash::check('mot-de-passe-eleve', $learner->fresh()->password));
        $this->assertFalse((bool) $learner->fresh()->must_change_password);
    }
}
