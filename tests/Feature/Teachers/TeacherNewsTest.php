<?php

namespace Tests\Feature\Teachers;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Teachers\Teachers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Faire connaître Teacher Rose aux abonnés (30 octobre 2026, « je ne la trouvais pas ») : annonce « Nouveau : Teacher
 * Rose est arrivée ! » en haut de « Mon espace » pour un abonné qui a déjà fait des séances sans jamais travailler
 * avec elle, et le professeur de la séance bien visible sous « Parler avec … ».
 */
class TeacherNewsTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['teachers.list.rose.ready' => true]);
    }

    private function subscriber(bool $withLesson = true, ?string $lessonTeacher = null): User
    {
        $user = User::factory()->level(Level::A1)->create(['name' => 'Afi Mensah', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A1);

        if ($withLesson) {
            $user->lessons()->make()->forceFill([
                'subscription_id' => $user->activeSubscription->id, 'date' => today()->subDay(), 'allowed_seconds' => 900,
                'used_seconds' => 600, 'started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addMinutes(10),
                'status' => LessonStatus::Completed, 'teacher' => $lessonTeacher,
            ])->save();
        }

        return $user->fresh();
    }

    public function test_a_subscriber_who_never_tried_her_sees_the_news_at_the_top(): void
    {
        $user = $this->subscriber();

        $page = $this->actingAs($user)->get('/espace')->assertOk()
            ->assertSee('Teacher Rose est arrivée !')
            ->assertSee('Essayer avec elle')
            ->assertSee('data-dismissible="teacher-news-rose"', false);

        // En haut : avant la carte « Prochaine étape ».
        $html = $page->getContent();
        $this->assertLessThan(strpos($html, 'id="next-step-title"'), strpos($html, 'teacher-news-rose'));

        // Un seul bouton orange : celui de la prochaine étape ; l'essai est en contour.
        $this->assertStringContainsString('class="btn btn-outline" data-busy-label="Teacher Rose arrive…">Essayer avec elle', $html);

        $this->actingAs($user)->post(route('teacher.choose'), ['teacher' => 'rose'])->assertRedirect();
        $this->assertSame('rose', Teachers::forUser($user->fresh())->key);
        $this->actingAs($user->fresh())->get('/espace')->assertDontSee('est arrivée !');
    }

    public function test_no_news_after_a_session_with_her_for_a_newcomer_or_with_a_single_teacher(): void
    {
        $tried = $this->subscriber(lessonTeacher: 'rose');
        $tried->forceFill(['teacher' => null])->save();
        $this->actingAs($tried)->get('/espace')->assertOk()->assertDontSee('est arrivée !');

        // Nouvel abonné : il a choisi sur la page de bienvenue.
        $this->actingAs($this->subscriber(withLesson: false))->get('/espace')->assertOk()->assertDontSee('est arrivée !');

        config(['teachers.list.rose.ready' => false]);
        $this->actingAs($this->subscriber())->get('/espace')->assertOk()->assertDontSee('est arrivée !');
    }

    public function test_the_teacher_of_the_lesson_is_shown_under_the_start_button(): void
    {
        $this->actingAs($this->subscriber())->get('/espace')->assertOk()
            ->assertSee('Avec <strong>Teacher Joe</strong>', false)
            ->assertSee('Changer de professeur')
            ->assertSee(route('dashboard').'#professeur', false);
    }
}
