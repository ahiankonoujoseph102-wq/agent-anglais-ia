<?php

namespace Tests\Feature\Live;

use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Gemini\TeacherSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

/**
 * Fin de la séance du jour et de « Mes cours » : vers une minute de la fin, Teacher Joe l'annonce ; puis UNE
 * conclusion (« On va s'arrêter ici pour aujourd'hui », résumé, une ou deux choses à retenir, la suite, « À demain ! »),
 * puis la séance se ferme. Indications envoyées d'après le temps restant (public/js/conversation-end.js).
 */
class SessionEndTest extends TestCase
{
    use BuildsPrograms, HasCourses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton']), '*/interactions' => Http::response([], 500)]);
        $this->freezeSecond();
    }

    private function subscriber(): User
    {
        $user = User::factory()->level(Level::A2)->create(['name' => 'Afi', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A2);

        return $user->fresh();
    }

    public function test_the_daily_lesson_gets_the_end_of_session_settings_and_instructions(): void
    {
        $data = $this->actingAs($this->subscriber())->postJson('/seance/connexion')->assertOk()->json();
        $end = $data['session_end'];

        $this->assertSame(900, $end['total']);
        $this->assertSame(60, $end['announce_seconds']);
        $this->assertSame(40, $end['conclude_seconds']);
        $this->assertSame('À demain !', $end['closing_phrase']);
        $this->assertStringContainsString('« Il nous reste environ une minute. »', $end['announce']);
        $this->assertStringContainsString('Call end_lesson now', $end['conclude']);
        $this->assertStringContainsString('« À demain ! »', $end['conclude']);

        $text = $data['setup']['systemInstruction']['parts'][0]['text'];
        $this->assertStringContainsString('END OF THE SESSION', $text);
        $this->assertStringContainsString('« On va s\'arrêter ici pour aujourd\'hui. »', $text);
        $this->assertStringContainsString('their REAL mistakes or real points of this session, never invented', $text);
        $this->assertStringContainsString('what you will do next time, then exactly « À demain ! »', $text);
        // Plus d'au revoir au préavis de 2 minutes : la conclusion vient seulement à la fin.
        $this->assertStringContainsString('Do not say goodbye yet.', $data['cues']['warning']);
        $this->assertStringNotContainsString('then say goodbye', $data['cues']['warning']);
        $this->assertStringNotContainsString('say goodbye warmly in one or two sentences and stop talking', $text);
    }

    public function test_a_course_session_ends_the_same_way_without_a_silent_goodbye_before(): void
    {
        $this->fakeGemini();
        $user = $this->learner();
        $lesson = $this->storedLesson($user);

        $data = $this->actingAs($user)->postJson(route('courses.connect', $lesson))->assertOk()->json();

        $this->assertSame(600, $data['session_end']['total']);
        $this->assertStringContainsString('« À demain ! »', $data['session_end']['conclude']);
        $this->assertStringNotContainsString('end_lesson', $data['session_end']['conclude']);
        // Plus d'indication « dites au revoir et taisez-vous » 90 secondes avant la fin (cause du professeur muet).
        $this->assertArrayNotHasKey('warning', $data['cues']);
        $this->assertNull(config('teacher.cues.course_warning'));
    }

    public function test_the_instructions_stay_short_and_only_for_these_two_conversations(): void
    {
        $block = (string) config('teacher.session_end');
        $this->assertLessThan(800, mb_strlen($block));

        $user = $this->subscriber();
        $this->assertStringContainsString('END OF THE SESSION', TeacherSetup::instructions('course', $user, 10));
        $this->assertStringNotContainsString('END OF THE SESSION', TeacherSetup::instructions('assessment', $user, 5));
        $this->assertStringNotContainsString('END OF THE SESSION', TeacherSetup::instructions('level_test', $user, 10));
    }

    public function test_the_end_screen_says_it_clearly_and_leads_back_to_my_space(): void
    {
        $html = $this->actingAs($this->subscriber())->get('/seance')->assertOk()->getContent();

        $this->assertStringContainsString('Séance terminée. À demain !', $html);
        $this->assertStringContainsString('Revenir à mon espace', $html);
    }

    public function test_the_script_closes_cleanly_from_the_remaining_time(): void
    {
        $script = file_get_contents(public_path('js/live-session.js'));

        // Indications de fin d'après le temps restant ; micro fermé dès la phrase de fin ; minuteur à zéro sans couper la conclusion.
        $this->assertStringContainsString('state.closing.next(seconds, busy, clock())', $script);
        $this->assertStringContainsString('state.closing?.teacherSaid(text, clock())', $script);
        $this->assertStringContainsString('return state.end.micOpen && (state.closing?.micOpen ?? true);', $script);
        $this->assertStringContainsString('state.closing.timeUp(clock(), busy)', $script);
    }
}
