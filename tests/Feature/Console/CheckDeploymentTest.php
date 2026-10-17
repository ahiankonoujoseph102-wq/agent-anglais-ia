<?php

namespace Tests\Feature\Console;

use App\Console\Commands\CheckDeployment;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckDeploymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_healthy_production_setup_passes(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.debug' => false,
            'app.force_https' => true,
            'app.url' => 'https://talkami.example',
            'session.secure' => true,
            'session.driver' => 'database',
            'cache.default' => 'database',
            'filesystems.private_disk' => 'bucket',
            'filesystems.disks.bucket' => ['driver' => 's3'],
            'services.gemini.key' => 'cle-fictive',
        ]);
        Storage::fake('bucket');
        Storage::disk('bucket')->put('payment-proofs/2026/10/a.png', 'x');
        Storage::disk('bucket')->put('attestations/TA-A1-AAAA-BBBB.pdf', 'x');

        // La base de test est SQLite : le contrôle exige MySQL en production, on le simule sans le toucher.
        Cache::put(CheckDeployment::HEARTBEAT_KEY, now()->timestamp - 30);

        $this->artisan('app:check-deployment')
            ->expectsOutputToContain('Mode débogage coupé')
            ->expectsOutputToContain('Captures de paiement enregistrées : 1')
            ->expectsOutputToContain('Attestations enregistrées : 1')
            ->expectsOutputToContain('Dernier passage du planificateur il y a 30 secondes')
            ->doesntExpectOutputToContain('cle-fictive')
            // SQLite n'est pas MySQL : c'est le seul point signalé.
            ->expectsOutputToContain('1 point(s) à corriger.')
            ->assertFailed();

        $this->assertSame([], Storage::disk('bucket')->allFiles('controle'));
    }

    public function test_it_flags_every_dangerous_setting(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.debug' => true,
            'app.force_https' => false,
            'app.url' => 'http://localhost',
            'session.secure' => false,
            'session.driver' => 'file',
            'cache.default' => 'file',
            'filesystems.private_disk' => null,
            'filesystems.default' => 'local',
            'services.gemini.key' => null,
        ]);
        Cache::forget(CheckDeployment::HEARTBEAT_KEY);

        $this->artisan('app:check-deployment')
            ->expectsOutputToContain('APP_DEBUG doit valoir false.')
            ->expectsOutputToContain('APP_FORCE_HTTPS')
            ->expectsOutputToContain('SESSION_SECURE_COOKIE')
            ->expectsOutputToContain('APP_URL doit commencer par https://')
            ->expectsOutputToContain('SESSION_DRIVER doit valoir database')
            ->expectsOutputToContain('le disque du serveur est effacé à chaque déploiement')
            ->expectsOutputToContain('Le planificateur n\'a pas encore tourné')
            ->expectsOutputToContain('GEMINI_API_KEY est vide')
            ->assertFailed();
    }

    public function test_a_stale_scheduler_heartbeat_is_flagged(): void
    {
        Cache::put(CheckDeployment::HEARTBEAT_KEY, now()->timestamp - 900);

        $this->artisan('app:check-deployment')
            ->expectsOutputToContain('Activez « Scheduler » sur le cluster App')
            ->assertFailed();
    }

    public function test_the_scheduled_heartbeat_writes_the_timestamp(): void
    {
        Cache::forget(CheckDeployment::HEARTBEAT_KEY);

        $event = collect($this->app->make(Schedule::class)->events())->first(fn ($e) => $e->description === 'scheduler-heartbeat');
        $event->run($this->app);

        $this->assertEqualsWithDelta(now()->timestamp, Cache::get(CheckDeployment::HEARTBEAT_KEY), 5);
    }
}
