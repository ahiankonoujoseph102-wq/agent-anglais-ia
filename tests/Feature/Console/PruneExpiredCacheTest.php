<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneExpiredCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_cache_rows_and_locks_are_deleted_and_fresh_ones_kept(): void
    {
        config(['cache.default' => 'database']);
        DB::table('cache')->insert([
            ['key' => 'vieux', 'value' => 's:1:"a";', 'expiration' => now()->subMinute()->getTimestamp()],
            ['key' => 'frais', 'value' => 's:1:"b";', 'expiration' => now()->addHour()->getTimestamp()],
        ]);
        DB::table('cache_locks')->insert([
            ['key' => 'verrou-vieux', 'owner' => 'x', 'expiration' => now()->subMinute()->getTimestamp()],
            ['key' => 'verrou-frais', 'owner' => 'y', 'expiration' => now()->addHour()->getTimestamp()],
        ]);

        $this->artisan('cache:prune-expired')->assertSuccessful();

        $this->assertSame(['frais'], DB::table('cache')->pluck('key')->all());
        $this->assertSame(['verrou-frais'], DB::table('cache_locks')->pluck('key')->all());
    }

    public function test_nothing_happens_with_another_cache_store(): void
    {
        config(['cache.default' => 'array']);

        $this->artisan('cache:prune-expired')->assertSuccessful()->expectsOutputToContain('rien à effacer');
    }

    public function test_it_runs_every_night(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains((string) $event->command, 'cache:prune-expired'));

        $this->assertCount(1, $events);
        $this->assertSame('25 3 * * *', $events->first()->expression);
    }
}
