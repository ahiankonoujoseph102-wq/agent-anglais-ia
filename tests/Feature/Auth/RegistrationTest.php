<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kossi Mensah',
            'phone' => '90 12 34 56',
            'password' => 'motdepasse',
            'password_confirmation' => 'motdepasse',
        ], $overrides);
    }

    public function test_registration_page_is_displayed_in_french(): void
    {
        $this->get('/inscription')
            ->assertOk()
            ->assertSee('Créer mon compte')
            ->assertSee('Numéro de téléphone');
    }

    public function test_learner_can_register_and_is_logged_in(): void
    {
        $response = $this->post('/inscription', $this->validData());

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::firstOrFail();
        $this->assertSame('Kossi Mensah', $user->name);
        $this->assertSame('+22890123456', $user->phone);
        $this->assertSame(Role::Learner, $user->role);
        $this->assertNull($user->level);
        $this->assertNotSame('motdepasse', $user->password);
    }

    public function test_registration_cannot_set_role_or_level(): void
    {
        $this->post('/inscription', $this->validData(['role' => 'admin', 'level' => 'C2']));

        $user = User::firstOrFail();
        $this->assertSame(Role::Learner, $user->role);
        $this->assertNull($user->level);
    }

    public function test_phone_number_must_be_unique_whatever_the_format(): void
    {
        User::factory()->create(['phone' => '+22890123456']);

        $response = $this->from('/inscription')->post('/inscription', $this->validData(['phone' => '+228 90-12-34-56']));

        $response->assertRedirect('/inscription')
            ->assertSessionHasErrors(['phone' => 'Un compte existe déjà avec ce numéro. Connectez-vous.']);
        $this->assertGuest();
        $this->assertSame(1, User::count());
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function invalidData(): array
    {
        return [
            'nom manquant' => [['name' => ''], 'name'],
            'téléphone manquant' => [['phone' => ''], 'phone'],
            'téléphone trop court' => [['phone' => '1234'], 'phone'],
            'téléphone avec des lettres' => [['phone' => 'abcdefgh'], 'phone'],
            'mot de passe trop court' => [['password' => 'court', 'password_confirmation' => 'court'], 'password'],
            'confirmation différente' => [['password_confirmation' => 'autrechose'], 'password'],
        ];
    }

    #[DataProvider('invalidData')]
    public function test_registration_is_validated(array $overrides, string $field): void
    {
        $this->post('/inscription', $this->validData($overrides))
            ->assertSessionHasErrors($field);

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_validation_messages_are_in_french(): void
    {
        $this->post('/inscription', $this->validData(['name' => '']))
            ->assertSessionHasErrors(['name' => 'Le champ nom est obligatoire.']);
    }

    public function test_logged_in_user_is_redirected_away_from_registration(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/inscription')
            ->assertRedirect(route('dashboard'));
    }
}
