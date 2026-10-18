<?php

namespace Tests\Feature\Live;

use App\Enums\AssessmentStatus;
use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Enums\LevelTestStatus;
use App\Models\Assessment;
use App\Models\ConversationLine;
use App\Models\CourseSession;
use App\Models\Lesson;
use App\Models\LevelTest;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Live\OpenAiRealtime;
use App\Support\Live\Transcripts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Texte des conversations (jamais l'audio) : réception par petits lots, contrôle du propriétaire et de la
 * taille, effacement après 90 jours.
 */
class TranscriptTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton'])]);
        $this->travelTo(Carbon::parse('2026-10-18 10:00:00', 'Africa/Lome'));
    }

    private function subscriber(): User
    {
        $user = User::factory()->level(Level::A2)->create(['name' => 'Ama']);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function line(int $seq, string $speaker, string $text, array $overrides = []): array
    {
        return $overrides + ['seq' => $seq, 'speaker' => $speaker, 'text' => $text, 'at' => now()->getTimestampMs()];
    }

    public function test_the_lines_of_a_lesson_are_recorded_in_order_with_their_time(): void
    {
        $user = $this->subscriber();
        $url = $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->json('transcript_url');
        $lesson = Lesson::sole();
        $this->assertSame(route('live.transcript', ['type' => 'lesson', 'id' => $lesson->id]), $url);

        $this->travel(20)->seconds();
        $this->actingAs($user)->postJson($url, ['lines' => [
            self::line(1, 'teacher', 'Hello Ama! Today we practise greetings.', ['at' => now()->subSeconds(15)->getTimestampMs()]),
            self::line(2, 'learner', 'Hello teacher.'),
        ]])->assertOk()->assertJson(['kept' => 2]);

        // La réplique du professeur a grandi : même numéro, le texte le plus long est gardé ; un ancien lot rejoué ne l'abîme pas.
        $this->actingAs($user)->postJson($url, ['lines' => [
            self::line(2, 'learner', 'Hello teacher. I am fine.'),
            self::line(3, 'teacher', 'Great answer!'),
        ]])->assertOk();
        $this->actingAs($user)->postJson($url, ['lines' => [self::line(2, 'learner', 'Hello')]])->assertOk();

        $lines = Transcripts::lines($lesson);
        $this->assertSame(['teacher', 'learner', 'teacher'], $lines->pluck('speaker')->all());
        $this->assertSame('Hello teacher. I am fine.', $lines[1]->text);
        $this->assertTrue($lines[0]->spoken_at->equalTo(now()->subSeconds(15)));
        $this->assertSame($user->id, $lines[0]->user_id);
    }

    public function test_the_time_given_by_the_browser_stays_between_the_start_and_now(): void
    {
        $user = $this->subscriber();
        $url = $this->actingAs($user)->postJson('/seance/connexion')->json('transcript_url');

        $this->actingAs($user)->postJson($url, ['lines' => [
            self::line(1, 'teacher', 'Avant le début', ['at' => now()->subDay()->getTimestampMs()]),
            self::line(2, 'learner', 'Dans le futur', ['at' => now()->addDay()->getTimestampMs()]),
        ]])->assertOk();

        $lines = ConversationLine::orderBy('position')->get();
        $this->assertTrue($lines[0]->spoken_at->equalTo(Lesson::sole()->started_at));
        $this->assertTrue($lines[1]->spoken_at->lessThanOrEqualTo(now()));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function types(): array
    {
        return ['évaluation' => ['assessment'], 'séance du jour' => ['lesson'], '« Mes cours »' => ['course'], 'bilan de niveau' => ['level_test']];
    }

    #[DataProvider('types')]
    public function test_every_kind_of_session_records_its_conversation(string $type): void
    {
        $user = $this->subscriber();
        $conversation = self::conversation($type, $user);

        $this->actingAs($user)->postJson(route('live.transcript', ['type' => $type, 'id' => $conversation->id]), ['lines' => [self::line(1, 'learner', 'Good morning')]])
            ->assertOk();

        $this->assertSame('Good morning', Transcripts::lines($conversation)->sole()->text);
    }

    public function test_a_session_of_another_learner_is_refused(): void
    {
        $owner = $this->subscriber();
        $other = User::factory()->create();
        $conversation = self::conversation('lesson', $owner);

        $this->actingAs($other)->postJson(route('live.transcript', ['type' => 'lesson', 'id' => $conversation->id]), ['lines' => [self::line(1, 'learner', 'Intrus')]])
            ->assertNotFound();
        $this->post(route('live.transcript', ['type' => 'lesson', 'id' => $conversation->id]), ['lines' => [self::line(1, 'learner', 'Invité')]]);
        $this->assertSame(0, ConversationLine::count());
    }

    public function test_an_old_session_is_refused(): void
    {
        $user = $this->subscriber();
        $conversation = self::conversation('lesson', $user, now()->subHours(7));

        $this->actingAs($user)->postJson(route('live.transcript', ['type' => 'lesson', 'id' => $conversation->id]), ['lines' => [self::line(1, 'learner', 'Trop tard')]])
            ->assertStatus(409);
        $this->assertSame(0, ConversationLine::count());
    }

    public function test_the_size_of_a_batch_and_of_a_line_is_limited(): void
    {
        $user = $this->subscriber();
        $url = route('live.transcript', ['type' => 'lesson', 'id' => self::conversation('lesson', $user)->id]);

        $tooMany = array_map(fn (int $i) => self::line($i, 'teacher', 'Ligne '.$i), range(1, 51));
        $this->actingAs($user)->postJson($url, ['lines' => $tooMany])->assertUnprocessable();
        $this->actingAs($user)->postJson($url, ['lines' => [self::line(1, 'teacher', str_repeat('a', 4001))]])->assertUnprocessable();
        $this->actingAs($user)->postJson($url, ['lines' => [self::line(1, 'robot', 'Qui parle ?')]])->assertUnprocessable();
        $this->assertSame(0, ConversationLine::count());

        // Plafond par séance : au-delà, rien de nouveau n'est gardé (sans erreur pour le navigateur).
        config(['platform.transcripts.max_lines_per_conversation' => 3]);
        $this->actingAs($user)->postJson($url, ['lines' => array_map(fn (int $i) => self::line($i, 'teacher', 'Ligne '.$i), range(1, 3))])->assertOk();
        $this->actingAs($user)->postJson($url, ['lines' => [self::line(3, 'teacher', 'Ligne 3, complétée')]])->assertOk();
        $this->assertSame(3, ConversationLine::count());
        $this->assertSame('Ligne 3, complétée', ConversationLine::where('position', 3)->value('text'));
    }

    public function test_the_conversations_are_erased_after_ninety_days(): void
    {
        $user = $this->subscriber();
        $old = self::conversation('lesson', $user, now()->subDays(91));
        $recent = self::conversation('course', $user);
        $assessment = $user->assessment()->create(['status' => AssessmentStatus::Completed, 'started_at' => now()->subDays(91), 'allowed_seconds' => 300, 'used_seconds' => 300, 'level' => Level::A2]);
        $assessment->forceFill(['transcript' => 'Learner: hello'])->save();

        $this->travelTo(now()->subDays(91));
        Transcripts::record($old, [self::line(1, 'teacher', 'Ancienne réplique')]);
        $this->travelBack();
        $this->travelTo(Carbon::parse('2026-10-18 10:00:00', 'Africa/Lome'));
        Transcripts::record($recent, [self::line(1, 'teacher', 'Réplique récente')]);

        $this->artisan('transcripts:purge')->assertSuccessful();

        $this->assertSame(['Réplique récente'], ConversationLine::pluck('text')->all());
        $this->assertNull($assessment->fresh()->transcript, 'Les sous-titres de l\'évaluation sont effacés aussi.');
        $this->assertSame(90, (int) config('platform.transcripts.keep_days'));
    }

    public function test_the_learner_transcription_at_openai_can_be_turned_off(): void
    {
        config(['services.openai.realtime_model' => 'gpt-realtime-2.1-mini']);
        $setup = ['systemInstruction' => ['parts' => [['text' => 'Consignes']]]];

        $this->assertSame('gpt-4o-mini-transcribe', OpenAiRealtime::session($setup)['audio']['input']['transcription']['model']);

        config(['services.openai.realtime_input_transcription' => false]);
        $this->assertArrayNotHasKey('transcription', OpenAiRealtime::session($setup)['audio']['input']);
    }

    private static function conversation(string $type, User $user, ?Carbon $startedAt = null): Model
    {
        $startedAt ??= now()->subMinute();

        return match ($type) {
            'assessment' => Assessment::query()->forceCreate(['user_id' => $user->id, 'status' => AssessmentStatus::InProgress, 'started_at' => $startedAt, 'allowed_seconds' => 300, 'used_seconds' => 0]),
            'lesson' => Lesson::query()->forceCreate(['user_id' => $user->id, 'date' => $startedAt->toDateString(), 'started_at' => $startedAt, 'allowed_seconds' => 900, 'used_seconds' => 0, 'status' => LessonStatus::InProgress]),
            'course' => CourseSession::query()->forceCreate(['user_id' => $user->id, 'date' => $startedAt->toDateString(), 'started_at' => $startedAt, 'allowed_seconds' => 600, 'used_seconds' => 0, 'status' => LessonStatus::InProgress]),
            'level_test' => LevelTest::query()->forceCreate(['user_id' => $user->id, 'program_id' => $user->activeProgram()->value('id'), 'level' => Level::A2, 'status' => LevelTestStatus::InProgress, 'started_at' => $startedAt, 'allowed_seconds' => 600, 'used_seconds' => 0]),
        };
    }
}
