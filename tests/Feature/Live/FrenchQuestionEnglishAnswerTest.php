<?php

namespace Tests\Feature\Live;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

/**
 * La question en français, la réponse en anglais (demande du fondateur, 4 novembre 2026) : jamais « What does it mean
 * in French? » ni « How do you say « … » in English? » ; un devoir pas fait reçoit des conseils pour réviser ; la phrase
 * de l'avis est dite à la fin de CHAQUE séance tant que l'élève n'a pas laissé d'avis.
 */
class FrenchQuestionEnglishAnswerTest extends TestCase
{
    use BuildsPrograms, HasCourses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur']);
    }

    private function subscriber(): User
    {
        $user = User::factory()->level(Level::A1)->create(['name' => 'Afi', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('intensif')->create();
        $this->buildProgram($user, Level::A1);

        return $user->fresh();
    }

    private function heldLesson(User $user, ?string $homework = null): void
    {
        $lesson = $user->lessons()->create([
            'program_id' => $user->activeProgram->id,
            'program_lesson_id' => $user->activeProgram->programLessons()->orderBy('position')->first()->id,
            'date' => today()->subDay(), 'allowed_seconds' => 900, 'used_seconds' => 900,
            'started_at' => now()->subDay(), 'status' => LessonStatus::Completed,
        ]);
        $lesson->forceFill(['summary' => '"How much is it?" = C\'est combien ?', 'homework' => $homework, 'reported_at' => now()->subDay()])->save();
    }

    /** @return array<string, mixed> */
    private function connectLesson(User $user): array
    {
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton']), '*/interactions' => Http::response([], 500)]);

        return $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json();
    }

    public function test_no_instruction_asks_to_translate_english_into_french(): void
    {
        $sources = implode("\n", array_filter(Arr::flatten(config('teacher')), 'is_string'))
            .file_get_contents(app_path('Support/Curriculum/LessonContext.php'))
            .file_get_contents(resource_path('teacher/site-guide.md'));

        $this->assertStringNotContainsString('What does it mean in French', $sources);
        $this->assertDoesNotMatchRegularExpression('/How do you say «/u', $sources);

        // La règle : question en français, réponse en anglais ; à partir de B2, jamais les mots de la réponse.
        $coaching = (string) config('teacher.coaching');
        $this->assertStringContainsString('ASK IN FRENCH, ANSWER IN ENGLISH, NEVER THE REVERSE', $coaching);
        $this->assertStringContainsString('« Très bien. Maintenant, dites-le en anglais. »', $coaching);
        $this->assertStringContainsString('From B2: simple English, never with the answer\'s words.', $coaching);
        $this->assertStringContainsString('« Comment dit-on « … » en anglais ? »', (string) config('teacher.lesson'));
        // Rappel de la veille : d'abord une question ouverte en anglais, puis le test en français.
        $this->assertStringContainsString('first ONE open question in simple, slow ENGLISH: "What did you learn in our last lesson?"', (string) config('teacher.lesson'));
        $this->assertStringContainsString('then TEST them, at every level IN FRENCH', (string) config('teacher.lesson'));
        $this->assertStringContainsString('« Répondez d\'abord en français. »', $coaching);
        $this->assertStringContainsString('asked in FRENCH', (string) config('teacher.assessment_next_step'));
    }

    public function test_the_lesson_gets_the_rule_and_the_revision_tips_when_the_homework_is_not_done(): void
    {
        $user = $this->subscriber();
        $this->heldLesson($user, 'Dites-moi trois prix du marché en anglais.');

        $text = $this->connectLesson($user)['setup']['systemInstruction']['parts'][0]['text'];

        $this->assertStringContainsString('ASK IN FRENCH, ANSWER IN ENGLISH', $text);
        $this->assertStringContainsString('LONGER SENTENCES', $text);
        $this->assertStringContainsString('Not done: never cut them off, no reproach.', $text);
        $this->assertStringContainsString('HOW TO REVISE in two or three concrete tips', $text);
        $this->assertStringContainsString('« Mes séances »', $text);
        $this->assertStringContainsString('« Réviser à voix haute »', $text);
        $this->assertStringContainsString('se poser la question en français et répondre en anglais sans regarder', $text);
        $this->assertStringContainsString('then do it TOGETHER now in one minute', $text);
    }

    public function test_the_review_sentence_is_sent_at_every_lesson_until_a_review_exists(): void
    {
        $user = $this->subscriber();
        $minutes = (int) config('platform.reviews.bonus_minutes');

        foreach (range(1, 3) as $number) {
            $conclude = $this->connectLesson($user)['session_end']['conclude'];
            $this->assertStringContainsString('touchez « Donner mon avis »', $conclude, "séance {$number}");
            $this->assertStringContainsString($minutes.' minutes offertes, quelle que soit votre note', $conclude);
            $this->assertStringContainsString('never ask for a good rating', $conclude);

            $user->lessons()->latest('id')->first()->update(['status' => LessonStatus::Completed, 'used_seconds' => 150, 'ended_at' => now()]);
            $this->heldLesson($user);
        }

        $this->actingAs($user)->put(route('reviews.save'), ['rating' => 3, 'body' => 'Un avis honnête : les séances m\'aident beaucoup.', 'consent' => '1']);
        $this->assertStringNotContainsString('Donner mon avis', $this->connectLesson($user->fresh())['session_end']['conclude']);
    }

    public function test_the_review_sentence_ends_my_courses_sessions_too(): void
    {
        $this->fakeGemini();
        $user = $this->learner();
        $lesson = $this->storedLesson($user);

        $conclude = $this->actingAs($user)->postJson(route('courses.connect', $lesson))->assertOk()->json('session_end.conclude');

        $this->assertStringContainsString('touchez « Donner mon avis »', $conclude);
    }
}
