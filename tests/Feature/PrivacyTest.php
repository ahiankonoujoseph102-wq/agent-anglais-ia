<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Page « Confidentialité » (/confidentialite) : publique, fidèle à ce que fait le code, liée partout.
 */
class PrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_privacy_page_is_public_and_describes_what_the_site_really_does(): void
    {
        config(['platform.transcripts.keep_days' => 90]);

        $this->get('/confidentialite')
            ->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSeeText('Confidentialité')
            ->assertSeeText('votre nom, votre numéro de téléphone')
            ->assertSeeText('Votre pays')
            ->assertSeeText('la capture d\'écran de confirmation que vous envoyez')
            ->assertSeeText('les photos ou le PDF des leçons d\'anglais que vous envoyez')
            ->assertSeeText('Le texte de vos conversations')
            ->assertSeeText('Votre voix n\'est jamais enregistrée.')
            ->assertSeeText('Google (Gemini)')
            ->assertSeeText('OpenAI')
            ->assertSeeText('90 jours, puis il est effacé automatiquement')
            ->assertSeeText('jusqu\'à la suppression de votre compte')
            ->assertSeeText('Seul l\'administrateur de Talkami')
            ->assertSeeText('Pour les parents des élèves mineurs')
            ->assertSeeText('Joseph Ahiankonou')
            ->assertSee('mailto:ahiankonoujoseph102@gmail.com', false)
            ->assertSee('tel:+22872240231', false)
            ->assertSeeText('+228 72 24 02 31');
    }

    public function test_the_retention_of_conversations_comes_from_the_configuration(): void
    {
        config(['platform.transcripts.keep_days' => 30]);

        $this->get('/confidentialite')->assertSeeText('30 jours, puis il est effacé automatiquement');
    }

    public function test_the_link_is_in_the_footer_of_every_page_and_on_the_sign_up_form(): void
    {
        $link = 'href="'.route('privacy').'"';

        $this->get('/')->assertSee($link, false);
        $this->get('/contact')->assertSee($link, false);
        $this->actingAs(User::factory()->create())->get('/espace')->assertSee($link, false);
        $this->actingAs(User::factory()->admin()->create())->get('/admin/inscrits')->assertSee($link, false);
        $this->assertStringContainsString($link, view('offline')->render());

        auth()->logout();
        $this->get('/inscription')
            ->assertSee($link, false)
            ->assertSeeText('En créant un compte, vous acceptez notre politique de confidentialité.');
    }

    public function test_the_page_is_in_the_sitemap(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee(rtrim((string) config('app.url'), '/').'/confidentialite', false);
    }
}
