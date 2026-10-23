<?php

namespace Tests\Feature\Live;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\Assessment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Avant « Commencer » (évaluation gratuite et séance du jour) : le bouton orange tout de suite sous Teacher Joe, UNE
 * ligne courte, la phrase du micro, « Enregistrer ma séance », puis tout le reste replié sous « Avant de commencer ».
 * Évaluation interrompue : un bouton « Écrire sur WhatsApp » direct, plus de renvoi vers la page « Aide ».
 */
class AssessmentBeforeStartTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    private function assessmentPage(): string
    {
        return html_entity_decode($this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent(), ENT_QUOTES);
    }

    private function lessonPage(): string
    {
        $user = User::factory()->level(Level::A1)->create(['welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A1);

        return html_entity_decode($this->actingAs($user)->get('/seance')->assertOk()->getContent(), ENT_QUOTES);
    }

    /** Partie de la page entre deux repères. */
    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $this->assertNotFalse($start, $from);
        $end = strpos($html, $to, $start);
        $this->assertNotFalse($end, $to);

        return substr($html, $start, $end - $start);
    }

    public function test_start_comes_before_every_explanation_on_both_pages(): void
    {
        foreach (['évaluation' => $this->assessmentPage(), 'séance du jour' => $this->lessonPage()] as $name => $html) {
            $start = strpos($html, 'id="start"');
            $this->assertNotFalse($start, $name);

            // Dans la carte : le bouton d'abord, puis sa ligne, puis « Avant de commencer » (replié, jamais ouvert d'office).
            $this->assertLessThan(strpos($html, 'class="intro-text"'), $start, $name);
            $this->assertLessThan(strpos($html, 'class="before-start"'), $start, $name);
            $this->assertStringContainsString('<details class="before-start">', $html, $name);
            $this->assertStringNotContainsString('<details class="before-start" open', $html, $name);

            // Toutes les explications sont après le bouton (le texte de la carte comme les conseils).
            $card = $this->between($html, '<div id="intro"', 'class="live-bar"');
            foreach (['Installez-vous dans un endroit calme.', 'Avec des écouteurs', 'vous avez le droit de vous tromper', 'Si la connexion est coupée'] as $tip) {
                $this->assertGreaterThan(strpos($card, 'id="start"'), strpos($card, $tip), "{$name} : {$tip}");
            }

            // Une ligne courte et la phrase du micro, juste sous le bouton.
            $block = $this->between($html, '<div id="start-block"', 'id="arriving"');
            $this->assertSame(1, substr_count($block, '<p class="intro-text">'), $name);
            $this->assertStringContainsString('Touchez « Autoriser » quand le téléphone demande le micro.', $block, $name);
        }
    }

    public function test_the_assessment_line_and_folded_details_come_from_the_configuration(): void
    {
        config(['platform.assessment.max_minutes' => 5, 'avatar.model.name' => 'Professeur Kofi']);
        $html = $this->assessmentPage();

        $block = $this->between($html, '<div id="start-block"', 'id="arriving"');
        $this->assertStringContainsString('<p class="intro-text">5 minutes à l\'oral avec Professeur Kofi, pour trouver votre niveau.</p>', $block);

        // Replié : ce qui va se passer, le micro expliqué avant que le téléphone ne le demande, le calme, le droit à l'erreur.
        $details = $this->between($html, '<details class="before-start">', '</details>');
        $this->assertStringContainsString(Assessment::totalMinutes().' minutes au plus en tout', $details);
        $this->assertStringContainsString('Le micro : après « Commencer », votre téléphone vous demande de l\'autoriser. Touchez « Autoriser ». Il ne sert que pendant la séance, pour que Professeur Kofi vous entende.', $details);
        $this->assertStringContainsString('Installez-vous dans un endroit calme.', $details);
        $this->assertStringContainsString('vous avez le droit de vous tromper', $details);
    }

    public function test_the_lesson_shows_the_time_left_under_the_button_and_the_rest_folded(): void
    {
        $html = $this->lessonPage();

        $this->assertStringContainsString('<p class="intro-text">Il vous reste 15 minutes aujourd\'hui.</p>', $this->between($html, '<div id="start-block"', 'id="arriving"'));
        $this->assertStringContainsString('Ce temps du jour est partagé avec « Mes cours ».', $this->between($html, '<details class="before-start">', '</details>'));
    }

    public function test_recording_stays_right_under_the_start_block_and_the_phone_layout_only_changes_before_start(): void
    {
        $this->assertStringContainsString('Enregistrer ma séance', $this->assessmentPage());

        $script = file_get_contents(public_path('js/live-session.js'));
        $this->assertStringContainsString("const before = root.dataset.phase === 'intro' ? (\$('start-block') ?? \$('start')) : null;", $script);
        $this->assertStringContainsString('before.after(rec.box, rec.unsupported);', $script);

        // Téléphone en portrait : la carte de « Commencer » passe juste après Teacher Joe, AVANT « Commencer » seulement.
        $css = file_get_contents(public_path('css/app.css'));
        $phone = substr($css, strpos($css, '@media (max-width: 899.98px) and (orientation: portrait) {'));
        $phone = substr($phone, 0, strpos($phone, "\n}\n"));
        $this->assertStringContainsString('.live[data-phase="intro"] .live-panel > #intro { order: 4;', $phone);
        $this->assertStringContainsString('.live[data-phase="intro"] .live-stage > .teacher { order: 1;', $phone);
        foreach (explode("\n", trim($phone)) as $line) {
            if (str_contains($line, '{') && ! str_starts_with(trim($line), '@media')) {
                $this->assertStringContainsString('[data-phase="intro"]', $line, 'Seulement avant « Commencer » : '.$line);
            }
        }
    }

    public function test_an_interrupted_assessment_offers_a_direct_whatsapp_button(): void
    {
        $user = User::factory()->create();
        $user->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 40, 'status' => AssessmentStatus::Abandoned]);

        $html = $this->actingAs($user)->get('/evaluation')->assertOk()
            ->assertSee('Nous n\'avons pas pu mesurer votre niveau', false)
            ->assertDontSee('page « Aide »', false)
            ->assertSee('Écrire sur WhatsApp')
            ->getContent();

        $number = preg_replace('/\D+/', '', (string) config('platform.contact.whatsapp'));
        $this->assertMatchesRegularExpression('#class="btn btn-whatsapp result-whatsapp" href="https://wa\.me/'.$number.'\?text=[^"]+" target="_blank" rel="noopener"#', $html);

        config(['platform.contact.whatsapp' => '']);
        $this->actingAs($user)->get('/evaluation')->assertOk()->assertDontSee('result-whatsapp')->assertDontSee('écrivez-nous sur WhatsApp');
    }
}
