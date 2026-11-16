<?php

namespace Tests\Feature\Live;

use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Live\LiveProviders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Une clé Gemini à sa limite : Google accorde quand même le jeton et ne ferme la connexion qu'au NAVIGATEUR. Le
 * navigateur le signale (« exhausted ») : la clé est mise de côté et la séance, même commencée, continue avec la
 * clé suivante, au lieu de retomber sans fin sur la même clé épuisée.
 */
class QuotaCloseSwitchTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.key' => 'cle-une',
            'services.gemini.key_2' => 'cle-deux',
            'services.gemini.key_3' => 'cle-trois',
            'services.openai.key' => null,
            'services.live.providers' => ['gemini', 'gemini2', 'gemini3'],
        ]);

        Http::fake([
            '*/auth_tokens' => fn (Request $request) => Http::response(['name' => 'auth_tokens/'.$request->header('x-goog-api-key')[0]]),
            '*/interactions' => Http::response([], 503),
        ]);
    }

    private function subscriber(): User
    {
        $user = User::factory()->level(Level::A2)->create(['name' => 'Ama', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user);

        return $user->fresh();
    }

    public function test_a_key_closed_at_its_limit_mid_lesson_is_set_aside_and_the_next_key_continues(): void
    {
        $user = $this->subscriber();

        $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->assertJsonPath('provider', 'gemini');

        // En pleine séance, Google ferme la connexion : limite atteinte. « Reprendre » le signale.
        $this->actingAs($user)->postJson('/seance/connexion', ['exhausted' => 'gemini'])
            ->assertOk()->assertJsonPath('provider', 'gemini2');

        $this->assertTrue(LiveProviders::isPaused('gemini'));
        $this->assertSame('gemini2', $user->lessons()->first()->fresh()->liveProvider());

        // La séance suivante commence directement avec une clé qui marche.
        $this->assertSame(['gemini2', 'gemini3', 'gemini'], LiveProviders::ordered());
    }

    public function test_without_a_signal_the_lesson_keeps_its_provider(): void
    {
        $user = $this->subscriber();

        $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->assertJsonPath('provider', 'gemini');
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->assertJsonPath('provider', 'gemini');
        $this->assertFalse(LiveProviders::isPaused('gemini'));
    }

    public function test_an_unknown_provider_is_refused(): void
    {
        $this->actingAs($this->subscriber())->postJson('/seance/connexion', ['exhausted' => 'inconnu'])->assertUnprocessable();
    }

    public function test_the_browser_reports_a_closure_at_the_limit_and_switches(): void
    {
        $js = file_get_contents(public_path('js/live-session.js'));

        $this->assertStringContainsString('function isQuotaClose(', $js);
        $this->assertStringContainsString('exhausted }', $js);
        $this->assertStringContainsString('state.exhaustedProvider = transport.provider;', $js);
    }
}
