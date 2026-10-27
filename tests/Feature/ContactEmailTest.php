<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Adresse e-mail officielle affichée sur le site : contact@talkami.net ; les adresses Gmail ne sont jamais affichées.
 */
class ContactEmailTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICIAL = 'contact@talkami.net';

    private const PRIVATE = ['ahiankonoujoseph102@gmail.com', 'amitalk62@gmail.com'];

    /** Toutes les pages du sitemap, plus les pages publiques non indexées qui montrent des coordonnées. */
    private function publicPages(): array
    {
        preg_match_all('#<loc>https?://[^/]+(/[^<]*)</loc>#', $this->get('/sitemap.xml')->getContent(), $matches);

        return array_unique([...$matches[1], '/installer-l-application', '/page-absente']);
    }

    public function test_every_public_page_shows_the_official_address_and_never_a_gmail_address(): void
    {
        foreach ($this->publicPages() as $path) {
            $html = $this->get($path)->getContent();

            foreach (self::PRIVATE as $address) {
                $this->assertStringNotContainsString($address, $html, $path);
            }
        }

        $this->get('/contact')->assertSee('mailto:'.self::OFFICIAL, false)->assertSee(self::OFFICIAL);
        $this->get('/confidentialite')->assertSee('mailto:'.self::OFFICIAL, false);
        $this->get('/')->assertSee('mailto:'.self::OFFICIAL, false);
    }

    public function test_structured_data_gives_the_official_address(): void
    {
        $html = $this->get('/')->getContent();
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $blocks);
        $organization = collect($blocks[1])->map(fn ($json) => json_decode($json, true))->firstWhere('@type', 'Organization');

        $this->assertSame(self::OFFICIAL, $organization['email']);
    }

    public function test_an_old_gmail_address_saved_in_the_settings_is_removed_but_another_choice_is_kept(): void
    {
        $migration = require database_path('migrations/2026_11_11_000001_use_official_contact_email.php');

        foreach ([...self::PRIVATE, 'equipe@exemple.tg'] as $saved) {
            Setting::put('contact.email', $saved);
            $migration->up();
            $expected = $saved === 'equipe@exemple.tg' ? $saved : null;
            $this->assertSame($expected, DB::table('settings')->where('key', 'contact.email')->value('value'), $saved);
        }

        Setting::put('contact.email', 'amitalk62@gmail.com');
        $migration->up();
        SiteSettings::flush();
        $this->get('/contact')->assertSee(self::OFFICIAL)->assertDontSee('amitalk62@gmail.com');
    }
}
