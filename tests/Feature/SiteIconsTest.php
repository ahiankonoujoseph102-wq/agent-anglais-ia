<?php

namespace Tests\Feature;

use App\Support\Seo;
use Tests\TestCase;

/**
 * Icône du site dans les résultats de Google : carrée, taille multiple de 48 pixels (192 × 192 déclarée), fond uni
 * (jamais transparent : sinon un globe gris ou un fond noir), adresses fixes, déclarée sur toutes les pages.
 */
class SiteIconsTest extends TestCase
{
    private const PNG = ['icons/favicon-48.png' => 48, 'icons/favicon-96.png' => 96, 'icons/favicon-192.png' => 192];

    public function test_every_page_declares_the_icons_in_its_head(): void
    {
        foreach (['/', route('plans'), route('offline')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $head = substr($html, 0, (int) strpos($html, '</head>'));

            $this->assertStringContainsString('<link rel="icon" href="'.asset('favicon.ico').'" sizes="16x16 32x32 48x48">', $head, $url);
            foreach (self::PNG as $file => $size) {
                $this->assertStringContainsString('<link rel="icon" href="'.asset($file).'" type="image/png" sizes="'.$size.'x'.$size.'">', $head, $url);
            }
            $this->assertStringContainsString('<link rel="apple-touch-icon" href="'.asset('icons/apple-touch-icon.png').'" sizes="180x180">', $head, $url);
        }
    }

    public function test_the_icons_are_square_multiples_of_48_on_a_solid_background(): void
    {
        foreach (self::PNG + ['icons/apple-touch-icon.png' => 180] as $file => $size) {
            $path = public_path($file);
            $this->assertFileExists($path);
            [$width, $height] = getimagesize($path);
            $this->assertSame([$size, $size], [$width, $height], $file);

            if ($size !== 180) {
                $this->assertSame(0, $size % 48, $file);
            }

            if (function_exists('imagecreatefrompng')) {
                $image = imagecreatefrompng($path);
                // Coin du haut à gauche : blanc opaque (alpha 0 = opaque pour GD).
                $this->assertSame(0, (imagecolorat($image, 0, 0) >> 24) & 0x7F, $file.' : fond transparent');
            }
        }

        // favicon.ico : 16, 32 et 48 pixels.
        $ico = file_get_contents(public_path('favicon.ico'));
        $this->assertSame("\0\0\1\0", substr($ico, 0, 4));
        $this->assertSame(3, unpack('v', substr($ico, 4, 2))[1]);
    }

    public function test_google_can_fetch_them(): void
    {
        foreach (Seo::PRIVATE_PATHS as $path) {
            $this->assertFalse(str_starts_with('/icons/', $path) || str_starts_with('/favicon.ico', $path), $path);
        }

        // robots.txt du site en production : rien ne ferme les icônes.
        $this->app['env'] = 'production';
        $robots = $this->get(route('seo.robots'))->assertOk()->getContent();
        $this->assertStringContainsString('Allow: /', $robots);
        $this->assertStringNotContainsString('Disallow: /icons', $robots);
        $this->assertStringNotContainsString('Disallow: /favicon', $robots);
    }
}
