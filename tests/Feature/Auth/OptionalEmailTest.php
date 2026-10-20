<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Adresse e-mail facultative : inscription, compte de l'apprenant, fiche administrateur.
 */
class OptionalEmailTest extends TestCase
{
    use RefreshDatabase;

    private const HINT = 'Facultatif. Pour recevoir plus tard vos corrections et documents.';

    /**
     * @return array<string, string>
     */
    private function registration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kossi Mensah',
            'phone' => '90 12 34 56',
            'password' => 'motdepasse',
            'password_confirmation' => 'motdepasse',
        ], $overrides);
    }

    public function test_registration_shows_the_optional_email_field(): void
    {
        $this->get('/inscription')
            ->assertOk()
            ->assertSee('Adresse e-mail')
            ->assertSee('type="email"', false)
            ->assertSee(self::HINT);
    }

    public function test_learner_can_register_without_an_email(): void
    {
        $this->post('/inscription', $this->registration(['email' => '  ']))->assertRedirect(route('dashboard'));

        $this->assertNull(User::firstOrFail()->email);
    }

    public function test_email_is_stored_in_lowercase(): void
    {
        $this->post('/inscription', $this->registration(['email' => '  Kossi.Mensah@Exemple.TG ']))->assertRedirect(route('dashboard'));

        $this->assertSame('kossi.mensah@exemple.tg', User::firstOrFail()->email);
    }

    public function test_invalid_email_is_refused_in_french(): void
    {
        $this->from('/inscription')->post('/inscription', $this->registration(['email' => 'kossi-arobase-exemple']))
            ->assertRedirect('/inscription')
            ->assertSessionHasErrors(['email' => 'Cette adresse e-mail n\'est pas valide. Exemple : prenom.nom@exemple.com.']);

        $this->assertGuest();
    }

    public function test_email_must_be_unique_whatever_the_case(): void
    {
        User::factory()->create(['email' => 'afi@exemple.tg']);

        $this->post('/inscription', $this->registration(['email' => 'AFI@exemple.tg']))
            ->assertSessionHasErrors(['email' => 'Cette adresse e-mail est déjà utilisée par un autre compte.']);

        $this->assertSame(1, User::count());
    }

    public function test_database_allows_many_accounts_without_email_but_never_twice_the_same(): void
    {
        User::factory()->count(2)->create(['email' => null]);
        User::factory()->create(['email' => 'Yao@Exemple.tg']);

        $this->assertSame('yao@exemple.tg', User::whereNotNull('email')->value('email'));

        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create(['email' => 'yao@exemple.tg']);
    }

    public function test_login_still_uses_the_phone_number(): void
    {
        User::factory()->create(['phone' => '+22890123456', 'email' => 'kossi@exemple.tg']);

        $this->post('/connexion', ['phone' => 'kossi@exemple.tg', 'password' => 'password'])->assertSessionHasErrors();
        $this->assertGuest();

        $this->post('/connexion', ['phone' => '90 12 34 56', 'password' => 'password']);
        $this->assertAuthenticated();
    }

    public function test_learner_can_add_change_and_remove_their_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/compte')
            ->assertOk()
            ->assertSee('Mon compte')
            ->assertSee(PhoneNumber::display($user->phone))
            ->assertSee(self::HINT);

        $this->put('/compte', ['email' => 'Nouvelle@Exemple.tg'])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHas('status', 'Votre adresse e-mail est enregistrée.');
        $this->assertSame('nouvelle@exemple.tg', $user->fresh()->email);

        // Garder sa propre adresse n'est pas un doublon.
        $this->put('/compte', ['email' => 'nouvelle@exemple.tg'])->assertSessionHasNoErrors();

        $this->put('/compte', ['email' => ''])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->email);
    }

    public function test_account_refuses_an_email_used_by_another_learner(): void
    {
        User::factory()->create(['email' => 'pris@exemple.tg']);
        $user = User::factory()->create(['email' => 'moi@exemple.tg']);

        $this->actingAs($user)->put('/compte', ['email' => 'PRIS@exemple.tg'])
            ->assertSessionHasErrors(['email' => 'Cette adresse e-mail est déjà utilisée par un autre compte.']);

        $this->assertSame('moi@exemple.tg', $user->fresh()->email);
    }

    public function test_account_page_is_for_logged_in_users_only(): void
    {
        $this->get('/compte')->assertRedirect(route('login'));
        $this->put('/compte', ['email' => 'x@exemple.tg'])->assertRedirect(route('login'));
    }

    public function test_admin_sees_the_email_on_the_learner_page_when_there_is_one(): void
    {
        $admin = User::factory()->admin()->create();
        $withEmail = User::factory()->create(['email' => 'esi@exemple.tg']);
        $withoutEmail = User::factory()->create(['email' => null]);

        $this->actingAs($admin)->get(route('admin.users.show', $withEmail))
            ->assertOk()
            ->assertSee('Adresse e-mail')
            ->assertSee('esi@exemple.tg');

        $this->actingAs($admin)->get(route('admin.users.show', $withoutEmail))
            ->assertOk()
            ->assertDontSee('Adresse e-mail');
    }
}
