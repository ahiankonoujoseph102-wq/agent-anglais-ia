<?php

namespace Tests\Feature\Live;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Évaluation gratuite, avant « Commencer » : ce qui va se passer, le calme, le droit à l'erreur et le micro expliqué
 * avant que le téléphone ne le demande. « Enregistrer ma séance » reste sous « Commencer ». Évaluation interrompue :
 * un bouton « Écrire sur WhatsApp » direct, plus de renvoi vers la page « Aide ».
 */
class AssessmentBeforeStartTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        return $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();
    }

    public function test_what_will_happen_is_said_before_the_start_button_with_durations_from_the_configuration(): void
    {
        config(['platform.assessment.max_minutes' => 5, 'avatar.model.name' => 'Professeur Kofi']);
        $html = $this->page();
        $card = substr($html, strpos($html, '<div id="intro"'));
        $card = html_entity_decode(substr($card, 0, strpos($card, 'id="start"')), ENT_QUOTES);

        $this->assertStringContainsString('5 minutes d\'anglais à l\'oral avec Professeur Kofi, pour trouver votre niveau.', $card);
        $this->assertStringContainsString(Assessment::totalMinutes().' minutes au plus en tout', $card);
        $this->assertStringContainsString('Installez-vous dans un endroit calme.', $card);
        $this->assertStringContainsString('Vous avez le droit de vous tromper', $card);
        // Le micro est expliqué AVANT le bouton (la demande du téléphone arrive au toucher de « Commencer »).
        $this->assertStringContainsString('après « Commencer », votre téléphone vous demande d\'autoriser le micro. Touchez « Autoriser ».', $card);
        $this->assertStringContainsString('Il ne sert que pendant la séance, pour que Professeur Kofi vous entende.', $card);
    }

    public function test_the_tips_do_not_repeat_it_and_other_sessions_keep_their_tips(): void
    {
        $html = $this->page();
        $this->assertSame(1, substr_count($html, 'Installez-vous dans un endroit calme.'));
        $this->assertStringNotContainsString('Votre navigateur va vous demander l\'accès au micro', $html);

        // Le micro est coupé à la fin de la séance : ce que dit la page est vrai.
        $this->assertStringContainsString('state.audio?.stream.getTracks().forEach((track) => track.stop());', file_get_contents(public_path('js/live-session.js')));
    }

    public function test_recording_stays_right_under_the_start_button(): void
    {
        $html = $this->page();
        $this->assertStringContainsString('Enregistrer ma séance', $html);
        $this->assertStringContainsString("const before = root.dataset.phase === 'intro' ? \$('start') : null;", file_get_contents(public_path('js/live-session.js')));
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

        // Sans numéro WhatsApp : ni bouton, ni promesse.
        config(['platform.contact.whatsapp' => '']);
        $this->actingAs($user)->get('/evaluation')->assertOk()->assertDontSee('result-whatsapp')->assertDontSee('écrivez-nous sur WhatsApp');
    }
}
