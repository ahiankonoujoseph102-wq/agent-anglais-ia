<?php

namespace Tests\Feature\Admin;

use App\Enums\AssessmentStatus;
use App\Models\ConversationLine;
use App\Models\User;
use App\Support\Live\LiveUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Consommation » : délai de réponse de Teacher Joe (médiane et 90 %), par fournisseur, sur 7 jours, pour mesurer
 * l'effet des réglages de latence.
 */
class ReplyDelayTest extends TestCase
{
    use RefreshDatabase;

    private function lines(User $user, string $provider, array $delays, int $daysAgo = 0): void
    {
        $assessment = $user->assessment()->create([
            'status' => AssessmentStatus::Completed, 'started_at' => now()->subDays($daysAgo), 'allowed_seconds' => 300, 'used_seconds' => 290,
        ]);
        $assessment->forceFill(['live_provider' => $provider])->save();

        foreach ($delays as $i => $delay) {
            $line = ConversationLine::query()->create([
                'user_id' => $user->id, 'conversation_type' => 'assessment', 'conversation_id' => $assessment->id,
                'position' => $i * 2 + 2, 'speaker' => 'teacher', 'text' => 'Very good!', 'spoken_at' => now(), 'metrics' => ['delay_ms' => $delay],
            ]);
            $line->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
            // Réplique de l'élève : jamais comptée comme délai.
            ConversationLine::query()->create([
                'user_id' => $user->id, 'conversation_type' => 'assessment', 'conversation_id' => $assessment->id,
                'position' => $i * 2 + 1, 'speaker' => 'learner', 'text' => 'Hello', 'spoken_at' => now(), 'metrics' => ['delay_ms' => 99999],
            ]);
        }
    }

    public function test_median_and_90_percent_by_provider_over_the_last_7_days(): void
    {
        $this->lines(User::factory()->create(), 'gemini', [800, 1200, 1500, 1700, 2500, 3000, 900, 1100, 1300, 6000]);
        $this->lines(User::factory()->create(), 'openai', [1400]);
        $this->lines(User::factory()->create(), 'gemini', [30000], daysAgo: 9);

        $delays = LiveUsage::replyDelays();

        $this->assertSame(['count' => 10, 'median' => 1300, 'p90' => 3000], $delays['gemini']);
        $this->assertSame(['count' => 1, 'median' => 1400, 'p90' => 1400], $delays['openai']);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.usage.index'))->assertOk()
            ->assertSee('Délai de réponse de Teacher Joe')
            ->assertSee('1,3 seconde')
            ->assertSee('9 réponses sur 10 en 3,0 secondes au plus')
            ->assertSee('Objectif : moins de 2 secondes.');
    }

    public function test_without_measures_the_page_says_so(): void
    {
        $this->assertSame(['count' => 0, 'median' => null, 'p90' => null], LiveUsage::replyDelays()['gemini']);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.usage.index'))->assertOk()
            ->assertSee('aucune réponse mesurée ces 7 derniers jours');
    }
}
