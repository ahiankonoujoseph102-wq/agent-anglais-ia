<?php

namespace Tests\Feature\Console;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin(): void
    {
        $this->artisan('app:create-admin', ['--name' => 'Joseph', '--phone' => '90 00 00 09', '--password' => 'secret123'])
            ->assertSuccessful();

        $admin = User::firstWhere('phone', '+22890000009');
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertSame('Joseph', $admin->name);
        $this->assertTrue(Hash::check('secret123', $admin->password));
    }

    public function test_it_promotes_an_existing_learner_without_changing_the_password(): void
    {
        $user = User::factory()->create(['phone' => '+22890000009']);

        $this->artisan('app:create-admin', ['--phone' => '+22890000009', '--password' => ''])
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->isAdmin());
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_it_refuses_an_invalid_phone_or_short_password(): void
    {
        $this->artisan('app:create-admin', ['--name' => 'X', '--phone' => '12', '--password' => 'secret123'])
            ->assertFailed();

        $this->artisan('app:create-admin', ['--name' => 'X', '--phone' => '90000009', '--password' => 'court'])
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
