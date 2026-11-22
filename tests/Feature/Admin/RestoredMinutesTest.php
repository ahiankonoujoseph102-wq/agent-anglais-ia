<?php

namespace Tests\Feature\Admin;

use App\Enums\LessonStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Rendre des minutes aujourd'hui » : un élève qui n'a pas pu faire sa séance retrouve son temps du jour tout de
 * suite. Administrateur principal seulement ; jamais pour une séance d'essai.
 */
class RestoredMinutesTest extends TestCase
{
    use RefreshDatabase;

    /** Élève Essentiel (15 minutes par jour) qui a déjà tout utilisé aujourd'hui. */
    private function learnerWithoutTime(): User
    {
        $user = User::factory()->create(['welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $user->lessons()->make()->forceFill([
            'date' => today(), 'allowed_seconds' => 900, 'used_seconds' => 900,
            'started_at' => now()->subMinutes(20), 'ended_at' => now()->subMinutes(5), 'status' => LessonStatus::Interrupted,
        ])->save();

        return $user->fresh();
    }

    public function test_the_admin_gives_back_today_minutes_at_once(): void
    {
        $user = $this->learnerWithoutTime();
        $admin = User::factory()->admin()->create();
        $this->assertSame(0, $user->remainingSecondsToday());

        $this->actingAs($admin)->get(route('admin.users.show', $user))->assertOk()
            ->assertSee('Rendre des minutes aujourd\'hui', false)
            ->assertSee('Rendre ces minutes maintenant');

        $this->actingAs($admin)->post(route('admin.users.minutes', $user), ['minutes' => 15])
            ->assertRedirect(route('admin.users.show', $user).'#minutes')
            ->assertSessionHas('status', fn ($status) => str_contains($status, '15 minutes rendues'));

        $this->assertSame(900, $user->fresh()->remainingSecondsToday());
        $this->assertTrue($user->fresh()->canStartSessionToday());

        // Un second don le même jour s'ajoute ; le lendemain, plus rien de ce don.
        $this->actingAs($admin)->post(route('admin.users.minutes', $user), ['minutes' => 5]);
        $this->assertSame(1200, $user->fresh()->remainingSecondsToday());

        $this->travel(1)->days();
        $this->assertSame(900, $user->fresh()->remainingSecondsToday());
    }

    public function test_limits_and_trial_are_refused(): void
    {
        $user = $this->learnerWithoutTime();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.minutes', $user), ['minutes' => 500])->assertSessionHasErrors('minutes');

        $trial = User::factory()->create();
        Subscription::factory()->for($trial)->forPlan('essentiel')->create(['is_trial' => true, 'granted_days' => 2, 'price' => 0]);
        $this->actingAs($admin)->post(route('admin.users.minutes', $trial), ['minutes' => 10])->assertSessionHasErrors('minutes');
        $this->assertSame(0, $trial->timeBonuses()->count());
    }

    public function test_only_the_main_admin_can_do_it(): void
    {
        $user = $this->learnerWithoutTime();

        $this->actingAs(User::factory()->create())->post(route('admin.users.minutes', $user), ['minutes' => 10])->assertForbidden();
        $this->assertSame(0, $user->timeBonuses()->count());
    }
}
