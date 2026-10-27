<?php

namespace Tests\Feature\Live;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\Assessment;
use App\Models\User;
use App\Support\Gemini\UsageCost;
use App\Support\Live\LiveUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\IgnoresSignalSilence;
use Tests\TestCase;

/**
 * Jetons des séances par nature (texte, audio, cache) : enregistrés avec les signaux, et coût estimé au tarif de
 * chaque nature. Les séances d'avant ce détail restent au tarif audio (jamais sous-estimées).
 */
class TokenDetailsTest extends TestCase
{
    use IgnoresSignalSilence, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.key' => 'cle-secrete-du-serveur',
            'services.gemini.pricing' => [
                'input_per_million' => 3.0, 'output_per_million' => 12.0,
                'input_text_per_million' => 0.75, 'output_text_per_million' => 4.5,
                'cached_text_per_million' => 0.75, 'cached_audio_per_million' => 3.0,
            ],
            'services.openai.realtime_pricing' => [
                'input_per_million' => 10.0, 'output_per_million' => 20.0,
                'input_text_per_million' => 0.6, 'output_text_per_million' => 2.4,
                'cached_text_per_million' => 0.06, 'cached_audio_per_million' => 0.3,
            ],
        ]);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton123'])]);
    }

    public function test_signals_add_the_token_details_turns_and_largest_context(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk();

        $signal = [
            'input_tokens' => 24_000, 'output_tokens' => 900,
            'input_text_tokens' => 20_000, 'input_audio_tokens' => 4_000, 'cached_text_tokens' => 0, 'cached_audio_tokens' => 0,
            'output_text_tokens' => 0, 'output_audio_tokens' => 900, 'turns' => 2, 'max_context_tokens' => 12_500,
        ];
        $this->actingAs($user)->postJson('/evaluation/signal', $signal)->assertOk();
        $this->actingAs($user)->postJson('/evaluation/signal', ['max_context_tokens' => 13_000, 'turns' => 1] + $signal)->assertOk();

        $assessment = Assessment::query()->sole();
        $this->assertSame(48_000, $assessment->input_tokens);
        $this->assertSame(40_000, (int) $assessment->input_text_tokens);
        $this->assertSame(8_000, (int) $assessment->input_audio_tokens);
        $this->assertSame(3, $assessment->turns);
        $this->assertSame(13_000, (int) $assessment->max_context_tokens);

        // 40 000 × 0,75 $ + 8 000 × 3 $ + 1 800 × 12 $ par million = 0,03 + 0,024 + 0,0216.
        $this->assertEqualsWithDelta(0.0756, UsageCost::forConversation($assessment), 1e-9);
    }

    public function test_an_old_browser_without_details_is_still_counted_at_the_audio_price(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk();
        $this->actingAs($user)->postJson('/evaluation/signal', ['input_tokens' => 10_000, 'output_tokens' => 1_000])->assertOk();

        $assessment = Assessment::query()->sole();
        $this->assertNull($assessment->input_text_tokens);
        $this->assertSame(0, $assessment->turns);
        // 10 000 × 3 $ + 1 000 × 12 $ par million.
        $this->assertEqualsWithDelta(0.042, UsageCost::forConversation($assessment), 1e-9);
    }

    public function test_totals_mix_detailed_and_old_sessions_and_openai_cache_is_cheap(): void
    {
        $learner = User::factory()->level(Level::A2)->create();
        // Ancienne séance chez Gemini, sans détail : tout au tarif audio (0,30 + 0,12 = 0,42 $).
        $learner->lessons()->create(['date' => today(), 'started_at' => now()->subDay(), 'allowed_seconds' => 900, 'used_seconds' => 900, 'status' => LessonStatus::Completed])
            ->forceFill(['input_tokens' => 100_000, 'output_tokens' => 10_000])->save();
        // Séance détaillée chez OpenAI : 80 000 en cache (texte), 10 000 audio neufs, sortie audio 5 000.
        // 80 000 × 0,06 + 10 000 × 10 + 5 000 × 20 par million = 0,0048 + 0,1 + 0,1 = 0,2048 $.
        $learner->lessons()->create(['date' => today(), 'started_at' => now()->subHour(), 'allowed_seconds' => 900, 'used_seconds' => 900, 'status' => LessonStatus::Completed])
            ->forceFill([
                'input_tokens' => 90_000, 'output_tokens' => 5_000, 'live_provider' => 'openai',
                'input_text_tokens' => 0, 'input_audio_tokens' => 10_000, 'cached_text_tokens' => 80_000, 'cached_audio_tokens' => 0,
                'output_text_tokens' => 0, 'output_audio_tokens' => 5_000, 'turns' => 20, 'max_context_tokens' => 9_000,
            ])->save();

        $this->assertEqualsWithDelta(0.6248, LiveUsage::tokenCost($learner->lessons()->getQuery()), 1e-9);
        $this->assertEqualsWithDelta(0.6248, LiveUsage::costsByLearner([$learner->id])[$learner->id]['oral'], 1e-9);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/consommation')
            ->assertOk()
            ->assertSee('20 réponses mesurées')
            ->assertSee('4 500 jetons relus par réponse en moyenne')
            ->assertSee('20 réponses · contexte le plus grand 9 000 jetons');
    }
}
