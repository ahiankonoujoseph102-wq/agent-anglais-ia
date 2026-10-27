<?php

namespace Tests\Feature\Live;

use App\Models\Assessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\IgnoresSignalSilence;
use Tests\TestCase;

/**
 * Une séance n'a qu'une page à la fois (App\Support\Live\LivePage) : la même évaluation ouverte dans deux onglets
 * ou sur deux téléphones faisait parler deux Teacher Joe, qui s'entendaient et se répondaient.
 */
class OnePageTest extends TestCase
{
    use IgnoresSignalSilence, RefreshDatabase;

    private const FIRST = 'page-aaaaaaaa-1111';

    private const SECOND = 'page-bbbbbbbb-2222';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton123'])]);
    }

    public function test_the_last_page_to_connect_owns_the_session_and_the_other_one_stops(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withHeader('X-Live-Page', self::FIRST)->postJson('/evaluation/connexion')->assertOk();
        $this->actingAs($user)->withHeader('X-Live-Page', self::FIRST)->postJson('/evaluation/signal')->assertOk();
        $this->assertSame(self::FIRST, Assessment::query()->sole()->live_page);

        // Deuxième onglet (ou deuxième téléphone) : il devient la page de la séance.
        $this->actingAs($user)->withHeader('X-Live-Page', self::SECOND)->postJson('/evaluation/connexion')->assertOk();

        // La première page est refusée, sans que la séance soit close.
        $this->actingAs($user)->withHeader('X-Live-Page', self::FIRST)->postJson('/evaluation/signal')
            ->assertStatus(409)
            ->assertJson(['superseded' => true])
            ->assertJsonPath('message', config('platform.live.messages.superseded'));
        $this->actingAs($user)->withHeader('X-Live-Page', self::FIRST)->postJson('/evaluation/fin')->assertStatus(409);
        $this->actingAs($user)->withHeader('X-Live-Page', self::FIRST)
            ->postJson('/evaluation/resultat', ['level' => 'A2', 'feedback' => 'Vous comprenez bien les questions simples.'])
            ->assertStatus(409);

        $assessment = Assessment::query()->sole();
        $this->assertTrue($assessment->isInProgress());
        $this->assertNull($assessment->level);

        // Son texte n'est pas mêlé à celui de la séance.
        $this->actingAs($user)->withHeader('X-Live-Page', self::FIRST)
            ->postJson(route('live.transcript', ['type' => 'assessment', 'id' => $assessment->id]), ['lines' => [['seq' => 1, 'speaker' => 'learner', 'text' => 'Hello']]])
            ->assertStatus(409);

        // La page de la séance continue normalement.
        $this->actingAs($user)->withHeader('X-Live-Page', self::SECOND)->postJson('/evaluation/signal')->assertOk();
    }

    public function test_a_page_without_identifier_from_before_the_update_is_still_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withHeader('X-Live-Page', self::FIRST)->postJson('/evaluation/connexion')->assertOk();
        $this->flushHeaders();
        $this->actingAs($user)->postJson('/evaluation/signal')->assertOk();
        // L'envoi de fermeture de la page (sendBeacon) porte l'identifiant dans un champ.
        $this->actingAs($user)->post('/evaluation/fin', ['page_id' => self::SECOND])->assertStatus(409);
    }
}
