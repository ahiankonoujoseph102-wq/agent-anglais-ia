<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\SiteGuide;
use App\Support\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ordre des formules : du prix le plus bas au plus haut (Semaine, Essentiel, Intensif), lu dans UN seul endroit
 * (l'ordre de config('platform.plans')), sur toutes les pages qui les listent.
 */
class PlanOrderTest extends TestCase
{
    use RefreshDatabase;

    private const ORDER = ['Semaine', 'Essentiel', 'Intensif'];

    /**
     * Noms des formules dans l'ordre des cartes d'une page.
     *
     * @return list<string>
     */
    private function cardNames(string $html): array
    {
        preg_match_all('/<article[^>]*\bclass="[^"]*\bplan\b[^"]*"[^>]*>.*?<h[23]>(.*?)<\/h[23]>/s', $html, $matches);

        return array_map(fn (string $name) => trim(html_entity_decode($name)), $matches[1]);
    }

    private function assertPlanCards(string $html, string $where): void
    {
        $this->assertSame(self::ORDER, $this->cardNames($html), "Ordre des formules : {$where}");
        // Un seul badge orange : Essentiel.
        $this->assertSame(1, substr_count($html, 'plan-badge-strong'), "Un seul badge orange : {$where}");
        $this->assertSame(1, preg_match_all('/class="[^"]*\bis-recommended\b/', $html), "Une seule carte mise en avant : {$where}");
        $this->assertMatchesRegularExpression('/is-recommended[^>]*>\s*<span class="plan-badge plan-badge-strong">.*?Recommandée.*?<\/span>\s*<h[23]>Essentiel</s', $html, "Essentiel mis en avant : {$where}");
    }

    public function test_the_configuration_gives_the_order_and_the_prices_are_unchanged(): void
    {
        $this->assertSame(['semaine', 'essentiel', 'intensif'], array_keys(config('platform.plans')));
        $this->assertSame(['semaine', 'essentiel', 'intensif'], array_keys(Plan::all()));
        $this->assertSame([2000, 7500, 14000], array_values(array_map(fn (Plan $plan) => $plan->price, Plan::all())));
        $this->assertSame([15, 15, 30], array_values(array_map(fn (Plan $plan) => $plan->dailyMinutes, Plan::all())));
        $this->assertSame(['Pour essayer', 'Recommandée', 'Progrès plus rapides'], array_values(array_map(fn (Plan $plan) => $plan->badge, Plan::all())));
    }

    public function test_changing_the_order_in_the_configuration_changes_every_page(): void
    {
        $plans = config('platform.plans');
        config(['platform.plans' => [
            'intensif' => $plans['intensif'],
            'essentiel' => $plans['essentiel'],
            'semaine' => $plans['semaine'],
        ]]);

        $this->assertSame(['Intensif', 'Essentiel', 'Semaine'], $this->cardNames($this->get('/')->assertOk()->getContent()));
        $this->assertSame(['Intensif', 'Essentiel', 'Semaine'], $this->cardNames($this->get('/formules')->assertOk()->getContent()));
    }

    public function test_home_and_plans_pages_show_the_plans_in_price_order(): void
    {
        $this->assertPlanCards($this->get('/')->assertOk()->getContent(), 'accueil');
        $this->assertPlanCards($this->get('/formules')->assertOk()->getContent(), '/formules');
        $this->assertPlanCards($this->get('/apprendre-anglais/togo')->assertOk()->getContent(), 'page pays');
    }

    public function test_payment_plan_choice_shows_the_plans_in_price_order(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/abonnement')->assertOk()->getContent();

        $this->assertPlanCards($html, 'choix de la formule');
    }

    public function test_assessment_screen_and_result_page_show_the_plans_in_price_order(): void
    {
        $user = User::factory()->create();
        $this->assertPlanCards($this->actingAs($user)->get('/evaluation')->assertOk()->getContent(), 'formules sous le verdict');

        $user->forceFill(['level' => Level::A2])->save();
        $user->assessment()->create([
            'status' => AssessmentStatus::Completed,
            'started_at' => now()->subHour(),
            'ended_at' => now()->subHour()->addMinutes(5),
            'allowed_seconds' => 300,
            'used_seconds' => 300,
            'level' => Level::A2,
            'feedback' => 'Bonne base.',
        ]);

        $this->assertPlanCards($this->actingAs($user->fresh())->get('/evaluation')->assertOk()->getContent(), 'page de résultat');
    }

    public function test_structured_data_and_the_site_guide_follow_the_same_order(): void
    {
        $html = $this->get('/formules')->assertOk()->getContent();
        preg_match_all('/"name":"(Semaine|Essentiel|Intensif)","description"/', $html, $names);
        $this->assertSame(self::ORDER, $names[1]);

        $guide = SiteGuide::text();
        $positions = array_map(fn (string $name) => strpos($guide, "- {$name} : "), self::ORDER);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'Fiche du site : même ordre');
    }
}
