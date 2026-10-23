<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Plan;
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

    public function test_plan_choice_uses_compact_cards_under_a_one_sentence_intro(): void
    {
        $user = User::factory()->level(Level::A2)->create(['country' => 'TG', 'phone' => '+22890112233']);

        $html = $this->actingAs($user)->get('/abonnement')->assertOk()
            ->assertSee('Payez par Mobile Money, puis envoyez la capture d\'écran de la confirmation.', false)
            ->getContent();
        $this->assertSame(count(Plan::all()), substr_count($html, 'plan-compact'));
    }

    public function test_a_known_country_is_folded_so_the_payment_methods_come_first(): void
    {
        $user = User::factory()->level(Level::A2)->create(['country' => 'TG', 'phone' => '+22890112233']);

        $html = $this->actingAs($user)->get('/abonnement/essentiel')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<section class="card pay-section" id="pays"[^>]*\shidden/', $html);
        $this->assertStringContainsString('(d\'après votre numéro)', html_entity_decode($html, ENT_QUOTES));
        // « Modifier » rouvre le pays, avec ou sans JavaScript.
        $this->assertStringContainsString('href="'.route('payment.create', ['plan' => 'essentiel', 'changer' => 'pays']).'#pays" data-pay-edit="pays"', $html);
        $this->assertStringContainsString('target.hidden = false;', file_get_contents(public_path('js/payment-flow.js')));

        $open = $this->actingAs($user)->get('/abonnement/essentiel?changer=pays')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/<section class="card pay-section" id="pays"[^>]*\shidden/', $open);
    }

    public function test_an_interrupted_assessment_puts_the_button_before_the_help_line(): void
    {
        $user = User::factory()->create();
        $user->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 40, 'status' => AssessmentStatus::Abandoned]);

        $html = $this->actingAs($user)->get('/evaluation')->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'result-help'), strpos($html, 'class="result-next"'));
        $this->assertStringContainsString('.result-teacher { width: 88px; height: 88px; }', self::css());
    }
}
