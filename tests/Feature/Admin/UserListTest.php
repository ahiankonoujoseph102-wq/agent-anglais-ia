<?php

namespace Tests\Feature\Admin;

use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_registered_users(): void
    {
        $admin = User::factory()->admin()->create();
        $learner = User::factory()->level(Level::B2)->create(['name' => 'Yawa Agbeko', 'phone' => '+22891112233']);
        Subscription::factory()->for($learner)->forPlan('semaine')->create();

        $this->actingAs($admin)
            ->get('/admin/inscrits')
            ->assertOk()
            ->assertSee('2 compte(s) au total.')
            ->assertSee('Yawa Agbeko')
            ->assertSee('+22891112233')
            ->assertSee('B2')
            ->assertSee('Semaine')
            ->assertSee('Apprenant')
            ->assertSee('Administrateur');
    }

    public function test_admin_can_search_by_name(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Komi Adjovi']);
        User::factory()->create(['name' => 'Esi Dossou']);

        $this->actingAs($admin)
            ->get('/admin/inscrits?q=komi')
            ->assertSee('Komi Adjovi')
            ->assertDontSee('Esi Dossou');
    }

    public function test_admin_can_search_by_phone_in_any_format(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Komi Adjovi', 'phone' => '+22890112233']);
        User::factory()->create(['name' => 'Esi Dossou', 'phone' => '+22899887766']);

        $this->actingAs($admin)
            ->get('/admin/inscrits?q='.urlencode('90 11 22 33'))
            ->assertSee('Komi Adjovi')
            ->assertDontSee('Esi Dossou');

        $this->actingAs($admin)
            ->get('/admin/inscrits?q=8877')
            ->assertSee('Esi Dossou')
            ->assertDontSee('Komi Adjovi');
    }

    public function test_list_is_paginated(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory(30)->create();

        $this->actingAs($admin)
            ->get('/admin/inscrits')
            ->assertOk()
            ->assertSee('Suivant', false);

        $this->actingAs($admin)
            ->get('/admin/inscrits?page=2')
            ->assertOk();
    }
}
