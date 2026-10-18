<?php

namespace Tests\Feature\Admin;

use App\Enums\AssessmentStatus;
use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\UsageCost;
use App\Support\Live\LiveUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_duration_tokens_and_estimated_cost_per_learner(): void
    {
        config([
            'services.gemini.pricing.input_per_million' => 3.0,
            'services.gemini.pricing.output_per_million' => 12.0,
        ]);

        $admin = User::factory()->admin()->create();
        $learner = User::factory()->level(Level::A2)->create(['name' => 'Edem Mensah']);

        $learner->assessment()->create([
            'status' => AssessmentStatus::Completed,
            'started_at' => now()->subDay(),
            'allowed_seconds' => 300,
            'used_seconds' => 290,
            'level' => Level::A2,
        ]);

        $lesson = $learner->lessons()->create([
            'date' => today(),
            'started_at' => now()->subMinutes(20),
            'allowed_seconds' => 900,
            'used_seconds' => 852,
            'status' => LessonStatus::Completed,
        ]);
        $lesson->forceFill(['input_tokens' => 100_000, 'output_tokens' => 50_000, 'connections' => 2])->save();

        // 100 000 × 3 $ + 50 000 × 12 $ par million = 0,30 $ + 0,60 $.
        $this->actingAs($admin)
            ->get('/admin/consommation')
            ->assertOk()
            ->assertSee('Edem Mensah')
            ->assertSee('14 minutes 12 secondes')
            ->assertSee('4 minutes 50 secondes')
            ->assertSee('100 000 / 50 000')
            ->assertSee('0,9000', false)
            ->assertSee('Réinitialiser');
    }

    public function test_each_session_is_costed_at_the_price_of_its_own_provider(): void
    {
        config([
            'services.gemini.pricing.input_per_million' => 3.0,
            'services.gemini.pricing.output_per_million' => 12.0,
            'services.openai.realtime_pricing.input_per_million' => 10.0,
            'services.openai.realtime_pricing.output_per_million' => 20.0,
        ]);

        $admin = User::factory()->admin()->create();
        $learner = User::factory()->level(Level::A2)->create(['name' => 'Afi Agbeko']);

        // Séance chez Gemini : 100 000 × 3 $ + 50 000 × 12 $ par million = 0,90 $.
        $learner->lessons()->create(['date' => today(), 'started_at' => now()->subDay(), 'allowed_seconds' => 900, 'used_seconds' => 900, 'status' => LessonStatus::Completed])
            ->forceFill(['input_tokens' => 100_000, 'output_tokens' => 50_000])->save();
        // Séance chez OpenAI : 10 000 × 10 $ + 5 000 × 20 $ par million = 0,20 $ (et non 0,09 $ au tarif de Gemini).
        $learner->lessons()->create(['date' => today(), 'started_at' => now()->subHour(), 'allowed_seconds' => 900, 'used_seconds' => 600, 'status' => LessonStatus::Completed])
            ->forceFill(['input_tokens' => 10_000, 'output_tokens' => 5_000, 'live_provider' => 'openai'])->save();
        // « Mes cours » chez OpenAI : 20 000 × 10 $ + 10 000 × 20 $ par million = 0,40 $.
        $learner->courseSessions()->create(['date' => today(), 'started_at' => now()->subMinutes(30), 'allowed_seconds' => 600, 'used_seconds' => 600])
            ->forceFill(['input_tokens' => 20_000, 'output_tokens' => 10_000, 'live_provider' => 'openai'])->save();

        $costs = LiveUsage::costsByLearner([$learner->id]);
        $this->assertEqualsWithDelta(1.10, $costs[$learner->id]['oral'], 1e-9);
        $this->assertEqualsWithDelta(0.40, $costs[$learner->id]['course'], 1e-9);
        $this->assertEqualsWithDelta(0.20, UsageCost::usd(10_000, 5_000, 'openai'), 1e-9);

        $this->actingAs($admin)
            ->get('/admin/consommation')
            ->assertOk()
            // Par apprenant : séances 0,90 $ + 0,20 $ ; « Mes cours » 0,40 $ (au tarif de Gemini : 0,99 $ et 0,18 $).
            ->assertSeeInOrder(['Afi Agbeko', '1,1000', '0,4000'], false)
            ->assertDontSee('0,9900', false)
            ->assertDontSee('0,1800', false)
            // Dernières séances : la séance OpenAI à son tarif, avec son fournisseur.
            ->assertSeeInOrder(['0,2000', 'OpenAI'], false);
    }

    public function test_learners_cannot_see_usage(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/consommation')->assertForbidden();
    }

    public function test_admin_can_let_a_learner_retake_the_assessment(): void
    {
        $admin = User::factory()->admin()->create();
        $learner = User::factory()->create();
        $learner->assessment()->create([
            'status' => AssessmentStatus::Abandoned,
            'started_at' => now()->subHour(),
            'allowed_seconds' => 300,
            'used_seconds' => 20,
        ]);

        $this->actingAs($admin)
            ->delete("/admin/inscrits/{$learner->id}/evaluation", ['confirmed' => 1])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNull($learner->assessment()->first());
    }
}
