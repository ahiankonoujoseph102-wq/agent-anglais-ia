<?php

namespace Tests\Feature\Live;

use App\Enums\Level;
use App\Models\Assessment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\Concerns\IgnoresSignalSilence;
use Tests\TestCase;

/**
 * Fin trop tôt (App\Support\Live\LiveEarlyEnd) : niveau donné après deux minutes d'évaluation, bilan commencé au
 * bout de cinq minutes sur quinze. La première demande n'est pas enregistrée, le professeur continue ; la seconde
 * est acceptée.
 */
class EarlyEndTest extends TestCase
{
    use BuildsPrograms, IgnoresSignalSilence, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-test', 'platform.live.refuse_early_end' => true]);
        Http::fake([
            '*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton']),
            '*/interactions' => Http::response([], 500),
        ]);
        $this->freezeSecond();
    }

    public function test_a_level_given_after_two_minutes_is_refused_once_then_accepted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk();
        $this->travel(2)->minutes();

        $verdict = ['level' => 'A2', 'feedback' => 'Vous comprenez bien les questions simples.'];
        $this->actingAs($user)->postJson('/evaluation/resultat', $verdict)
            ->assertOk()
            ->assertJson(['saved' => false, 'too_early' => true])
            ->assertJsonPath('next_step', fn (string $text) => str_contains($text, 'Do NOT announce any level'));
        $this->assertNull(Assessment::query()->sole()->level);

        // L'élève ne peut pas en dire plus : la seconde demande est acceptée.
        $this->actingAs($user)->postJson('/evaluation/resultat', $verdict)->assertOk()->assertJsonPath('level', 'A2');
    }

    public function test_a_level_after_three_minutes_is_saved_at_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk();
        $this->travel(3)->minutes();

        $this->actingAs($user)->postJson('/evaluation/resultat', ['level' => 'B1', 'feedback' => 'Vous parlez avec aisance.'])
            ->assertOk()->assertJsonPath('level', 'B1');
    }

    public function test_a_summary_after_five_minutes_of_fifteen_is_refused_once_then_accepted(): void
    {
        $user = User::factory()->create();
        $user->level = Level::A1;
        $user->save();
        Subscription::factory()->for($user)->create();
        $this->buildProgram($user);

        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        $this->travel(5)->minutes();

        $report = [
            'lesson_status' => 'completed',
            'summary' => '"This is my sister." : voici ma sœur.',
            'homework' => 'Présenter un membre de sa famille.',
            'scores' => ['fluency' => 3, 'pronunciation' => 3, 'grammar' => 3, 'vocabulary' => 3, 'comprehension' => 3],
        ];

        $this->actingAs($user)->postJson('/seance/bilan', $report)
            ->assertOk()
            ->assertJson(['saved' => false, 'too_early' => true])
            ->assertJsonPath('next_step', fn (string $text) => str_contains($text, 'about 10 minutes'));
        $this->assertNull($user->lessons()->sole()->reported_at);

        $this->actingAs($user)->postJson('/seance/bilan', $report)->assertOk()->assertJson(['saved' => true]);
        $this->assertNotNull($user->lessons()->sole()->reported_at);
    }
}
