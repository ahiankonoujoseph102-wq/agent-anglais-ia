<?php

namespace Tests\Feature\Live;

use App\Enums\AssessmentStatus;
use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\Assessment;
use App\Models\CourseLesson;
use App\Models\Lesson;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Live\LiveLog;
use App\Support\Live\LiveProviders;
use App\Support\Live\OpenAiRealtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\BuildsPrograms;
use Tests\Concerns\IgnoresSignalSilence;
use Tests\TestCase;

/**
 * Séances en direct avec deux fournisseurs : Gemini Live d'abord, OpenAI Realtime en secours (LIVE_PROVIDERS).
 * Le fournisseur est choisi au début de la séance et ne change jamais ensuite. Faux services uniquement :
 * aucun appel réel à Google ni à OpenAI.
 */
class LiveProvidersTest extends TestCase
{
    use BuildsPrograms, IgnoresSignalSilence, RefreshDatabase;

    private const GEMINI_KEY = 'cle-gemini-secrete';

    private const OPENAI_KEY = 'sk-cle-openai-secrete';

    /** Réponse simulée de Google à la création d'un jeton (200, 429, 500 ou « timeout »). */
    private int|string $gemini = 200;

    private int $openaiCall = 201;

    private int $callNumber = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.key' => self::GEMINI_KEY,
            'services.openai.key' => self::OPENAI_KEY,
            'services.live.providers' => ['gemini', 'openai'],
        ]);
        $this->freezeSecond();

        Http::fake([
            '*/auth_tokens' => fn () => match ($this->gemini) {
                200 => Http::response(['name' => 'auth_tokens/jeton-gemini']),
                'timeout' => throw new ConnectionException('cURL error 28: Operation timed out'),
                429 => Http::response(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded for requests per day.']], 429),
                default => Http::response(['error' => ['code' => $this->gemini, 'message' => 'Internal error']], $this->gemini),
            },
            '*/realtime/calls/*/hangup' => Http::response('', 200),
            'api.openai.com/v1/realtime/calls' => fn () => $this->openaiCall === 201
                ? Http::response("v=0\r\no=- reponse-openai\r\n", 201, ['Location' => '/v1/realtime/calls/rtc_'.(++$this->callNumber)])
                : Http::response(['error' => ['message' => 'You exceeded your current quota.', 'code' => 'insufficient_quota']], $this->openaiCall),
            // Présentation du programme (modèle texte) : texte type de repli.
            '*/interactions' => Http::response([], 503),
            'api.openai.com/v1/responses' => Http::response([], 503),
            'api.openai.com/v1/realtime/client_secrets' => Http::response(['value' => 'ek_jeton-ephemere', 'expires_at' => now()->addSeconds(10)->getTimestamp()]),
        ]);
    }

    private function subscriber(): User
    {
        $user = User::factory()->level(Level::A2)->create(['name' => 'Ama']);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user);

        return $user;
    }

    private function lesson(User $user): Lesson
    {
        return $user->lessons()->latest('id')->firstOrFail();
    }

    private function sentToOpenAi(string $path = '/realtime/calls'): int
    {
        return Http::recorded(fn (Request $request) => str_ends_with($request->url(), $path))->count();
    }

    private function sentToGemini(): int
    {
        return Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/auth_tokens'))->count();
    }

    public function test_gemini_available_the_lesson_runs_on_gemini_and_openai_is_never_called(): void
    {
        $user = $this->subscriber();

        $this->actingAs($user)->postJson('/seance/connexion')
            ->assertOk()
            ->assertJsonPath('provider', 'gemini')
            ->assertJsonStructure(['websocket_url', 'setup'])
            ->assertJsonMissingPath('call_url');

        $this->assertSame('gemini', $this->lesson($user)->live_provider);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/realtime/'));
    }

    public function test_a_gemini_token_refused_with_a_429_starts_the_lesson_at_openai_and_sets_gemini_aside(): void
    {
        $this->gemini = 429;
        $user = $this->subscriber();

        $response = $this->actingAs($user)->postJson('/seance/connexion')
            ->assertOk()
            ->assertJsonPath('provider', 'openai')
            ->assertJsonPath('remaining_seconds', 900)
            ->assertJsonMissingPath('websocket_url')
            // Les consignes restent sur le serveur : le navigateur ne reçoit que l'adresse de l'appel.
            ->assertJsonMissingPath('setup');

        $this->assertMatchesRegularExpression('#/en-direct/appel/[A-Za-z0-9]{40}$#', $response->json('call_url'));
        $this->assertSame('openai', $this->lesson($user)->live_provider);
        $this->assertTrue(LiveProviders::isPaused('gemini'));
        $this->assertSame('Gemini', LiveLog::recent()[0]['provider_label']);

        // Séance suivante (autre apprenant) : Gemini, mis de côté, n'est même pas essayé.
        $other = $this->subscriber();
        $this->actingAs($other)->postJson('/seance/connexion')->assertJsonPath('provider', 'openai');
        $this->assertSame(1, $this->sentToGemini());
    }

    public function test_a_gemini_timeout_or_error_also_falls_back_without_setting_gemini_aside(): void
    {
        foreach (['timeout', 500] as $answer) {
            $this->gemini = $answer;
            $user = $this->subscriber();

            $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->assertJsonPath('provider', 'openai');
            $this->assertFalse(LiveProviders::isPaused('gemini'), 'une panne passagère ne met pas Gemini de côté');
        }
    }

    public function test_without_an_openai_key_a_refused_gemini_token_ends_with_a_clear_message_and_nothing_is_created(): void
    {
        config(['services.openai.key' => null]);
        $this->gemini = 429;
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/evaluation/connexion')
            ->assertStatus(503)
            ->assertJsonPath('quota', true);

        $this->assertSame(0, Assessment::query()->count());
        $this->assertSame(0, $this->sentToOpenAi());
    }

    public function test_with_openai_alone_gemini_is_never_called(): void
    {
        config(['services.live.providers' => ['openai']]);

        $this->actingAs($this->subscriber())->postJson('/seance/connexion')->assertJsonPath('provider', 'openai');

        $this->assertSame(0, $this->sentToGemini());
    }

    public function test_the_server_opens_the_openai_call_with_exactly_the_same_instructions_and_function(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion');
        // Consignes données à Gemini pour cette séance, à comparer avec celles d'OpenAI.
        $gemini = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/auth_tokens'))->first()[0]['bidiGenerateContentSetup'];

        // Le navigateur n'a pas pu joindre Gemini : la même séance commence chez OpenAI.
        $url = $this->actingAs($user)->postJson('/seance/connexion', ['failed' => 'gemini'])->json('call_url');
        $other = $user;

        $this->actingAs($other)->postJson($url, ['sdp' => "v=0\r\no=- offre\r\n"])
            ->assertOk()
            ->assertJsonPath('sdp', "v=0\r\no=- reponse-openai\r\n");

        $this->assertSame('rtc_1', $this->lesson($other)->live_call_id);

        Http::assertSent(function (Request $request) use ($gemini) {
            if (! str_ends_with($request->url(), '/realtime/calls')) {
                return false;
            }

            $fields = collect($request->data())->pluck('contents', 'name');
            $session = json_decode($fields['session'], true);
            $tool = $session['tools'][0];

            return $request->hasHeader('Authorization', 'Bearer '.self::OPENAI_KEY)
                && $request->hasHeader('OpenAI-Safety-Identifier')
                && $fields['sdp'] === "v=0\r\no=- offre\r\n"
                && $session['model'] === 'gpt-realtime-2.1-mini'
                && $session['audio']['output']['voice'] === 'cedar'
                // Vitesse de la voix selon le niveau (A2 : 0,85), en plus des consignes de débit.
                && $session['audio']['output']['speed'] === 0.85
                && str_contains($session['instructions'], (string) config('teacher.pace.levels.A2'))
                && $session['audio']['input']['turn_detection'] === ['type' => 'server_vad', 'threshold' => 0.6, 'prefix_padding_ms' => 300, 'silence_duration_ms' => 900, 'create_response' => true, 'interrupt_response' => true]
                && $session['audio']['input']['transcription']['model'] === 'gpt-4o-mini-transcribe'
                // Réponse la plus rapide : réflexion du modèle réduite au minimum.
                && $session['reasoning'] === ['effort' => 'minimal']
                // EXACTEMENT les consignes données à Gemini (personnage, leçon, fiche du site, situation).
                && str_contains($session['instructions'], 'SITE GUIDE (in French)')
                && $session['instructions'] === $gemini['systemInstruction']['parts'][0]['text']
                && $tool['name'] === 'end_lesson'
                && $tool['parameters']['type'] === 'object'
                && $tool['parameters']['properties']['errors']['items']['type'] === 'object'
                && $tool['parameters']['properties']['lesson_status']['enum'] === ['completed', 'repeat'];
        });
    }

    /**
     * Session envoyée à OpenAI au dernier appel ouvert.
     *
     * @return array<string, mixed>
     */
    private function lastOpenAiSession(): array
    {
        $request = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/realtime/calls'))->last()[0];

        return json_decode(collect($request->data())->pluck('contents', 'name')['session'], true);
    }

    public function test_the_openai_voice_speed_follows_the_level_and_the_assessment_starts_slowly(): void
    {
        $this->gemini = 429;

        // Évaluation : niveau inconnu, même pour un compte qui a déjà un niveau.
        $assessed = User::factory()->level(Level::C1)->create();
        $url = $this->actingAs($assessed)->postJson('/evaluation/connexion')->assertJsonPath('provider', 'openai')->json('call_url');
        $this->actingAs($assessed)->postJson($url, ['sdp' => "v=0\r\no=- offre\r\n"])->assertOk();
        $this->assertSame(0.9, $this->lastOpenAiSession()['audio']['output']['speed']);
        $this->assertStringContainsString((string) config('teacher.pace.levels.unknown'), $this->lastOpenAiSession()['instructions']);

        // Séance du jour C1 : vitesse naturelle.
        $advanced = User::factory()->level(Level::C1)->create();
        Subscription::factory()->for($advanced)->forPlan('essentiel')->create();
        $this->buildProgram($advanced);
        $url = $this->actingAs($advanced)->postJson('/seance/connexion')->assertJsonPath('provider', 'openai')->json('call_url');
        $this->actingAs($advanced)->postJson($url, ['sdp' => "v=0\r\no=- offre\r\n"])->assertOk();
        $this->assertEquals(1.0, $this->lastOpenAiSession()['audio']['output']['speed']);
    }

    public function test_the_speed_comes_from_the_configuration_and_stays_in_the_allowed_range(): void
    {
        $this->assertSame(0.85, OpenAiRealtime::speed('A1'));
        $this->assertSame(0.92, OpenAiRealtime::speed('B1'));
        $this->assertSame(0.9, OpenAiRealtime::speed('inconnu'), 'niveau inconnu : valeur de l\'évaluation');

        config(['services.openai.realtime_speed' => ['unknown' => 0.1, 'A1' => 3]]);
        $this->assertSame(0.25, OpenAiRealtime::speed('unknown'));
        $this->assertSame(1.5, OpenAiRealtime::speed('A1'));

        // Aucune vitesse réglée : rien n'est envoyé (vitesse normale d'OpenAI).
        config(['services.openai.realtime_speed' => []]);
        $this->assertNull(OpenAiRealtime::speed('A1'));
        $this->assertArrayNotHasKey('speed', OpenAiRealtime::session(['systemInstruction' => ['parts' => [['text' => 'x']]]], 'A1')['audio']['output']);
    }

    public function test_gemini_gets_no_speed_setting_only_the_pace_instructions(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertJsonPath('provider', 'gemini');
        $setup = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/auth_tokens'))->first()[0]['bidiGenerateContentSetup'];

        $this->assertSame(['voiceConfig'], array_keys($setup['generationConfig']['speechConfig']));
        $this->assertStringNotContainsString('speed', json_encode($setup['generationConfig']));
        $this->assertStringContainsString((string) config('teacher.pace.levels.A2'), $setup['systemInstruction']['parts'][0]['text']);
    }

    public function test_the_call_address_is_single_use_and_personal(): void
    {
        $this->gemini = 429;
        $user = $this->subscriber();
        $url = $this->actingAs($user)->postJson('/seance/connexion')->json('call_url');

        $this->actingAs($this->subscriber())->postJson($url, ['sdp' => 'v=0'])->assertStatus(410);
        $this->actingAs($user)->postJson($url, ['sdp' => 'v=0'])->assertStatus(410);
        $this->assertSame(0, $this->sentToOpenAi());
    }

    public function test_an_openai_call_refused_is_reported_without_creating_anything_more(): void
    {
        $this->gemini = 429;
        $this->openaiCall = 429;
        $user = $this->subscriber();
        $url = $this->actingAs($user)->postJson('/seance/connexion')->json('call_url');

        $this->actingAs($user)->postJson($url, ['sdp' => 'v=0'])
            ->assertStatus(503)
            ->assertJsonPath('quota', true);

        $this->assertNull($this->lesson($user)->live_call_id);
        $this->assertTrue(LiveProviders::isPaused('openai'));
    }

    public function test_a_browser_unable_to_reach_gemini_before_the_first_word_gets_openai(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertJsonPath('provider', 'gemini');

        $this->travel(5)->seconds();
        $this->actingAs($user)->postJson('/seance/connexion', ['failed' => 'gemini'])
            ->assertOk()
            ->assertJsonPath('provider', 'openai');

        $lesson = $this->lesson($user);
        $this->assertSame('openai', $lesson->live_provider);
        $this->assertSame(1, Lesson::query()->count(), 'la même séance continue, rien n\'est compté deux fois');
        $this->assertSame('Connexion du navigateur', LiveLog::recent()[0]['step']);
        $this->assertFalse(LiveProviders::isPaused('gemini'), 'un réseau d\'apprenant ne met pas Gemini de côté pour tout le monde');
    }

    public function test_never_a_change_of_provider_in_the_middle_of_a_lesson(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertJsonPath('provider', 'gemini');

        // Trois minutes plus tard, une coupure : même un « échec » annoncé ne fait pas changer de fournisseur.
        $this->travel(3)->minutes();
        $this->actingAs($user)->postJson('/seance/signal');
        $this->actingAs($user)->postJson('/seance/connexion', ['failed' => 'gemini'])->assertJsonPath('provider', 'gemini');

        // Et une séance commencée chez OpenAI y reste, même quand Gemini est de nouveau disponible.
        $this->gemini = 429;
        $other = $this->subscriber();
        $this->actingAs($other)->postJson('/seance/connexion')->assertJsonPath('provider', 'openai');
        $this->gemini = 200;
        $this->travel(1)->minutes();
        $this->actingAs($other)->postJson('/seance/connexion')->assertJsonPath('provider', 'openai')->assertJsonPath('resumed', true);
    }

    public function test_a_teacher_who_no_longer_answers_continues_the_same_lesson_at_the_other_provider(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertJsonPath('provider', 'gemini');
        $this->travel(6)->minutes();

        // Blocage silencieux de Gemini (relance et nouvelle connexion sans effet) : la MÊME séance continue chez OpenAI.
        $this->actingAs($user)->postJson('/seance/connexion', ['unresponsive' => 'gemini'])
            ->assertOk()
            ->assertJsonPath('provider', 'openai')
            ->assertJsonPath('resumed', true);

        $this->assertSame('openai', $this->lesson($user)->live_provider);
        $this->assertSame(1, Lesson::query()->count());
        $this->assertSame('Aucune réponse', LiveLog::recent()[0]['step']);
        $this->assertFalse(LiveProviders::isPaused('gemini'), 'un blocage d\'une séance ne met pas Gemini de côté pour tout le monde');

        // Un fournisseur qui n'est pas celui de la séance ne change rien.
        $this->actingAs($user)->postJson('/seance/connexion', ['unresponsive' => 'gemini'])->assertJsonPath('provider', 'openai');
    }

    public function test_the_cue_to_finish_a_cut_answer_is_given_to_the_browser(): void
    {
        $cues = $this->actingAs($this->subscriber())->postJson('/seance/connexion')->json('cues');

        $this->assertStringContainsString('cut off', $cues['stalled']);
        $this->assertArrayHasKey('nudge', $cues);
    }

    public function test_gemini_refusing_a_reconnection_continues_the_same_lesson_at_openai(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertJsonPath('provider', 'gemini');
        $this->travel(4)->minutes();
        $this->gemini = 429;

        // Refus de reconnexion chez Gemini (pas une simple lenteur) : la MÊME séance continue chez OpenAI.
        $this->actingAs($user)->postJson('/seance/connexion', ['handle' => 'reprise-gemini'])
            ->assertOk()
            ->assertJsonPath('provider', 'openai')
            ->assertJsonPath('resumed', true)
            ->assertJsonPath('remaining_seconds', 660);

        $lesson = $this->lesson($user);
        $this->assertSame('openai', $lesson->live_provider);
        $this->assertSame(1, Lesson::query()->count(), 'la même séance continue, rien n\'est compté deux fois');
        $this->assertSame(240, $lesson->elapsedUntil(now()));
    }

    public function test_every_provider_refusing_a_reconnection_ends_cleanly_with_the_time_used_counted(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertJsonPath('provider', 'gemini');
        $this->travel(4)->minutes();
        $this->gemini = 429;
        config(['services.openai.key' => null]);

        $this->actingAs($user)->postJson('/seance/connexion')->assertStatus(503);

        $this->assertSame(0, $this->sentToOpenAi());
        $this->actingAs($user)->postJson('/seance/fin')->assertOk();
        $this->assertSame(240, $this->lesson($user)->used_seconds);
    }

    public function test_a_slow_network_never_changes_the_provider_in_the_middle_of_a_lesson(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user)->postJson('/seance/connexion')->assertJsonPath('provider', 'gemini');
        $this->travel(4)->minutes();

        // Le navigateur n'a pas pu joindre Gemini (réseau lent), mais Gemini accepte : on reste chez Gemini.
        $this->actingAs($user)->postJson('/seance/connexion', ['failed' => 'gemini'])->assertJsonPath('provider', 'gemini');
        $this->assertSame(0, $this->sentToOpenAi());
    }

    public function test_a_reconnection_at_openai_opens_a_new_call_and_hangs_up_the_old_one(): void
    {
        $this->gemini = 429;
        $user = $this->subscriber();
        $first = $this->actingAs($user)->postJson('/seance/connexion')->json('call_url');
        $this->actingAs($user)->postJson($first, ['sdp' => 'v=0']);
        $this->travel(2)->minutes();

        $second = $this->actingAs($user)->postJson('/seance/connexion')->json('call_url');
        $this->actingAs($user)->postJson($second, ['sdp' => 'v=0'])->assertOk();

        $this->assertSame('rtc_2', $this->lesson($user)->live_call_id);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/realtime/calls/rtc_1/hangup'));
    }

    public function test_the_server_hangs_up_the_openai_call_itself_at_the_time_limit(): void
    {
        $this->gemini = 429;
        $user = $this->subscriber();
        $url = $this->actingAs($user)->postJson('/seance/connexion')->json('call_url');
        $this->actingAs($user)->postJson($url, ['sdp' => 'v=0']);

        // Le navigateur continue d'envoyer ses signaux, mais le temps de la formule (15 minutes) est écoulé.
        $this->travel(15)->minutes();
        $this->travel(5)->seconds();
        $this->artisan('live:close-stale')->assertSuccessful();

        $lesson = $this->lesson($user);
        $this->assertNotSame(LessonStatus::InProgress, $lesson->status);
        $this->assertSame(900, $lesson->used_seconds);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/realtime/calls/rtc_1/hangup')
            && $request->hasHeader('Authorization', 'Bearer '.self::OPENAI_KEY));
    }

    public function test_the_end_of_the_lesson_hangs_up_the_call_once(): void
    {
        $this->gemini = 429;
        $user = $this->subscriber();
        $url = $this->actingAs($user)->postJson('/seance/connexion')->json('call_url');
        $this->actingAs($user)->postJson($url, ['sdp' => 'v=0']);
        $this->travel(1)->minutes();

        $this->actingAs($user)->postJson('/seance/fin')->assertOk();
        $this->actingAs($user)->postJson('/seance/fin')->assertOk();

        $this->assertSame(1, $this->sentToOpenAi('/hangup'));
    }

    public function test_the_assessment_verdict_is_saved_only_once_at_openai_too(): void
    {
        $this->gemini = 429;
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/evaluation/connexion')->assertJsonPath('provider', 'openai');
        $this->travel(3)->minutes();

        $this->actingAs($user)->postJson('/evaluation/resultat', ['level' => 'B1', 'feedback' => 'Très bien.'])->assertOk()->assertJsonPath('questions', true);
        $this->actingAs($user)->postJson('/evaluation/resultat', ['level' => 'C2', 'feedback' => 'Encore.'])->assertStatus(409);

        $assessment = $user->assessment()->first();
        $this->assertSame(Level::B1, $assessment->level);
        $this->assertSame('openai', $assessment->live_provider);
        $this->assertSame(AssessmentStatus::InProgress, $assessment->status, 'le moment des questions continue');
    }

    public function test_a_course_session_falls_back_too(): void
    {
        $this->gemini = 429;
        $user = $this->subscriber();
        $course = CourseLesson::query()->forceCreate(['user_id' => $user->id, 'title' => 'Present simple', 'source' => 'photos', 'page_count' => 1, 'content' => ['summary' => 'Le présent simple.'], 'text' => 'She works.']);

        $this->actingAs($user)->postJson(route('courses.connect', $course))->assertOk()->assertJsonPath('provider', 'openai');

        $this->assertSame('openai', $user->courseSessions()->sole()->live_provider);
    }

    public function test_the_logs_never_contain_a_key_nor_a_token(): void
    {
        $written = [];
        Log::listen(function ($message) use (&$written) {
            $written[] = $message->message.' '.json_encode($message->context);
        });
        $this->gemini = 429;
        $this->openaiCall = 401;
        $user = $this->subscriber();
        $url = $this->actingAs($user)->postJson('/seance/connexion')->json('call_url');
        $this->actingAs($user)->postJson($url, ['sdp' => 'v=0']);

        $this->assertNotEmpty($written);

        foreach ([...$written, json_encode(LiveLog::recent())] as $line) {
            $this->assertStringNotContainsString(self::GEMINI_KEY, $line);
            $this->assertStringNotContainsString(self::OPENAI_KEY, $line);
            $this->assertStringNotContainsString('ek_', $line);
        }
    }

    // Administration : fiche, consommation, diagnostic et essai de 30 secondes.

    public function test_the_provider_of_each_session_is_shown_on_the_learner_page_and_in_the_consumption(): void
    {
        $this->gemini = 429;
        $user = $this->subscriber();
        $url = $this->actingAs($user)->postJson('/seance/connexion')->json('call_url');
        $this->actingAs($user)->postJson($url, ['sdp' => 'v=0']);
        $this->travel(10)->minutes();
        $this->actingAs($user)->postJson('/seance/fin');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSeeText('Conversations')
            ->assertSeeInOrder(['Séance du jour', '10 minutes', 'OpenAI']);

        $this->actingAs($admin)->get('/admin/consommation')
            ->assertOk()
            ->assertSeeText('Séances en direct par fournisseur')
            // 10 minutes à 0,04 $ la minute.
            ->assertSee("0,4000\u{00A0}$", false);
    }

    public function test_the_diagnostic_tests_the_token_of_each_provider_without_ever_showing_it(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/diagnostic-texte')
            ->assertOk()
            ->assertSeeText('Séances en direct')
            ->assertSeeText('gpt-realtime-2.1-mini')
            ->assertSeeText('cedar')
            ->assertSeeText('Essayer une conversation de 30 secondes')
            ->assertDontSee(self::GEMINI_KEY)
            ->assertDontSee(self::OPENAI_KEY);

        $this->actingAs($admin)->from('/admin/diagnostic-texte')->post('/admin/diagnostic-texte/direct/openai/essai')
            ->assertRedirect('/admin/diagnostic-texte#direct')
            ->assertSessionHas('diagnostic', fn (array $result) => $result['ok'] && str_contains($result['text'], 'OpenAI (séances en direct) : réussi') && ! str_contains($result['text'], 'ek_'));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/realtime/client_secrets')
            && $request['expires_after'] === ['anchor' => 'created_at', 'seconds' => 10]);

        $this->gemini = 429;
        $this->actingAs($admin)->post('/admin/diagnostic-texte/direct/gemini/essai')
            ->assertSessionHas('diagnostic', fn (array $result) => ! $result['ok'] && str_contains($result['text'], 'Quota Gemini atteint'));

        $this->actingAs($admin)->get('/admin/diagnostic-texte')->assertSeeText('Diagnostic : jeton');
        $this->actingAs($this->subscriber())->post('/admin/diagnostic-texte/direct/openai/essai')->assertForbidden();
    }

    public function test_the_30_second_trial_runs_at_the_chosen_provider_only_and_is_hung_up_by_the_server(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/diagnostic-texte/direct/openai/conversation')
            ->assertOk()
            ->assertSee('data-mode="trial"', false)
            ->assertSeeText('Essai de conversation (OpenAI)');

        // Gemini disponible : l'essai OpenAI se fait quand même chez OpenAI (aucun secours).
        $url = $this->actingAs($admin)->postJson('/admin/diagnostic-texte/direct/openai/conversation/connexion')
            ->assertOk()
            ->assertJsonPath('provider', 'openai')
            ->assertJsonPath('remaining_seconds', 30)
            ->json('call_url');
        $this->actingAs($admin)->postJson($url, ['sdp' => 'v=0'])->assertOk();
        $this->assertSame(0, $this->sentToGemini());

        $this->travel(31)->seconds();
        $this->actingAs($admin)->postJson('/admin/diagnostic-texte/direct/openai/conversation/signal')->assertJsonPath('active', false);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/realtime/calls/rtc_1/hangup'));

        // Gemini : le jeton expire à la fin des 30 secondes.
        $this->actingAs($admin)->postJson('/admin/diagnostic-texte/direct/gemini/conversation/connexion')
            ->assertOk()
            ->assertJsonPath('provider', 'gemini')
            ->assertJsonPath('token_expires_in', 30);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/auth_tokens')
            && $request['expireTime'] === now()->addSeconds(30)->utc()->toIso8601ZuluString()
            && ! isset($request['bidiGenerateContentSetup']['tools']));
    }
}
