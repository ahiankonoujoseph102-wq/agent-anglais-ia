<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Un seul bouton orange par page pour l'action principale (texte bleu nuit), les autres avec un contour ; jamais deux
 * boutons au même libellé pour deux actions différentes.
 */
class ButtonConsistencyTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    /** Contenu de <main>, sans les scripts. */
    private function main(string $html): string
    {
        $main = substr($html, strpos($html, '<main'), strpos($html, '</main>') - strpos($html, '<main'));

        return html_entity_decode(preg_replace('#<script\b.*?</script>#s', '', $main), ENT_QUOTES);
    }

    public function test_main_actions_are_orange_on_forms_and_error_pages(): void
    {
        $this->assertMatchesRegularExpression('#class="btn btn-accent btn-block btn-lg">Se connecter#', $this->get('/connexion')->getContent());

        $html = $this->main($this->get('/page-qui-n-existe-pas')->assertNotFound()->getContent());
        $this->assertSame(1, substr_count($html, 'btn-accent'));
        $this->assertMatchesRegularExpression('#class="btn btn-accent btn-block"[^>]*>.*Retour à l\'accueil#s', $html);
        $this->assertStringNotContainsString('btn-light', $html);

        $this->assertStringContainsString('btn btn-accent btn-lg btn-block', $this->get('/hors-connexion')->getContent());

        $user = User::factory()->level(Level::A1)->create(['welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->actingAs($user);
        $this->assertStringContainsString('class="btn btn-accent btn-block btn-lg">Enregistrer</button>', $this->get('/compte')->getContent());
        $this->assertStringContainsString('<button type="submit" class="btn btn-accent btn-block btn-lg">Continuer', $this->get('/programme/questionnaire/1')->getContent());
    }

    public function test_dashboard_never_shows_two_identical_buttons_for_two_different_actions(): void
    {
        // Abonné avec un programme, sans évaluation (réinitialisée) : séance du jour ET invitation à l'évaluation.
        $user = User::factory()->level(Level::A1)->create(['welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A1);

        $main = $this->main($this->actingAs($user)->get('/espace')->assertOk()->getContent());

        $this->assertSame(1, substr_count($main, 'Parler avec '.config('avatar.model.name')));
        $this->assertMatchesRegularExpression('#class="btn btn-outline btn-block"><svg[^>]*>.*?</svg>\s*Passer mon évaluation gratuite#s', $main);
        $this->assertSame(1, substr_count($main, 'class="recording-hint"'));
    }

    public function test_the_lighter_display_link_has_a_44_pixel_touch_area(): void
    {
        $css = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('.teacher3d-toggle { min-height: 44px;', $css);
        $this->assertStringContainsString('.band-soft .section-head .eyebrow, .band-soft .eyebrow { color: var(--c-accent-ink); }', $css);
    }
}
