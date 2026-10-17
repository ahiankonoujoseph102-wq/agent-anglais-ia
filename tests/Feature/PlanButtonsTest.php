<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Boutons des formules : orange plein « Choisir … » partout ; formule recommandée mise en avant.
 */
class PlanButtonsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_plan_list_has_orange_buttons_and_the_recommended_plan(): void
    {
        $learner = User::factory()->create();
        $assessed = User::factory()->create();
        $assessed->assessment()->create([
            'status' => AssessmentStatus::Completed, 'started_at' => now()->subHour(),
            'allowed_seconds' => 300, 'used_seconds' => 300, 'level' => Level::A2,
        ]);

        $pages = [
            'accueil' => $this->get('/'),
            'paiement' => $this->actingAs($learner)->get('/abonnement'),
            'sous le verdict' => $this->actingAs($learner)->get('/evaluation'),
            'résultat' => $this->actingAs($assessed)->get('/evaluation'),
        ];

        foreach ($pages as $page => $response) {
            $html = $response->assertOk()->getContent();

            foreach (['Essentiel', 'Intensif', 'Semaine'] as $name) {
                $this->assertMatchesRegularExpression('/class="btn btn-accent btn-block btn-plan" href="[^"]+">Choisir '.$name.' <svg/', $html, "{$page} : Choisir {$name}");
            }
            $this->assertSame(1, substr_count($html, 'class="plan-badge"'), "{$page} : un seul badge");
            $this->assertStringContainsString('Recommandée', $html);
            $this->assertStringNotContainsString('btn btn-light btn-block" href', $html);
            $this->assertDoesNotMatchRegularExpression('/la plus choisie|plus populaire|\d+ ?%/iu', strip_tags($html));
        }
    }

    public function test_the_recommended_plan_comes_from_configuration(): void
    {
        config(['platform.plans.essentiel.recommended' => false, 'platform.plans.intensif.recommended' => true]);

        $html = $this->get('/')->getContent();
        $this->assertSame(1, substr_count($html, 'class="plan-badge"'));
        $this->assertMatchesRegularExpression('/plan-badge.*?<h3>Intensif<\/h3>/s', $html);
    }
}
