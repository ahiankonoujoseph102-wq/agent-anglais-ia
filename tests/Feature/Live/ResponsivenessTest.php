<?php

namespace Tests\Feature\Live;

use App\Models\ConversationEvent;
use App\Models\ConversationLine;
use App\Models\Lesson;
use App\Models\User;
use App\Support\Live\Responsiveness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rapidité de Teacher Joe (fin octobre 2026, réponses lentes signalées par des abonnés) : délai de réponse et incidents
 * jour par jour dans « Consommation », réglages de reprise automatique donnés au navigateur, assez de connexions pour
 * une longue séance.
 */
class ResponsivenessTest extends TestCase
{
    use RefreshDatabase;

    private function reply(User $user, int $delayMs, string $at, string $type = 'lesson', int $id = 1): void
    {
        static $position = 0;

        ConversationLine::query()->forceCreate([
            'user_id' => $user->id,
            'conversation_type' => $type,
            'conversation_id' => $id,
            'position' => ++$position,
            'speaker' => 'teacher',
            'text' => 'Good answer!',
            'spoken_at' => $at,
            'metrics' => ['delay_ms' => $delayMs],
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function test_the_reply_delay_is_measured_day_by_day_with_the_worst_cases_and_incidents(): void
    {
        $this->travelTo('2026-11-03 18:00:00');
        $user = User::factory()->create();

        foreach ([1000, 1500, 2000, 9000] as $delay) {
            $this->reply($user, $delay, '2026-11-03 10:00:00');
        }
        $this->reply($user, 1200, '2026-11-02 10:00:00', 'assessment');

        foreach (['reconnect', 'auto_nudge', 'auto_nudge', 'switch'] as $kind) {
            ConversationEvent::query()->forceCreate([
                'user_id' => $user->id, 'conversation_type' => 'lesson', 'conversation_id' => 1,
                'kind' => $kind, 'detail' => '', 'happened_at' => '2026-11-03 10:00:00',
            ]);
        }

        $days = Responsiveness::daily(14);

        $this->assertSame('2026-11-03', $days[0]['day']);
        $this->assertSame(4, $days[0]['count']);
        $this->assertSame(3375, $days[0]['average']);
        $this->assertSame(1500, $days[0]['median']);
        $this->assertSame(9000, $days[0]['worst']);
        $this->assertSame(1, $days[0]['slow']);
        $this->assertSame(2, $days[0]['events']['auto_nudge']);
        $this->assertSame(1, $days[0]['events']['switch']);
        $this->assertSame('2026-11-02', $days[1]['day']);

        $this->assertSame(1, Responsiveness::byType(7)['assessment']['count']);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.usage.index'))
            ->assertOk()
            ->assertSee('id="rapidite"', false)
            ->assertSee('Rapidité de '.config('avatar.model.name').', jour par jour')
            ->assertSee('9,0 secondes')
            ->assertSee('Professeur relancé automatiquement : 2');
    }

    public function test_the_automatic_recovery_settings_reach_the_lesson_screen(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-audio=\'[^\']*auto_nudge_seconds/', $html);
        foreach (['auto_reconnect_seconds', 'auto_switch_seconds', 'answer_stall_seconds', 'renew_lead_seconds'] as $key) {
            $this->assertStringContainsString($key, $html);
        }
    }

    public function test_a_long_lesson_gets_enough_connections_for_its_planned_renewals(): void
    {
        config(['services.gemini.token_minutes' => 5, 'services.gemini.max_connections' => 8, 'platform.live.renew_lead_seconds' => 60]);

        // Intensif, 30 minutes : un renouvellement toutes les 4 minutes, plus une marge pour les coupures.
        $this->assertSame(14, (new Lesson(['allowed_seconds' => 1800]))->forceFill(['allowed_seconds' => 1800])->maxConnections());
        // Courte séance : jamais moins que le réglage.
        $this->assertSame(10, (new Lesson)->forceFill(['allowed_seconds' => 900])->maxConnections());
        $this->assertSame(8, (new Lesson)->forceFill(['allowed_seconds' => 120])->maxConnections());
    }
}
