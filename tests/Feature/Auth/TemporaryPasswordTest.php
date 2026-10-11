<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TemporaryPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_prepares_a_whatsapp_message_with_the_account_number(): void
    {
        $this->get('/connexion')->assertSee(route('password.forgot'))->assertSee('Mot de passe oublié ?');

        $this->get('/mot-de-passe-oublie')->assertOk()->assertDontSee('https://wa.me/22872240231?text=', false);

        $this->get('/mot-de-passe-oublie?telephone='.urlencode('90 11 22 33'))
            ->assertOk()
            ->assertSee('Demander sur WhatsApp')
            ->assertSee('https://wa.me/22872240231?text=', false)
            ->assertSee(rawurlencode('Numéro de mon compte : +22890112233'), false);

        $this->get('/mot-de-passe-oublie?telephone=abc')->assertSee('Ce numéro de téléphone n\'est pas valide', false);
    }

    public function test_admin_generates_a_temporary_password_shown_only_once(): void
    {
        $admin = User::factory()->admin()->create();
        $learner = User::factory()->create(['phone' => '+22891234567']);

        $this->actingAs($admin)->get(route('admin.users.show', $learner))
            ->assertOk()
            ->assertSee('Générer un mot de passe provisoire');

        $response = $this->actingAs($admin)->post(route('admin.users.password', $learner));
        $response->assertRedirect(route('admin.users.show', $learner));
        $password = session('temporary_password');

        $this->assertMatchesRegularExpression('/^[a-z2-9]{10}$/', $password);
        $learner->refresh();
        $this->assertTrue($learner->must_change_password);
        $this->assertTrue(Hash::check($password, $learner->password));

        // Affiché une fois…
        $this->actingAs($admin)->get(route('admin.users.show', $learner))->assertSee($password);
        // … puis plus jamais.
        $this->actingAs($admin)->get(route('admin.users.show', $learner))->assertDontSee($password);

        $this->actingAs(User::factory()->create())->post(route('admin.users.password', $admin))->assertForbidden();
    }

    public function test_temporary_password_forces_a_new_password_before_anything_else(): void
    {
        $learner = User::factory()->create(['phone' => '+22891234567']);
        $learner->password = 'provisoire9';
        $learner->must_change_password = true;
        $learner->save();

        $this->post('/connexion', ['phone' => '91 23 45 67', 'password' => 'provisoire9'])
            ->assertRedirect('/espace');

        // Tout le reste est bloqué tant que le mot de passe n'est pas changé.
        $this->get('/espace')->assertRedirect(route('password.change'));
        $this->get('/abonnement')->assertRedirect(route('password.change'));
        $this->postJson('/seance/connexion')->assertForbidden();
        $this->get(route('password.change'))->assertOk()->assertSee('Choisissez un nouveau mot de passe');

        $this->put(route('password.update'), ['password' => 'nouveau-secret', 'password_confirmation' => 'autre'])
            ->assertSessionHasErrors('password');

        $this->put(route('password.update'), ['password' => 'nouveau-secret', 'password_confirmation' => 'nouveau-secret'])
            ->assertRedirect('/espace');

        $learner->refresh();
        $this->assertFalse($learner->must_change_password);
        $this->assertTrue(Hash::check('nouveau-secret', $learner->password));
        $this->get('/espace')->assertOk();
    }
}
