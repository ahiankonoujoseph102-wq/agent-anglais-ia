<?php

namespace Tests\Feature\Console;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_expires_only_finished_active_subscriptions(): void
    {
        $finished = Subscription::factory()->expired()->create();
        $running = Subscription::factory()->create();
        $pending = Subscription::factory()->expired()->create(['status' => SubscriptionStatus::Pending]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Expired, $finished->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $running->fresh()->status);
        $this->assertSame(SubscriptionStatus::Pending, $pending->fresh()->status);
    }
}
