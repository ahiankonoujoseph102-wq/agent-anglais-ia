<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Confiance : un visage sur la page Contact, une issue sur la vérification d'attestation, un pied de page sans phrase
 * répétée, des cartes de pays lisibles sur téléphone.
 */
class TrustPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_shows_the_founder_under_the_whatsapp_button(): void
    {
        $html = html_entity_decode($this->get('/contact')->assertOk()->getContent(), ENT_QUOTES);

        $this->assertStringContainsString('Joseph vous répond', $html);
        $this->assertStringContainsString('Joseph Ahiankonou, fondateur de Talkami', $html);
        $this->assertLessThan(strpos($html, 'class="contact-founder'), strpos($html, 'contact-whatsapp'));
        $this->assertMatchesRegularExpression('#<img src="[^"]*fondateur-joseph-ahiankonou\.webp[^"]*" width="56" height="56" alt="">#', $html);

        config(['platform.founder.photo' => null]);
        $this->get('/contact')->assertOk()->assertDontSee('contact-founder');
    }

    public function test_a_wrong_attestation_code_leaves_a_way_out(): void
    {
        $html = html_entity_decode($this->get('/attestation/verifier/ABCDEF')->getContent(), ENT_QUOTES);
        $this->assertStringContainsString('Vérifiez le code écrit en bas de l\'attestation.', $html);
        $this->assertMatchesRegularExpression('#class="btn btn-accent btn-block" href="[^"]+">.*Retour à l\'accueil#s', $html);
    }

    public function test_footer_no_longer_repeats_the_tagline(): void
    {
        $html = html_entity_decode($this->get('/')->getContent(), ENT_QUOTES);
        $footer = substr($html, strpos($html, '<footer'));
        $this->assertStringNotContainsString('Apprenez l\'anglais à l\'oral avec Teacher Joe, depuis votre téléphone.', $footer);
        $this->assertStringContainsString('Apprenez l\'anglais à l\'oral, depuis votre téléphone.', $footer);
    }

    public function test_feature_cards_put_the_text_on_the_full_width_on_phones(): void
    {
        $css = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('.card.feature > div { display: contents; }', $css);
        $this->assertStringContainsString('.card.feature p { grid-column: 1 / -1; }', $css);
    }
}
