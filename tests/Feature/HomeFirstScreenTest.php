<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Premier écran de l'accueil (visiteurs venus de TikTok et de WhatsApp) : Teacher Joe en bandeau tout en haut sur
 * téléphone, accroche courte, bouton orange et réassurance AVANT le reste de l'accroche (anglais américain, élèves,
 * points), sans rien supprimer.
 */
class HomeFirstScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_button_and_the_reassurance_come_right_after_a_short_promise(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $hero = substr($html, strpos($html, '<section class="hero"'), strpos($html, '</section>', strpos($html, '<section class="hero"')) - strpos($html, '<section class="hero"'));

        // Dans l'ordre : titre, une phrase, le bouton, la réassurance, puis le reste de l'accroche.
        $order = ['id="promesse"', 'class="lead"', 'hero-cta', 'hero-reassure', 'hero-more', 'anglais américain', 'Élève ou étudiant', 'hero-points'];
        $positions = array_map(fn (string $needle) => strpos($hero, $needle), $order);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);

        // Une seule phrase avant le bouton.
        preg_match('/<p class="lead">(.*?)<\/p>/s', $hero, $lead);
        $this->assertSame(1, preg_match_all('/[.!?](\s|$)/u', strip_tags($lead[1])));

        // Réassurance : des faits vérifiés, la durée lue dans la configuration.
        $minutes = config('platform.assessment.max_minutes');
        $this->assertStringContainsString('Évaluation orale gratuite de '.$minutes.' minutes : votre niveau d\'anglais, de A1 à C2.', $hero);
        // Teacher Joe : nom et accroche lus dans la configuration.
        $this->assertStringContainsString('<strong>'.config('avatar.model.name').'</strong> '.e(config('platform.brand.tagline')), $hero);
    }

    public function test_on_phones_teacher_joe_comes_first_as_a_light_banner(): void
    {
        $css = file_get_contents(public_path('css/app.css'));
        $phone = substr($css, strpos($css, '@media (max-width: 899.98px)'));
        $phone = substr($phone, 0, strpos($phone, "\n}\n"));

        // Bandeau en haut, sans nouvelle image : la même image, recadrée.
        $this->assertStringContainsString('.hero-media { order: -1;', $phone);
        $this->assertStringContainsString('object-fit: cover;', $phone);
        $this->assertSame(1, substr_count(file_get_contents(resource_path('views/home.blade.php')), 'images/teacher-joe.webp'));
        // Aucune animation de plus sur téléphone.
        $this->assertStringNotContainsString('animation', $phone);
    }
}
