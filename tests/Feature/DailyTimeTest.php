<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Enums\ProgramLessonStatus;
use App\Enums\ProgramStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Support\DailyTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsPrograms;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

/**
 * Un seul temps par jour et par formule, partagé librement entre la séance orale quotidienne et
 * « Mes cours » (config/platform.php : plans.*.daily_minutes, daily_time, courses.max_session_minutes).
 */
class DailyTimeTest extends TestCase
{
    use BuildsPrograms, HasCourses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur', 'services.gemini.token_minutes' => 30]);
        $this->fakeGemini();
        // En milieu de journée, heure de Lomé : le jour ne change pas pendant le test.
        $this->travelTo(Carbon::parse('2026-10-18 10:00:00', 'Africa/Lome'));
    }

    private function subscriber(string $plan = 'essentiel'): User
    {
        $user = User::factory()->level(Level::A2)->create(['name' => 'Ama']);
        Subscription::factory()->for($user)->forPlan($plan)->create();
        $this->buildProgram($user);

        return $user;
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function plans(): array
    {
        return ['Semaine' => ['semaine', 900], 'Essentiel' => ['essentiel', 900], 'Intensif' => ['intensif', 1800]];
    }

    #[DataProvider('plans')]
    public function test_each_plan_gives_one_daily_time(string $plan, int $seconds): void
    {
        $this->assertSame($seconds, $this->subscriber($plan)->remainingSecondsToday());
    }

    public function test_the_oral_lesson_and_my_courses_share_the_same_daily_time(): void
    {
        $user = $this->subscriber('intensif');
        $lesson = $this->storedLesson($user);

        // « Mes cours » d'abord : 10 minutes au plus, prises sur les 30 minutes du jour.
        $this->actingAs($user)->postJson(route('courses.connect', $lesson))->assertOk()->assertJsonPath('remaining_seconds', 600);
        $this->travel(7)->minutes();
        $this->actingAs($user)->postJson(route('courses.finish'))->assertOk();
        $this->assertSame(1380, $user->remainingSecondsToday());

        // Puis la séance orale reçoit ce qui reste : 23 minutes.
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->assertJsonPath('remaining_seconds', 1380);
        $this->travel(20)->minutes();
        $this->actingAs($user)->postJson('/seance/fin')->assertOk();
        $this->assertSame(180, $user->remainingSecondsToday());

        // Il reste 3 minutes : « Mes cours » est raccourci à 3 minutes, et le serveur coupe là.
        $this->actingAs($user)->postJson(route('courses.connect', $lesson))->assertOk()->assertJsonPath('remaining_seconds', 180);
        $this->travel(4)->minutes();
        $this->actingAs($user)->postJson(route('courses.signal'))->assertOk()->assertJsonPath('active', false);
        $this->assertSame(0, $user->remainingSecondsToday());
        $this->assertSame(1800, $user->secondsUsedToday(), 'Jamais plus que le temps de la formule.');
    }

    public function test_an_open_session_keeps_its_time_while_another_one_starts(): void
    {
        $user = $this->subscriber();
        $lesson = $this->storedLesson($user);

        // « Mes cours » ouvert (10 minutes accordées) : la séance orale ouverte en même temps n'a que 5 minutes.
        $this->actingAs($user)->postJson(route('courses.connect', $lesson))->assertOk();
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->assertJsonPath('remaining_seconds', 300);

        // Affichage : le temps réellement écoulé des deux séances ouvertes (une minute chacune), sans réservation.
        $this->travel(1)->minutes();
        $this->assertSame(780, $user->remainingSecondsToday(false));
        $this->assertSame(0, $user->remainingSecondsToday());
    }

    public function test_under_two_minutes_no_new_session_opens_with_a_clear_message(): void
    {
        $user = $this->subscriber();
        $lesson = $this->storedLesson($user);
        $user->lessons()->create(['date' => today(), 'started_at' => now()->subHour(), 'allowed_seconds' => 900, 'used_seconds' => 781, 'status' => LessonStatus::Completed]);

        // 119 secondes : refusé partout.
        $this->actingAs($user)->postJson('/seance/connexion')->assertForbidden()->assertJsonPath('message', DailyTime::EXHAUSTED);
        $this->actingAs($user)->postJson(route('courses.connect', $lesson))->assertForbidden()->assertJsonPath('message', DailyTime::EXHAUSTED);
        $this->actingAs($user)->get('/seance')->assertRedirect('/espace')->assertSessionHas('status', DailyTime::EXHAUSTED);
        $this->actingAs($user)->get('/espace')
            ->assertSeeText('Votre temps du jour est épuisé')
            ->assertSeeText('repart à zéro à minuit. À demain !')
            ->assertDontSee('/seance"', false);
        Http::assertNothingSent();

        // Exactement 2 minutes : la séance s'ouvre.
        $user->lessons()->update(['used_seconds' => 780]);
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->assertJsonPath('remaining_seconds', 120);
        $this->assertSame(120, (int) config('platform.daily_time.min_start_seconds'));
        $this->assertStringNotContainsString(' min ', DailyTime::EXHAUSTED);
    }

    public function test_the_daily_time_starts_again_at_midnight_lome_time(): void
    {
        $this->assertSame('Africa/Lome', config('app.timezone'));
        $this->travelTo(Carbon::parse('2026-10-18 23:50:00', 'Africa/Lome'));
        $user = $this->subscriber();
        $user->lessons()->create(['date' => today(), 'started_at' => now(), 'allowed_seconds' => 900, 'used_seconds' => 900, 'status' => LessonStatus::Completed]);

        $this->travelTo(Carbon::parse('2026-10-18 23:59:59', 'Africa/Lome'));
        $this->assertSame(0, $user->remainingSecondsToday());

        $this->travelTo(Carbon::parse('2026-10-19 00:00:01', 'Africa/Lome'));
        $this->assertSame(900, $user->remainingSecondsToday());
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->assertJsonPath('remaining_seconds', 900);
    }

    public function test_the_assessment_and_the_level_test_do_not_count(): void
    {
        $user = $this->subscriber();
        $user->assessment()->create(['status' => AssessmentStatus::Completed, 'started_at' => now()->subHour(), 'allowed_seconds' => 420, 'used_seconds' => 420, 'level' => Level::A2]);

        $program = $user->activeProgram()->first();
        $program->programLessons()->update(['status' => ProgramLessonStatus::Completed, 'completed_at' => now()]);
        $program->update(['status' => ProgramStatus::LevelTestDue]);
        $this->actingAs($user)->postJson('/bilan-niveau/connexion')->assertOk();
        $this->travel(5)->minutes();
        $this->actingAs($user)->postJson('/bilan-niveau/fin')->assertOk();

        $this->assertSame(300, $user->levelTests()->sole()->used_seconds);
        $this->assertSame(900, $user->remainingSecondsToday(), 'Ni l\'évaluation ni le bilan de niveau ne prennent sur le temps du jour.');
    }

    public function test_my_space_shows_the_time_left_today(): void
    {
        $user = $this->subscriber();
        $user->lessons()->create(['date' => today(), 'started_at' => now()->subHour(), 'allowed_seconds' => 900, 'used_seconds' => 360, 'status' => LessonStatus::Completed]);

        $this->actingAs($user)->get('/espace')
            ->assertOk()
            ->assertSeeText('Il vous reste 9 minutes aujourd\'hui. Ce temps est partagé entre la séance orale et « Mes cours ».')
            ->assertSeeText('sur 15 minutes, séance orale et « Mes cours »');
        $this->actingAs($user)->get('/mes-cours')->assertSeeText('Il vous reste 9 minutes aujourd\'hui.');
    }
}
