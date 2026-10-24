<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Teacher Joe pensif sur l'accueil : UNE image de notre modèle 3D, légère, dimensionnée, chargée en différé, avec un
 * texte de remplacement en français, notée dans le registre des images, seulement dans l'encadré de réponse.
 * Les étapes gardent leurs icônes d'origine.
 */
class TeacherExpressionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_pensive_image_exists_and_is_listed_in_the_credits(): void
    {
        $credits = file_get_contents(base_path('docs/credits-images.md'));
        $this->assertStringContainsString('`public/images/teacher-joe-pensif.webp`', $credits);

        $path = public_path('images/teacher-joe-pensif.webp');
        $this->assertFileExists($path);
        $this->assertLessThan(15 * 1024, filesize($path));
        $this->assertSame([192, 192, IMAGETYPE_WEBP], array_slice(getimagesize($path), 0, 3));

        foreach (['accueil', 'attentif', 'encourageant', 'souriant'] as $name) {
            $this->assertFileDoesNotExist(public_path("images/teacher-joe-{$name}.webp"), $name);
            $this->assertStringNotContainsString("teacher-joe-{$name}", $credits, $name);
        }
    }

    public function test_home_shows_the_pensive_badge_in_the_answer_box_only(): void
    {
        $html = html_entity_decode($this->get('/')->assertOk()->getContent(), ENT_QUOTES);

        $this->assertMatchesRegularExpression('#<p class="persuade-answer"><img class="persuade-face" src="[^"]*teacher-joe-pensif\.webp[^"]*" width="56" height="56" alt="Teacher Joe[^"]+" loading="lazy">#', $html);
        $this->assertSame(1, substr_count($html, 'teacher-joe-pensif.webp'));

        $css = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('.persuade-face { position: absolute;', $css);
        $this->assertStringContainsString('@media (min-width: 640px) { .persuade-face { display: none; } }', $css);
    }

    public function test_steps_keep_their_original_icons(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('how-face', $html);
        $this->assertStringNotContainsString('how-face', file_get_contents(public_path('css/app.css')));
        $view = file_get_contents(resource_path('views/home.blade.php'));
        foreach ([1 => 'user-plus', 2 => 'mic', 3 => 'mobile', 4 => 'calendar-check'] as $number => $icon) {
            $this->assertStringContainsString('<span class="how-num">'.$number.'</span><span class="icon-bubble"><x-icon name="'.$icon.'" /></span>', $view, $icon);
            $this->assertMatchesRegularExpression('#<span class="how-num">'.$number.'</span><span class="icon-bubble"><svg #', $html, $icon);
        }
    }
}
