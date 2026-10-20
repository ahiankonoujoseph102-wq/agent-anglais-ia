<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Finition du design : accueil, questionnaire, espace apprenant, séance, pied de page.
 * Les vérifications visuelles (captures aux largeurs 360, 390, 768 et 1280) sont faites dans Chromium ;
 * ces tests gardent les règles qui les rendent justes.
 */
class DesignFinishTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    private static function css(): string
    {
        return (string) file_get_contents(public_path('css/app.css'));
    }

    /**
     * Contenu des blocs « @media (…) { … } » dont la condition contient $condition.
     */
    private static function mediaBlocks(string $condition): string
    {
        $css = self::css();
        $blocks = '';
        $offset = 0;

        while (($start = strpos($css, '@media', $offset)) !== false) {
            $open = strpos($css, '{', $start);
            $head = substr($css, $start, $open - $start);
            $depth = 0;

            for ($i = $open; $i < strlen($css); $i++) {
                $depth += $css[$i] === '{' ? 1 : ($css[$i] === '}' ? -1 : 0);

                if ($depth === 0) {
                    break;
                }
            }

            if (str_contains($head, $condition)) {
                $blocks .= substr($css, $open + 1, $i - $open - 1)."\n";
            }

            $offset = $i;
        }

        return $blocks;
    }

    public function test_home_sections_alternate_with_a_single_dark_one(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'band-dark'));
        $this->assertMatchesRegularExpression('/class="section band band-dark"[^>]*aria-labelledby="difference"/', $html);
        $this->assertGreaterThanOrEqual(3, substr_count($html, 'band band-soft'));
        // Fond animé : trois formes floues (deux pseudo-éléments + une) et trois bulles, dont une sur ordinateur seulement.
        $this->assertStringContainsString('class="hero-blob"', $html);
        $this->assertStringContainsString('How are you?', $html);
        $this->assertStringContainsString('class="hero-bubble third"', $html);
    }

    public function test_home_animations_are_desktop_only_and_respect_reduced_motion(): void
    {
        $css = self::css();
        $desktop = self::mediaBlocks('min-width: 900px');
        $reduce = self::mediaBlocks('prefers-reduced-motion: reduce');

        // Téléphone : aucune animation sur les formes ni sur les bulles du haut de page.
        foreach (['.hero::before {', '.hero::after {', '.hero-blob {', '.hero-bubble {'] as $selector) {
            $rule = substr($css, strpos($css, "\n".$selector) ?: 0, 600);
            $this->assertStringNotContainsString('animation', strtok($rule, '}'), $selector);
            $this->assertStringContainsString(rtrim($selector, ' {'), $desktop);
        }

        $this->assertStringContainsString('.hero-bubble.third { display: none; }', $css);

        foreach (['.hero::before', '.hero::after', '.hero-blob', '.hero-bubble'] as $selector) {
            $this->assertStringContainsString($selector, $reduce);
        }

        $this->assertStringContainsString('animation: none !important', $reduce);
        $this->assertStringContainsString('.js .reveal { opacity: 1; transform: none; }', $reduce);
    }

    public function test_home_cards_keep_their_buttons_aligned_at_the_bottom(): void
    {
        $grid = self::mediaBlocks('min-width: 720px');

        $this->assertStringContainsString('.audience-body .btn { margin-top: auto;', $grid);
        $this->assertStringContainsString('grid-template-rows: auto 1fr; align-items: stretch;', $grid);
        $this->assertStringContainsString('.audience-body { display: flex; flex-direction: column;', $grid);

        // Note « Uniquement pour les cours d'anglais » : petit texte gris avec icône.
        $this->assertStringContainsString('.audience .help-only { font-size: var(--fs-xs); font-weight: var(--fw-normal); color: var(--c-muted);', self::css());
        $this->get('/')->assertSee('<p class="help-only"><svg', false);
    }

    public function test_home_steps_levels_payments_and_questions(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Niveaux : échelle reliée, nom sous chaque niveau.
        $this->assertSame(6, substr_count($html, 'class="level-dot"'));
        // Moyens de paiement groupés par pays, en étiquettes.
        $this->assertStringContainsString('class="pay-countries"', $html);
        $this->assertMatchesRegularExpression('/<span class="pay-country">Togo<\/span>\s*<span class="tags">\s*<span class="tag">Mixx by Yas<\/span>\s*<span class="tag">Flooz<\/span>/', $html);
        $this->assertStringContainsString('faq-section', $html);

        $css = self::css();
        // Étapes : petits traits retirés, vraie ligne entre les numéros sur ordinateur.
        $this->assertStringNotContainsString('.how-head::after', $css);
        $this->assertStringContainsString('.how::before', self::mediaBlocks('min-width: 900px'));
        $this->assertStringContainsString('.faq-section .faq { margin-inline: auto; max-width: 760px; }', $css);
    }

    public function test_questionnaire_choices_never_overflow_the_card(): void
    {
        $css = self::css();

        // Jours et profils : grille souple (jamais plus de colonnes que la carte n'en contient).
        $this->assertStringContainsString('.days { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 8.5rem), 1fr));', $css);
        $this->assertStringContainsString('.profiles { grid-template-columns: repeat(auto-fill, minmax(min(100%, 10.5rem), 1fr)); }', $css);
        $this->assertStringContainsString('.days .option, .profiles .option { min-width: 0;', $css);
        $this->assertDoesNotMatchRegularExpression('/\.days \{ grid-template-columns: repeat\(\d/', $css);
    }

    public function test_narrow_pages_get_a_help_panel_on_large_screens_only(): void
    {
        $css = self::css();
        $this->assertStringContainsString('.with-aside-panel { display: none; }', $css);
        $this->assertStringContainsString('.with-aside-panel { display: block;', self::mediaBlocks('min-width: 900px'));

        foreach (['/connexion', '/inscription'] as $page) {
            $this->get($page)->assertOk()
                ->assertSee('class="with-aside-panel card"', false)
                ->assertSee(config('avatar.model.name'))
                ->assertSeeInOrder(['Gratuite et sans engagement', 'Votre niveau, de A1 à C2', 'Paiement par Mobile Money']);
        }

        $user = User::factory()->create();
        $user->level = Level::A2;
        $user->save();
        Subscription::factory()->for($user)->create();

        $this->actingAs($user)->get(route('program.questionnaire', 1))->assertOk()
            ->assertSee('À quoi servent vos réponses');

        $program = $this->buildProgram($user->fresh());
        $this->actingAs($user)->get(route('program.answers.edit', [$program, 3]))->assertOk()
            ->assertSee('À quoi servent vos réponses')
            ->assertSee('class="days"', false);
    }
}
