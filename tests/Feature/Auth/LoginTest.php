<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_displayed(): void
    {
        $this->get('/connexion')
            ->assertOk()
            ->assertSee('Connexion')
            ->assertSee('Mot de passe');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function phoneFormats(): array
    {
        return [
            'local' => ['90123456'],
            'local avec espaces' => ['90 12 34 56'],
            'international' => ['+22890123456'],
            'international avec espaces' => ['+228 90 12 34 56'],
            'préfixe 00' => ['0022890123456'],
            'indicatif sans +' => ['22890123456'],
        ];
    }

    #[DataProvider('phoneFormats')]
    public function test_learner_can_log_in_with_any_phone_format(string $phone): void
    {
        $user = User::factory()->create(['phone' => '+22890123456']);

        $this->post('/connexion', ['phone' => $phone, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_lands_on_admin_area_after_login(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post('/connexion', ['phone' => $admin->phone, 'password' => 'password'])
            ->assertRedirect(route('admin.users.index'));
    }

    public function test_user_is_sent_back_to_the_page_they_wanted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->get('/espace')->assertRedirect(route('login'));

        $this->post('/connexion', ['phone' => $admin->phone, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->from('/connexion')
            ->post('/connexion', ['phone' => $user->phone, 'password' => 'mauvais'])
            ->assertRedirect('/connexion')
            ->assertSessionHasErrors(['phone' => 'Numéro de téléphone ou mot de passe incorrect.']);

        $this->assertGuest();
    }

    public function test_unknown_phone_is_rejected(): void
    {
        $this->post('/connexion', ['phone' => '91000000', 'password' => 'password'])
            ->assertSessionHasErrors('phone');

        $this->assertGuest();
    }

    public function test_login_is_blocked_after_too_many_attempts(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS; $i++) {
            $this->post('/connexion', ['phone' => $user->phone, 'password' => 'mauvais']);
        }

        // Même le bon mot de passe est refusé pendant le blocage.
        $this->post('/connexion', ['phone' => $user->phone, 'password' => 'password'])
            ->assertSessionHasErrors('phone');

        $this->assertGuest();
        $this->assertStringStartsWith('Trop de tentatives', session('errors')->first('phone'));
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/deconnexion')
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_logged_in_user_is_redirected_away_from_login(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/connexion')
            ->assertRedirect(route('admin.users.index'));
    }
}
