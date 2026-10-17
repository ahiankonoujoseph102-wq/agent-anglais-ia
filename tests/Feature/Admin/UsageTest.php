<?php

namespace Tests\Feature\Admin;

use App\Enums\AssessmentStatus;
use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\User;
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
            ->delete("/admin/inscrits/{$learner->id}/evaluation")
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNull($learner->assessment()->first());
    }
}
