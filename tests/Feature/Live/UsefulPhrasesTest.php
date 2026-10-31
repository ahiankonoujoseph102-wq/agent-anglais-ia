<?php

namespace Tests\Feature\Live;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Live\WorkingTogether;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsPrograms;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

/**
 * « Phrases utiles » à l'écran : ce que l'élève peut DIRE pour demander de l'aide (évaluation, séance du jour,
 * « Mes cours »). Dépliées au premier contact, repliées ensuite. Aucun bouton qui parle à sa place.
 */
class UsefulPhrasesTest extends TestCase
{
    use BuildsPrograms, HasCourses, RefreshDatabase {
        HasCourses::learner insteadof BuildsPrograms;
    }

    private function block(string $html): string
    {
        $this->assertSame(1, preg_match('/<details id="useful-phrases".*?<\/details>/s', $html, $match), 'Phrases utiles absentes.');

        return $match[0];
    }

    public function test_the_phrases_come_from_the_configuration_in_american_english_with_their_translation(): void
    {
        $phrases = WorkingTogether::phrases();

        $this->assertGreaterThanOrEqual(4, count($phrases));
        $this->assertSame('Can you repeat, please?', $phrases[0]['en']);
        $this->assertContains('Can you explain in French, please?', array_column($phrases, 'en'));

        foreach ($phrases as $phrase) {
            $this->assertNotSame('', $phrase['fr']);
        }

        // La première phrase est celle que Teacher Joe cite dans son accueil.
        $this->assertStringContainsString('« '.$phrases[0]['en'].' »', (string) config('teacher.working_together.lesson_first'));
        $this->assertStringContainsString('written on their screen', (string) config('teacher.working_together.lesson_first'));
    }

    public function test_the_assessment_shows_them_open_with_the_reassuring_tip(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()
            ->assertSee('Vous pouvez parler français, demander de répéter ou de réexpliquer : vous avez le droit de vous tromper.')
            ->getContent();
        $block = $this->block($html);

        $this->assertMatchesRegularExpression('/class="useful-phrases"\s+open/', $block);
        $this->assertStringContainsString('Can you repeat, please?', $block);
        $this->assertStringContainsString('Pouvez-vous expliquer en français', $block);
        $this->assertStringContainsString('À dire à voix haute', $block);
        // À lire seulement : l'élève s'entraîne à les dire.
        $this->assertStringNotContainsString('<button', $block);
    }

    public function test_the_daily_lesson_opens_them_for_the_first_lessons_only(): void
    {
        $user = User::factory()->level(Level::A1)->create(['welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('intensif')->create();
        $this->buildProgram($user, Level::A1);

        $this->assertMatchesRegularExpression('/class="useful-phrases"\s+open/', $this->block($this->actingAs($user)->get('/seance')->assertOk()->getContent()));

        foreach ([600, 700] as $seconds) {
            $user->lessons()->create([
                'subscription_id' => $user->activeSubscription->id, 'date' => today()->subDays(2), 'allowed_seconds' => 900,
                'used_seconds' => $seconds, 'started_at' => now()->subDays(2), 'status' => LessonStatus::Completed,
            ]);
        }

        $block = $this->block($this->actingAs($user->fresh())->get('/seance')->assertOk()->getContent());
        $this->assertDoesNotMatchRegularExpression('/class="useful-phrases"\s+open/', $block);
        $this->assertStringContainsString('Can you repeat, please?', $block);
    }

    public function test_my_courses_shows_them_and_the_level_test_does_not(): void
    {
        Storage::fake('local');
        $user = $this->learner();
        $lesson = $this->storedLesson($user);

        $this->assertMatchesRegularExpression('/class="useful-phrases"\s+open/', $this->block($this->actingAs($user)->get(route('courses.session', $lesson))->assertOk()->getContent()));

        $this->actingAs(User::factory()->create())->get('/evaluation');
        $this->assertStringNotContainsString('id="useful-phrases"', view('live.session', [
            'mode' => 'level_test', 'title' => 'Bilan', 'intro' => 'Bilan', 'remainingSeconds' => 600, 'urls' => ['done' => '/espace'],
        ])->render());
    }
}
