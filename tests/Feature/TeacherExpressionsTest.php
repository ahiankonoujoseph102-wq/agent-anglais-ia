<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Teacher Joe en expressions sur l'accueil : images de notre modèle 3D, légères, dimensionnées, chargées en différé,
 * avec un texte de remplacement en français, et notées dans le registre des images.
 */
class TeacherExpressionsTest extends TestCase
{
    use RefreshDatabase;

    private const FILES = ['pensif', 'accueil', 'attentif', 'encourageant', 'souriant'];

    public function test_images_are_light_square_webp_files_listed_in_the_credits(): void
    {
        $credits = file_get_contents(base_path('docs/credits-images.md'));
        $this->assertStringContainsString('teacher-joe-{pensif,accueil,attentif,encourageant,souriant}.webp', $credits);

        foreach (self::FILES as $name) {
            $path = public_path("images/teacher-joe-{$name}.webp");
            $this->assertFileExists($path);
            $this->assertLessThan(15 * 1024, filesize($path), $name);
            $this->assertSame([192, 192, IMAGETYPE_WEBP], array_slice(getimagesize($path), 0, 3), $name);
        }
    }

    public function test_home_uses_them_lazily_with_french_alt_text(): void
    {
        $html = html_entity_decode($this->get('/')->assertOk()->getContent(), ENT_QUOTES);

        foreach (self::FILES as $name) {
            $this->assertMatchesRegularExpression('#<img class="(how-face|persuade-face)" src="[^"]*teacher-joe-'.$name.'\.webp[^"]*" width="5\d" height="5\d" alt="Teacher Joe[^"]+" loading="lazy">#', $html, $name);
        }

        // Le bloc « Ce n'est pas un manque d'intelligence… » garde sa place : l'image est posée en pastille.
        $css = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('.persuade-face { position: absolute;', $css);
        $this->assertStringContainsString('.how .how-head .how-face { display: none; }', $css);
    }
}
