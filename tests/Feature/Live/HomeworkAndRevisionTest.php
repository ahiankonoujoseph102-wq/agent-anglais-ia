<?php

namespace Tests\Feature\Live;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\Lesson;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Curriculum\LessonBoards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Devoir et révision (demande du fondateur, 2 novembre 2026) : un devoir à DIRE à la séance suivante, une conclusion
 * qui dit le devoir, quoi réviser et où le retrouver, une vraie vérification du devoir le lendemain, le tableau de
 * chaque séance gardé dans « Mes séances », et « Enregistrer ma séance » rappelé au bon moment.
 */
class HomeworkAndRevisionTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton']), '*/interactions' => Http::response([], 500)]);
    }

    private function learner(): User
    {
        $user = User::factory()->level(Level::A1)->create(['name' => 'Afi', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('intensif')->create();
        $this->buildProgram($user, Level::A1);

        return $user->fresh();
    }

    private function pastLesson(User $user, ?string $homework = 'Préparez trois phrases sur votre journée d\'hier : vous me les direz.'): Lesson
    {
        $programLesson = $user->activeProgram->programLessons()->orderBy('position')->first();

        $lesson = $user->lessons()->create([
            'program_id' => $user->activeProgram->id, 'program_lesson_id' => $programLesson->id,
            'date' => today()->subDay(), 'allowed_seconds' => 900, 'used_seconds' => 900,
            'started_at' => now()->subDay(), 'status' => LessonStatus::Completed,
        ]);
        $lesson->forceFill([
            'summary' => '"I went to the port." = Je suis allé au port. Vous savez raconter votre journée.',
            'homework' => $homework, 'reported_at' => now()->subDay(),
        ])->save();

        return $lesson;
    }

    public function test_the_homework_is_said_at_the_next_lesson_and_really_checked(): void
    {
        $user = $this->learner();
        $this->pastLesson($user);

        $data = $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json();
        $text = $data['setup']['systemInstruction']['parts'][0]['text'];

        // Devoir donné : à DIRE à la séance suivante, en français.
        $this->assertStringContainsString('ONE small homework to SAY at the start of the next lesson', $text);
        // Le lendemain : vérification réelle, fait ou pas fait.
        $this->assertStringContainsString('then the HOMEWORK CHECK (see PREVIOUS LESSON)', $text);
        $this->assertStringContainsString('HOMEWORK CHECK (two minutes at most, one question per turn): ask them to SAY their homework now.', $text);
        $this->assertStringContainsString('Not done: no reproach', $text);
        $this->assertStringContainsString('then do it TOGETHER now in one minute', $text);

        // Conclusion : devoir, quoi réviser, où le retrouver (dit, et écrit sur l'écran de fin).
        $conclude = $data['session_end']['conclude'];
        $this->assertStringContainsString('the HOMEWORK in clear words', $conclude);
        $this->assertStringContainsString('what to REVISE', $conclude);
        $this->assertStringContainsString(config('platform.lessons.find_note'), $conclude);
        $this->assertSame(config('platform.lessons.find_note'), $data['session_end']['find_note']);
        $this->assertStringContainsString('télécharger votre vidéo', $data['session_end']['recording']);
        $this->assertStringContainsString('state.config.session_end?.recording', file_get_contents(public_path('js/live-session.js')));
    }

    public function test_no_homework_check_without_homework(): void
    {
        $user = $this->learner();
        $this->pastLesson($user, null);

        $text = $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json('setup.systemInstruction.parts.0.text');

        $this->assertStringNotContainsString('HOMEWORK CHECK (two minutes', $text);
    }

    public function test_the_recording_tip_is_given_only_in_the_first_lessons(): void
    {
        $user = $this->learner();
        $start = $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json('cues.start');

        $this->assertStringContainsString('« Enregistrer ma séance »', (string) $start);

        // Deux séances tenues : plus de rappel (le bouton reste à l'écran).
        $other = $this->learner();
        $this->pastLesson($other);
        $this->pastLesson($other);
        $later = $this->actingAs($other)->postJson('/seance/connexion')->assertOk()->json('cues.start');

        $this->assertStringNotContainsString('« Enregistrer ma séance »', (string) $later);
    }

    public function test_my_sessions_keep_the_board_of_each_lesson_and_my_space_links_to_it(): void
    {
        $user = $this->learner();
        $lesson = $this->pastLesson($user);
        $board = LessonBoards::for($lesson->programLesson, $user->activeProgram);

        $this->actingAs($user)->get(route('lessons.history', ['reviser' => $lesson->id]))
            ->assertOk()
            ->assertSee('Le tableau de cette séance')
            ->assertSee('id="seance-'.$lesson->id.'"', false)
            ->assertSee($board['phrases'][0]['en'] ?? $board['title'])
            ->assertSee('À dire à');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Réviser le tableau de ma dernière séance')
            ->assertSee(route('lessons.history', ['reviser' => $lesson->id]).'#seance-'.$lesson->id, false);
    }
}
