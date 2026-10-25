<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Liens TikTok et chaîne WhatsApp de la plateforme : lus dans config/platform.php (contact), affichés dans le pied
 * de page des pages publiques et sur Contact, jamais sur l'accueil, le paiement, l'évaluation ni l'administration.
 */
class SocialLinksTest extends TestCase
{
    use RefreshDatabase;

    private const TIKTOK = 'https://www.tiktok.com/@talkami8';

    private const CHANNEL = 'https://whatsapp.com/channel/0029VbDtfS2HbFV796odSi0n';

    public function test_the_two_links_are_in_the_configuration(): void
    {
        $this->assertSame(self::TIKTOK, config('platform.contact.tiktok'));
        $this->assertSame(self::CHANNEL, config('platform.contact.whatsapp_channel'));
    }

    public function test_public_pages_show_both_links_in_a_new_tab_and_readable_by_screen_readers(): void
    {
        foreach (['/formules', '/a-propos', '/apprendre-anglais/togo', '/contact', '/inscription', '/confidentialite', '/installer-l-application'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('href="'.self::TIKTOK.'" target="_blank" rel="noopener noreferrer"', $html, $path);
            $this->assertStringContainsString('href="'.self::CHANNEL.'" target="_blank" rel="noopener noreferrer"', $html, $path);
            $this->assertStringContainsString('Suivre '.config('platform.name').' sur TikTok', $html, $path);
            $this->assertStringContainsString('Rejoindre la chaîne WhatsApp', $html, $path);
            $this->assertStringContainsString("(s'ouvre dans un nouvel onglet)", $html, $path);
        }

        // Contact : dans la page ET dans le pied de page.
        $contact = $this->get('/contact')->getContent();
        $this->assertSame(2, substr_count($contact, 'href="'.self::TIKTOK.'"'));
        $this->assertStringContainsString('contact-social', $contact);
    }

    public function test_excluded_pages_never_show_them(): void
    {
        $this->get('/')->assertOk()->assertDontSee(self::TIKTOK, false)->assertDontSee(self::CHANNEL, false);

        $learner = User::factory()->create();
        $this->actingAs($learner);
        $this->get(route('payment.plans'))->assertOk()->assertDontSee(self::TIKTOK, false);
        $this->get(route('payment.create', 'semaine'))->assertOk()->assertDontSee(self::TIKTOK, false)->assertDontSee(self::CHANNEL, false);
        $this->get(route('assessment.show'))->assertOk()->assertDontSee(self::TIKTOK, false)->assertDontSee(self::CHANNEL, false);
        $this->get(route('dashboard'))->assertOk()->assertDontSee(self::TIKTOK, false);

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.users.index'))->assertOk()->assertDontSee(self::TIKTOK, false)->assertDontSee(self::CHANNEL, false);
    }

    public function test_the_links_come_from_the_configuration(): void
    {
        config(['platform.contact.tiktok' => 'https://www.tiktok.com/@essai', 'platform.contact.whatsapp_channel' => null]);

        $this->get('/formules')->assertOk()
            ->assertSee('https://www.tiktok.com/@essai', false)
            ->assertDontSee(self::TIKTOK, false)
            ->assertDontSee('Rejoindre la chaîne WhatsApp');

        config(['platform.contact.tiktok' => '']);
        $this->get('/contact')->assertOk()->assertDontSee('sur TikTok')->assertDontSee('social-links', false);
    }
}
