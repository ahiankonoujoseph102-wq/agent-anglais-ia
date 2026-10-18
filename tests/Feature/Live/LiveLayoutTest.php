<?php

namespace Tests\Feature\Live;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Écran de conversation : le professeur d'abord, puis « Commencer » tout de suite
 * en dessous (avant le minuteur et les conseils), pour qu'il soit visible sans défiler.
 */
class LiveLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_button_comes_right_after_the_teacher(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();

        $teacher = strpos($html, 'class="live-stage"');
        $start = strpos($html, 'id="start"');
        $this->assertNotFalse($teacher);
        $this->assertNotFalse($start);
        $this->assertGreaterThan($teacher, $start);
        $this->assertLessThan(strpos($html, 'class="live-bar"'), $start, '« Commencer » avant le minuteur');
        $this->assertLessThan(strpos($html, 'class="intro-tips"'), $start, '« Commencer » avant les conseils');
    }

    public function test_landscape_and_computer_screens_use_two_columns(): void
    {
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertStringContainsString('@media (min-width: 900px), (orientation: landscape) and (max-height: 560px)', $css);
        $this->assertStringContainsString('.live-stage { grid-column: 1;', $css);
    }

    public function test_the_screen_can_say_that_no_answer_comes_and_offers_to_nudge_teacher_joe(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<div id="wait-note"[^>]*role="status"[^>]*hidden>/', $html);
        $this->assertStringContainsString('id="nudge"', $html);
        $this->assertStringContainsString('Relancer Teacher Joe', $html);
        $this->assertStringContainsString('data-turn-watch="', $html);
        // Délais du navigateur (config/platform.php → live) : fin de tour, « réfléchit… », solution.
        $this->assertStringContainsString('"end_of_turn_seconds":3,"no_answer_seconds":8,"stuck_seconds":20,"voice_stuck_seconds":4', $html);

        $script = file_get_contents(public_path('js/live-session.js'));
        $this->assertStringContainsString('réfléchit, un instant…', $script);
        $this->assertStringContainsString('ne répond pas. Touchez « Relancer', $script);
    }
}
