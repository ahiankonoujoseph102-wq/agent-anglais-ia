<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Réglages de production (Laravel Cloud) : HTTPS, cookies, débogage, proxys, fuseau, planificateur.
 */
class ProductionSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        unset($_SERVER['LARAVEL_CLOUD'], $_ENV['LARAVEL_CLOUD']);

        parent::tearDown();
    }

    /**
     * Valeur de configuration relue dans un processus où APP_ENV vaut « $environment ».
     */
    private function configFile(string $file, string $environment, string $key, string $extraEnvironment = ''): mixed
    {
        $script = sprintf(
            'require %s; $app = require %s; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo json_encode(config(%s));',
            var_export(base_path('vendor/autoload.php'), true),
            var_export(base_path('bootstrap/app.php'), true),
            var_export("{$file}.{$key}", true),
        );

        $output = shell_exec(sprintf('env -u APP_FORCE_HTTPS -u SESSION_SECURE_COOKIE -u TRUSTED_PROXIES %s APP_ENV=%s %s -r %s 2>&1', $extraEnvironment, escapeshellarg($environment), escapeshellarg(PHP_BINARY), escapeshellarg($script)));

        return json_decode((string) $output, true);
    }

    public function test_https_and_secure_cookies_are_on_by_default_in_production_only(): void
    {
        $this->assertTrue($this->configFile('app', 'production', 'force_https'));
        $this->assertTrue($this->configFile('session', 'production', 'secure'));

        $this->assertFalse($this->configFile('app', 'local', 'force_https'));
        $this->assertFalse($this->configFile('session', 'local', 'secure'));

        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }

    public function test_http_requests_are_redirected_to_https_when_forced(): void
    {
        config(['app.force_https' => true]);

        $this->get('http://talkami.test/connexion?x=1')
            ->assertStatus(301)
            ->assertRedirect('https://talkami.test/connexion?x=1');

        $this->get('https://talkami.test/connexion')->assertOk();
    }

    public function test_the_health_check_is_never_redirected(): void
    {
        config(['app.force_https' => true]);

        $this->get('http://talkami.test/up')->assertOk();
    }

    public function test_on_laravel_cloud_the_proxy_protocol_header_is_trusted(): void
    {
        config(['app.force_https' => true]);
        $_SERVER['LARAVEL_CLOUD'] = '1';

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->get('http://talkami.test/connexion', ['X-Forwarded-Proto' => 'https'])
            ->assertOk();
    }

    public function test_elsewhere_a_forged_forwarded_protocol_header_is_not_trusted(): void
    {
        config(['app.force_https' => true]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->get('http://talkami.test/connexion', ['X-Forwarded-Proto' => 'https'])
            ->assertStatus(301);
    }

    public function test_trusted_proxies_default_to_all_in_production_and_none_elsewhere(): void
    {
        $this->assertSame('*', $this->configFile('trustedproxy', 'production', 'proxies'));
        $this->assertNull($this->configFile('trustedproxy', 'local', 'proxies'));
        $this->assertSame('10.0.0.1,10.0.0.2', $this->configFile('trustedproxy', 'production', 'proxies', 'TRUSTED_PROXIES=10.0.0.1,10.0.0.2'));
    }

    public function test_in_production_the_proxy_protocol_header_is_trusted_even_without_the_cloud_flag(): void
    {
        config(['app.force_https' => true, 'trustedproxy.proxies' => '*']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->get('http://talkami.test/connexion', ['X-Forwarded-Proto' => 'https'])
            ->assertOk();
    }

    public function test_debug_mode_is_always_off_in_production(): void
    {
        config(['app.debug' => true]);
        $this->app['env'] = 'production';

        (new AppServiceProvider($this->app))->boot();

        $this->assertFalse(config('app.debug'));
    }

    public function test_debug_mode_is_left_alone_outside_production(): void
    {
        config(['app.debug' => true]);
        $this->app['env'] = 'local';

        (new AppServiceProvider($this->app))->boot();

        $this->assertTrue(config('app.debug'));
    }

    public function test_the_application_timezone_is_lome(): void
    {
        $this->assertSame('Africa/Lome', config('app.timezone'));
        $this->assertSame('Africa/Lome', (require config_path('app.php'))['timezone']);
        $this->assertSame('fr', config('app.locale'));
    }

    public function test_every_scheduled_task_is_declared_and_runs_on_one_server_only(): void
    {
        $events = collect($this->app->make(Schedule::class)->events());

        $names = $events->map(fn (Event $event) => $event->command
            ? trim(str_replace("'", '', explode('artisan', $event->command)[1] ?? ''))
            : $event->description)->all();

        $this->assertEqualsCanonicalizing(['subscriptions:expire', 'live:close-stale', 'scheduler-heartbeat', 'app:prepare-voice --budget=50', 'transcripts:purge'], $names);

        foreach ($events as $event) {
            $this->assertTrue($event->onOneServer, "{$event->command} doit utiliser onOneServer().");
            $this->assertTrue($event->withoutOverlapping, "{$event->command} doit utiliser withoutOverlapping().");
        }

        $this->assertSame('* * * * *', $events->first(fn (Event $e) => str_contains((string) $e->command, 'live:close-stale'))->expression);
        $this->assertSame('0 * * * *', $events->first(fn (Event $e) => str_contains((string) $e->command, 'subscriptions:expire'))->expression);
        $this->assertSame('* * * * *', $events->first(fn (Event $e) => $e->description === 'scheduler-heartbeat')->expression);
        $this->assertSame('0 * * * *', $events->first(fn (Event $e) => str_contains((string) $e->command, 'app:prepare-voice'))->expression);
        $this->assertSame('10 3 * * *', $events->first(fn (Event $e) => str_contains((string) $e->command, 'transcripts:purge'))->expression);
    }

    public function test_every_artisan_command_of_the_app_is_either_scheduled_or_manual(): void
    {
        // Commandes de l'application : planifiées (ci-dessus) ou lancées à la main (administrateur).
        $app = collect(Artisan::all())->keys()->filter(fn (string $name) => in_array($name, ['app:create-admin', 'app:check-deployment', 'subscriptions:expire', 'live:close-stale', 'app:prepare-voice', 'transcripts:purge'], true));

        $this->assertCount(6, $app);
    }

    public function test_the_deployment_never_needs_an_interactive_answer(): void
    {
        // « migrate --force » est la commande de déploiement : elle ne pose aucune question en production.
        $this->artisan('migrate', ['--force' => true, '--no-interaction' => true])->assertSuccessful();
    }

    public function test_env_is_only_read_in_configuration_files(): void
    {
        // Nécessaire à « php artisan optimize » (configuration mise en cache) sur Laravel Cloud.
        $offenders = [];

        foreach (['app', 'routes', 'bootstrap', 'database', 'resources/views'] as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory), RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if (in_array($file->getExtension(), ['php'], true) && preg_match('/(?<![\w>:$])env\(/', (string) file_get_contents($file->getPathname()))) {
                    $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
                }
            }
        }

        $this->assertSame([], $offenders, 'env() ne doit être appelé que dans config/*.php.');
    }
}
