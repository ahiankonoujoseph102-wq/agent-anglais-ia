<?php

namespace Tests\Feature\Program;

use App\Enums\Level;
use App\Models\LessonBoard;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Curriculum\LessonBoards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Tableau de la séance du jour : préparé une fois par le modèle texte (Gemini, puis OpenAI en secours), partagé,
 * jamais à la connexion, et tableau de secours tiré du programme. Faux services uniquement.
 */
class LessonBoardTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    private const OPENAI_URL = 'api.openai.com/v1/responses';

    private mixed $gemini = null;

    private mixed $openai = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.text.providers' => ['gemini', 'openai'], 'services.gemini.key' => null, 'services.openai.key' => null]);

        Http::fake([
            '*/interactions' => fn () => $this->reply($this->gemini, 'gemini'),
            self::OPENAI_URL => fn () => $this->reply($this->openai, 'openai'),
            '*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton']),
        ]);
    }

    private function reply(mixed $answer, string $provider): mixed
    {
        if (is_int($answer)) {
            return Http::response(['error' => ['code' => $answer, 'message' => 'Erreur interne']], $answer);
        }

        $text = is_array($answer) ? json_encode($answer) : (string) $answer;

        return $provider === 'gemini'
            ? Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $text]]]], 'usage' => ['total_input_tokens' => 900, 'total_output_tokens' => 300]])
            : Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => $text]]]], 'usage' => ['input_tokens' => 900, 'output_tokens' => 300]]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function board(string $title = 'My family'): array
    {
        return [
            'title' => $title,
            'phrases' => [
                ['en' => 'This is my sister.', 'fr' => 'Voici ma sœur.'],
                ['en' => 'Her name is Ama.', 'fr' => 'Elle s\'appelle Ama.'],
                ['en' => 'I have two brothers.', 'fr' => 'J\'ai deux frères.'],
                ['en' => 'Do you have any children?', 'fr' => 'Avez-vous des enfants ?'],
            ],
            'vocabulary' => [
                ['en' => 'mother / mom', 'fr' => 'mère / maman'], ['en' => 'father / dad', 'fr' => 'père / papa'],
                ['en' => 'brother', 'fr' => 'frère'], ['en' => 'sister', 'fr' => 'sœur'], ['en' => 'children', 'fr' => 'enfants'],
                ['en' => 'married', 'fr' => 'marié(e)'],
            ],
            'verbs' => [['base' => 'have', 'forms' => 'I have / she has', 'fr' => 'avoir']],
            'rule_en' => 'Use "his" for a man and "her" for a woman: Kofi and his mother.',
            'rule_fr' => '« his » pour un homme, « her » pour une femme : Kofi and his mother.',
        ];
    }

    private function learner(string $phone, string $goal = 'work'): User
    {
        $user = User::factory()->level(Level::A1)->create(['phone' => $phone, 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->create();
        $this->buildProgram($user, Level::A1, ['goal' => $goal]);

        return $user->fresh();
    }

    private function current(User $user): array
    {
        $program = $user->activeProgram()->firstOrFail();
        $lesson = $program->currentLesson()->firstOrFail();
        $lesson->setRelation('program', $program);

        return [$lesson, $program];
    }

    public function test_the_board_is_prepared_once_by_gemini_and_shared_by_the_same_lesson_and_goal(): void
    {
        config(['services.gemini.key' => 'cle-gemini']);
        $this->gemini = self::board();
        [$lesson, $program] = $this->current($this->learner('+22890111111'));

        $board = LessonBoards::prepare($lesson, $program);

        $this->assertSame('gemini', $board->provider);
        $this->assertSame(LessonBoards::key($lesson, $program), $board->key);
        $this->assertSame('v1:A1-01-L1-S'.$lesson->situation_index.'-work', $board->key);
        $content = LessonBoards::for($lesson, $program);
        $this->assertSame('My family', $content['title']);
        $this->assertSame(['en' => 'This is my sister.', 'fr' => 'Voici ma sœur.'], $content['phrases'][0]);
        $this->assertSame('gemini', $content['source']);
        $this->assertSame(['en' => 'Use "his" for a man and "her" for a woman: Kofi and his mother.', 'fr' => '« his » pour un homme, « her » pour une femme : Kofi and his mother.'], $content['rule']);

        // La demande décrit l'unité, la situation et l'objectif, jamais l'apprenant (aucun nom, aucun numéro).
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'interactions')
            && str_contains(json_encode($request->data()), 'SITUATION')
            && str_contains(json_encode($request->data()), 'American English')
            && ! str_contains(json_encode($request->data()), '+22890111111'));

        // Un autre apprenant, même leçon et même objectif : le même tableau, aucune nouvelle demande.
        [$other, $otherProgram] = $this->current($this->learner('+22890222222'));
        $this->assertSame(LessonBoards::key($lesson, $program), LessonBoards::key($other, $otherProgram));
        $this->assertSame($board->id, LessonBoards::prepare($other, $otherProgram)->id);
        $this->assertSame(1, LessonBoard::count());
        Http::assertSentCount(1);
    }

    public function test_openai_prepares_the_board_when_gemini_fails(): void
    {
        config(['services.gemini.key' => 'cle-gemini', 'services.openai.key' => 'cle-openai']);
        $this->gemini = 500;
        $this->openai = self::board('Introducing my family');
        [$lesson, $program] = $this->current($this->learner('+22890111111'));

        $this->assertSame('openai', LessonBoards::prepare($lesson, $program)->provider);
        $this->assertSame('Introducing my family', LessonBoards::for($lesson, $program)['title']);
    }

    public function test_an_unusable_answer_is_never_kept_and_the_programme_board_is_used(): void
    {
        config(['services.gemini.key' => 'cle-gemini']);
        $this->gemini = ['title' => 'Too short', 'phrases' => [['en' => 'Hi.', 'fr' => 'Salut.']], 'vocabulary' => [], 'verbs' => [], 'rule_en' => '', 'rule_fr' => ''];
        [$lesson, $program] = $this->current($this->learner('+22890111111'));

        $this->assertNull(LessonBoards::prepare($lesson, $program));
        $this->assertSame(0, LessonBoard::count());

        // Aucune nouvelle demande avant un moment : le secours suffit.
        $this->assertNull(LessonBoards::prepare($lesson, $program));
        Http::assertSentCount(1);

        $fallback = LessonBoards::for($lesson, $program);
        $this->assertSame('programme', $fallback['source']);
        $unit = $lesson->unit();
        $this->assertSame($unit->title, $fallback['title']);
        $this->assertCount(8, $fallback['vocabulary']);
        $this->assertSame(array_key_first($unit->vocabulary), $fallback['vocabulary'][0]['en']);
        $this->assertGreaterThanOrEqual(1, count($fallback['phrases']));
        $this->assertNull($fallback['phrases'][0]['fr'], 'aucune traduction inventée');
        // Seules des phrases anglaises du programme, jamais du français entre guillemets.
        foreach ($fallback['phrases'] as $phrase) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9 ,.!?\'’()\/-]+$/', $phrase['en']);
        }
        $this->assertSame($unit->grammar[0], $fallback['rule']['fr']);
    }

    public function test_without_any_provider_the_lesson_page_uses_the_programme_board_without_any_request(): void
    {
        $user = $this->learner('+22890111111');

        $this->actingAs($user)->get('/seance')->assertOk();

        Http::assertNothingSent();
        $this->assertSame(0, LessonBoard::count());
    }

    public function test_the_lesson_page_prepares_a_missing_board_but_connecting_never_does(): void
    {
        $user = $this->learner('+22890111111');
        config(['services.gemini.key' => 'cle-gemini']);
        $this->gemini = self::board();

        // Connexion à la séance : seulement le jeton, jamais le modèle texte.
        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'interactions'));
        $this->assertSame(0, LessonBoard::count());

        // « Ma séance du jour » : le tableau manquant est préparé avec un délai court.
        config(['program.board_prepare_timeout' => 6]);
        $this->actingAs($user)->get('/seance')->assertOk();
        $this->assertSame(1, LessonBoard::count());
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'interactions'));
    }

    public function test_the_next_board_is_prepared_after_the_response_once_a_lesson_is_over(): void
    {
        $user = $this->learner('+22890111111');
        $program = $user->activeProgram()->firstOrFail();
        config(['services.gemini.key' => 'cle-gemini']);
        $this->gemini = self::board();

        $this->actingAs($user)->postJson('/seance/connexion')->assertOk();
        $this->actingAs($user)->postJson('/seance/bilan', [
            'lesson_status' => 'completed',
            'summary' => 'Présenter sa famille.',
            'homework' => 'Présentez trois personnes de votre famille.',
            'scores' => ['fluency' => 3, 'pronunciation' => 3, 'grammar' => 3, 'vocabulary' => 3, 'comprehension' => 3],
        ])->assertOk();

        $next = $program->fresh()->currentLesson()->firstOrFail();
        $next->setRelation('program', $program);
        $this->assertTrue(LessonBoards::ready($next, $program));
        $this->assertSame(2, $next->lesson_number);
    }
}
