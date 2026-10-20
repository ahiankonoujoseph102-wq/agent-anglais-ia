<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Support\Typography;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Typographie française des pages (App\Support\Typography, middleware ApplyFrenchTypography) :
 * espaces insécables devant « ? ! ; : » et dans les guillemets ; « Mes cours » ne se coupe plus.
 */
class TypographyTest extends TestCase
{
    use RefreshDatabase;

    private const THIN = "\u{202F}";

    private const NBSP = "\u{00A0}";

    public function test_text_gets_non_breaking_spaces_and_short_quotes_stay_whole(): void
    {
        $this->assertSame('Vous êtes élève'.self::THIN.'? Oui'.self::THIN.'! Note'.self::NBSP.': bien'.self::THIN.';', Typography::text('Vous êtes élève ? Oui ! Note : bien ;'));
        $this->assertSame('«'.self::NBSP.'Mes'.self::NBSP.'cours'.self::NBSP.'»', Typography::text('« Mes cours »'));
        // Saut de ligne du gabarit dans les guillemets : une seule espace insécable.
        $this->assertSame('«'.self::NBSP.'Aide'.self::NBSP.'»', Typography::text("«\n        Aide\n    »"));
        // Long passage entre guillemets : seuls les bords sont insécables, il peut passer à la ligne.
        $long = Typography::text('« Une phrase bien trop longue pour rester sur une seule ligne »');
        $this->assertStringStartsWith('«'.self::NBSP.'Une phrase', $long);
        // Adresses : jamais touchées.
        $this->assertSame('https://talkami.net', Typography::text('https://talkami.net'));
    }

    public function test_only_the_text_is_changed_never_tags_attributes_or_scripts(): void
    {
        $html = '<p title="Vous ? « Mes cours »">Vous ? « Mes cours »</p><script>if (a ? b : c) {}</script><style>a:hover{}</style><textarea>Note : brut</textarea>';
        $out = Typography::html($html);

        $this->assertStringContainsString('<p title="Vous ? « Mes cours »">', $out);
        $this->assertStringContainsString('Vous'.self::THIN.'? «'.self::NBSP.'Mes'.self::NBSP.'cours'.self::NBSP.'»</p>', $out);
        $this->assertStringContainsString('<script>if (a ? b : c) {}</script>', $out);
        $this->assertStringContainsString('<textarea>Note : brut</textarea>', $out);
    }

    public function test_pages_are_sent_with_french_typography(): void
    {
        $raw = $this->get('/')->assertOk()->rawContent();

        $this->assertStringContainsString('Élève ou étudiant'.self::THIN.'?', $raw);
        $this->assertStringContainsString('avec «'.self::NBSP.'Mes'.self::NBSP.'cours'.self::NBSP.'»', $raw);
        // Données structurées (JSON-LD) : jamais modifiées.
        $this->assertMatchesRegularExpression('/<script type="application\/ld\+json">[^<]*Comment payer mon abonnement \?/', $raw);

        $user = User::factory()->create(['welcomed_at' => now()]);
        Subscription::factory()->for($user)->create();
        $this->actingAs($user)->get(route('courses.index'))->assertOk();
        $this->assertStringContainsString('«'.self::NBSP.'Mes'.self::NBSP.'cours'.self::NBSP.'»', $this->actingAs($user)->get(route('courses.index'))->rawContent());
    }

    public function test_json_and_files_are_never_touched(): void
    {
        $this->get(route('manifest'))->assertOk()->assertHeader('Content-Type', 'application/manifest+json');
        $this->assertStringNotContainsString(self::NBSP, $this->get(route('seo.robots'))->rawContent());
    }
}
