<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function publicPages(): array
    {
        return [
            'accueil' => ['/'],
            'inscription' => ['/inscription'],
            'connexion' => ['/connexion'],
        ];
    }

    #[DataProvider('publicPages')]
    public function test_public_pages_are_open_to_guests(string $uri): void
    {
        $this->get($uri)->assertOk();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function protectedPages(): array
    {
        return [
            'espace apprenant' => ['/espace'],
            'administration' => ['/admin'],
            'liste des inscrits' => ['/admin/inscrits'],
        ];
    }

    #[DataProvider('protectedPages')]
    public function test_guests_are_sent_to_login(string $uri): void
    {
        $this->get($uri)->assertRedirect(route('login'));
    }

    public function test_guest_cannot_log_out(): void
    {
        $this->post('/deconnexion')->assertRedirect(route('login'));
    }

    public function test_learner_can_access_learner_area(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/espace')
            ->assertOk();
    }

    public function test_learner_cannot_access_admin_area(): void
    {
        $learner = User::factory()->create();

        $this->actingAs($learner)->get('/admin/inscrits')->assertForbidden();
        $this->actingAs($learner)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_access_admin_area(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertRedirect('/admin/inscrits');
        $this->actingAs($admin)->get('/admin/inscrits')->assertOk();
    }

    public function test_admin_can_also_open_the_learner_area(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/espace')
            ->assertOk();
    }

    public function test_admin_link_is_only_shown_to_admins(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/espace')
            ->assertDontSee('Administration');

        $this->actingAs(User::factory()->admin()->create())
            ->get('/espace')
            ->assertSee('Administration');
    }
}
