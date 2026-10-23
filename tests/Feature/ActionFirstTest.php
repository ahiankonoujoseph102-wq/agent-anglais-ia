<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Règle du site : sur chaque page, l'action principale suivante est dans le premier écran d'un petit téléphone
 * (360 × 640). Le bouton passe avant les explications ; le détail est plus bas ou replié.
 */
class ActionFirstTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    private static function css(): string
    {
        return file_get_contents(public_path('css/app.css'));
    }

    public function test_dashboard_shows_the_next_step_before_the_journey_and_its_button_under_the_title_on_a_phone(): void
    {
        $user = User::factory()->level(Level::A1)->create(['welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A1);

        foreach ([User::factory()->create(), $user] as $account) {
            $html = $this->actingAs($account)->get('/espace')->assertOk()->getContent();
            $this->assertLessThan(strpos($html, 'class="stepper'), strpos($html, 'class="card next-card'), 'Prochaine étape avant le fil du parcours');
        }

        $css = self::css();
        $this->assertStringContainsString('.next-card:not(.next-step-card) > h2 { order: 1;', $css);
        $this->assertStringContainsString('.next-card:not(.next-step-card) > .btn, .next-card:not(.next-step-card) > .recording-hint { order: 2; }', $css);
    }

    public function test_the_steppers_show_only_the_current_label_on_a_phone(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/espace')->assertOk()->getContent();

        // Chaque étape garde son libellé (lu par les lecteurs d'écran), caché visuellement sauf l'étape en cours.
        $this->assertStringContainsString('<span class="step-label">Évaluation</span>', $html);
        $this->assertStringContainsString('.pay-steps li:not(.current) .step-label { position: absolute;', self::css());
    }

    public function test_sign_up_and_log_in_headers_are_compact_on_a_phone(): void
    {
        $this->get('/inscription')->assertOk()->assertSee('Créer mon compte');
        $this->assertStringContainsString('.auth-head .icon-bubble { display: none; }', self::css());
    }
}
