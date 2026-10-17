<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Bouton « œil » des champs mot de passe (composant <x-input type="password">, piloté par app.js).
 */
class PasswordToggleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: list<string>, 2: bool}>
     */
    public static function pages(): array
    {
        return [
            'connexion' => ['/connexion', ['password'], false],
            'inscription' => ['/inscription', ['password', 'password_confirmation'], false],
            'nouveau mot de passe' => ['/mot-de-passe/nouveau', ['password', 'password_confirmation'], true],
        ];
    }

    /**
     * @param  list<string>  $fields
     */
    #[DataProvider('pages')]
    public function test_every_password_field_has_a_show_hide_button(string $url, array $fields, bool $loggedIn): void
    {
        if ($loggedIn) {
            $user = User::factory()->create();
            $user->must_change_password = true;
            $user->save();
            $this->actingAs($user);
        }

        $html = $this->get($url)->assertOk()->getContent();

        foreach ($fields as $field) {
            $this->assertStringContainsString('id="'.$field.'"', $html);
            $this->assertStringContainsString('data-password-toggle="'.$field.'" aria-controls="'.$field.'"', $html);
        }

        $this->assertSame(count($fields), substr_count($html, 'aria-label="Afficher le mot de passe"'));
        // Le bouton n'est jamais un bouton d'envoi du formulaire.
        $this->assertSame(count($fields), substr_count($html, '<button type="button" class="password-toggle"'));
        $this->assertStringContainsString('js/app.js', $html);
    }

    public function test_the_script_switches_the_accessible_label(): void
    {
        $script = file_get_contents(public_path('js/app.js'));

        $this->assertStringContainsString("'Masquer le mot de passe'", $script);
        $this->assertStringContainsString("'Afficher le mot de passe'", $script);
    }

    public function test_the_password_is_never_written_back_into_the_field(): void
    {
        $this->from('/connexion')->post('/connexion', ['phone' => '90 12 34 56', 'password' => 'mauvais-secret']);

        $this->get('/connexion')->assertDontSee('mauvais-secret');
    }
}
