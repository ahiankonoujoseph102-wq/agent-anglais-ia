<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\IconStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Icônes : une seule famille (Lucide, licence ISC), générée par tools/icons/build-icons.py.
 */
class IconsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_icon_used_in_a_view_exists(): void
    {
        $component = File::get(resource_path('views/components/icon.blade.php'));
        preg_match_all("/^\s+'([a-z0-9-]+)' => '/m", $component, $defined);

        $used = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            preg_match_all('/<x-icon\s+name="([a-z0-9-]+)"/', $file->getContents(), $matches);
            $used = array_merge($used, $matches[1]);
        }
        foreach (File::allFiles(app_path()) as $file) {
            preg_match_all("/'icon' => '([a-z0-9-]+)'/", $file->getContents(), $matches);
            $used = array_merge($used, $matches[1]);
        }

        $this->assertNotEmpty($used);
        $this->assertSame([], array_values(array_diff(array_unique($used), $defined[1])), 'Icônes utilisées mais absentes de <x-icon>.');
        $this->assertStringContainsString('Lucide', $component);
    }

    public function test_lucide_license_is_kept_in_the_repository(): void
    {
        $this->assertStringContainsString('ISC License', File::get(resource_path('icons/LICENSE-lucide.txt')));
        $this->assertStringContainsString('Lucide', File::get(base_path('LICENCES-TIERS.md')));
    }

    public function test_home_steps_have_a_big_number_and_a_meaningful_icon(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(4, substr_count($html, 'class="how-num"'));
        $this->assertSame(4, substr_count($html, '<div class="how-head">'));
        // Icône de chaque question fréquente.
        $this->assertGreaterThanOrEqual(6, preg_match_all('/<summary><svg class="icon"/', $html));
    }

    public function test_one_setting_chooses_the_icon_style_and_the_preview_shows_the_three(): void
    {
        $admin = User::factory()->admin()->create();

        $this->get('/')->assertSee('data-icon-style="night"', false);

        config(['platform.brand.icon_style' => 'gradient']);
        $this->get('/')->assertSee('data-icon-style="gradient"', false);

        // Valeur inconnue : style par défaut.
        config(['platform.brand.icon_style' => 'fluo']);
        $this->get('/')->assertSee('data-icon-style="night"', false);

        config(['platform.brand.icon_style' => 'duotone']);
        $html = $this->actingAs($admin)->get('/admin/icones')->assertOk()
            ->assertSee('Aperçu des icônes')
            ->assertSee('Style appliqué sur le site')
            ->getContent();

        foreach (['night', 'gradient', 'duotone'] as $style) {
            $this->assertStringContainsString('<section class="icon-preview" data-icon-style="'.$style.'"', $html);
        }
        // Au moins 12 icônes par style, dans de vraies cartes.
        $this->assertGreaterThanOrEqual(36, substr_count($html, '<article class="card feature">'));
        $this->assertMatchesRegularExpression('/data-icon-style="duotone"[^>]*>\s*<div class="icon-preview-head">\s*<h2[^>]*>[^<]*<\/h2>\s*<span class="badge badge-accent">Style appliqué/', $html);

        $this->actingAs(User::factory()->create())->get('/admin/icones')->assertForbidden();
    }

    public function test_every_style_is_defined_in_the_tokens(): void
    {
        $tokens = File::get(public_path('css/tokens.css'));

        foreach (array_keys(IconStyle::STYLES) as $style) {
            $this->assertStringContainsString('[data-icon-style="'.$style.'"]', $tokens);
        }
    }
}
