<?php

namespace Tests\Feature\Teachers;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Enums\ProgramLessonStatus;
use App\Enums\ProgramStatus;
use App\Models\Lesson;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Gemini\TeacherSetup;
use App\Support\Gemini\TeacherVoice;
use App\Support\Teachers\Teachers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

/**
 * Teacher Rose, étape 2 : séance du jour, « Mes cours » et bilan de niveau avec sa voix (faux Gemini, faux OpenAI :
 * aucun appel réel). Son choix n'est montré aux apprenants que quand elle est prête (voix ET visage, « ready ») ;
 * l'évaluation gratuite reste avec Teacher Joe ; retour à Teacher Joe seul en la marquant pas prête ou en la retirant.
 */
class TeacherRoseTest extends TestCase
{
    use BuildsPrograms, HasCourses, RefreshDatabase;

    private const OPENAI_KEY = 'sk-cle-openai-secrete';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.key' => 'cle-secrete-du-serveur',
            'services.gemini.voice' => 'Achird',
            'services.openai.key' => self::OPENAI_KEY,
            'services.openai.realtime_voice' => 'cedar',
        ]);
        Http::fake([
            '*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton']),
            '*/interactions' => Http::response([], 500),
            'api.openai.com/v1/realtime/calls' => Http::response("v=0\r\no=- reponse-openai\r\n", 201, ['Location' => '/v1/realtime/calls/rtc_1']),
            '*/realtime/calls/*/hangup' => Http::response('', 200),
            'api.openai.com/v1/responses' => Http::response([], 503),
        ]);
    }

    /** Teacher Rose proposée aux apprenants (étape 3 : visage prêt et validé). */
    private function roseReady(): void
    {
        config(['teachers.list.rose.ready' => true]);
    }

    private function subscriber(?string $teacher = null, Level $level = Level::A2): User
    {
        $user = User::factory()->level($level)->create(['name' => 'Afi Mensah', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, $level);

        if ($teacher !== null) {
            $user->forceFill(['teacher' => $teacher])->save();
        }

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function geminiSetup(User $user, string $url): array
    {
        return $this->actingAs($user)->postJson($url)->assertOk()->assertJsonPath('provider', 'gemini')->json('setup');
    }

    private static function voice(array $setup): string
    {
        return $setup['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName'];
    }

    private static function instructions(array $setup): string
    {
        return $setup['systemInstruction']['parts'][0]['text'];
    }

    // --- Rien de visible tant qu'elle n'est pas prête -------------------------------------------------------------

    public function test_teacher_rose_exists_but_is_not_offered_to_learners_before_her_face_is_ready(): void
    {
        $this->assertSame(['joe', 'rose'], array_keys(Teachers::all()));
        $this->assertSame('Teacher Rose', Teachers::find('rose')->name());
        $this->assertFalse(Teachers::find('rose')->isReady());
        $this->assertSame(['joe'], array_keys(Teachers::choosable()));
        $this->assertFalse(Teachers::hasChoice());

        $user = $this->subscriber();
        $this->actingAs($user)->get('/espace')->assertOk()->assertDontSee('id="professeur"', false)->assertDontSee('Teacher Rose');
        $this->actingAs($user)->post('/espace/professeur', ['teacher' => 'rose'])->assertNotFound();
        $this->assertNull($user->fresh()->getAttributes()['teacher']);
    }

    public function test_a_learner_set_on_rose_while_she_is_not_ready_gets_teacher_joe(): void
    {
        $user = $this->subscriber('rose');

        $setup = $this->geminiSetup($user, '/seance/connexion');

        $this->assertSame('Achird', self::voice($setup));
        $this->assertStringContainsString('Teacher Joe', self::instructions($setup));
        $this->assertStringNotContainsString('Teacher Rose', self::instructions($setup));
        $this->assertSame('joe', Lesson::query()->sole()->teacher);
        $this->actingAs($user)->get('/seance')->assertOk()->assertSee('data-teacher-name="Teacher Joe"', false);
    }

    // --- Choix dans « Mon espace » -----------------------------------------------------------------------------------

    public function test_when_she_is_ready_the_learner_chooses_her_in_my_space_and_can_switch_back(): void
    {
        $this->roseReady();
        $user = $this->subscriber();

        $this->actingAs($user)->get('/espace')->assertOk()
            ->assertSee('id="professeur"', false)
            ->assertSee('Votre professeur')
            ->assertSee('Choisir Teacher Rose');

        $this->actingAs($user)->post('/espace/professeur', ['teacher' => 'rose'])
            ->assertRedirect(route('dashboard').'#professeur')
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'Teacher Rose : elle vous accompagne'));
        $this->assertSame('rose', $user->fresh()->getAttributes()['teacher']);

        $this->actingAs($user)->get('/espace')->assertSee('Choisir Teacher Joe');

        $this->actingAs($user)->post('/espace/professeur', ['teacher' => 'joe'])->assertRedirect();
        $this->assertSame('joe', $user->fresh()->getAttributes()['teacher']);

        $this->actingAs($user)->post('/espace/professeur', ['teacher' => 'inconnu'])->assertSessionHasErrors('teacher');
    }

    // --- Séance du jour ---------------------------------------------------------------------------------------------

    public function test_the_daily_lesson_with_rose_has_her_voice_her_name_and_her_feminine_personality(): void
    {
        $this->roseReady();
        $user = $this->subscriber('rose');

        $page = $this->actingAs($user)->get('/seance')->assertOk();
        $page->assertSee('data-teacher-name="Teacher Rose"', false)
            ->assertSee('face-rose', false)
            // Son personnage 3D (même module que Teacher Joe), son visage simple en repli.
            ->assertSee('data-teacher="loading"', false)
            ->assertSee('teacher-rose.glb', false)
            ->assertDontSee('teacher-joe.glb', false)
            ->assertSee('La professeure vous annoncera la durée');

        $setup = $this->geminiSetup($user, '/seance/connexion');
        $instructions = self::instructions($setup);

        $this->assertSame('Sulafat', self::voice($setup));
        $this->assertStringContainsString('you are Teacher Rose, a woman', $instructions);
        $this->assertStringContainsString('« une professeure virtuelle »', $instructions);
        $this->assertStringNotContainsString('Teacher Joe', $instructions);
        $this->assertStringNotContainsString('un professeur virtuel', $instructions);
        // Même pédagogie : quatre temps, tableau, fiche du site.
        $this->assertStringContainsString('SITE GUIDE (in French)', $instructions);
        $this->assertStringContainsString((string) config('platform.lessons.phase_phrases.practice'), $instructions);
        $this->assertSame('rose', Lesson::query()->sole()->teacher);
    }

    public function test_a_started_lesson_keeps_its_teacher_even_if_the_learner_switches(): void
    {
        $this->roseReady();
        $user = $this->subscriber('rose');
        $this->geminiSetup($user, '/seance/connexion');

        $this->actingAs($user)->post('/espace/professeur', ['teacher' => 'joe']);

        // Reconnexion de la même séance : toujours Teacher Rose (sa voix est figée pour toute la séance).
        $setup = $this->geminiSetup($user->fresh(), '/seance/connexion');
        $this->assertSame('Sulafat', self::voice($setup));
        $this->assertSame(1, Lesson::query()->count());
        $this->actingAs($user)->get('/seance')->assertSee('data-teacher-name="Teacher Rose"', false);
    }

    public function test_level_progress_and_errors_are_common_to_both_teachers(): void
    {
        $this->roseReady();
        $user = $this->subscriber();
        $user->learnerErrors()->create(['wrong' => 'I have 25 years', 'correct' => 'I am 25', 'explanation' => 'âge avec « be »']);

        $joe = self::instructions($this->geminiSetup($user, '/seance/connexion'));
        Lesson::query()->update(['status' => LessonStatus::Completed, 'ended_at' => now(), 'used_seconds' => 60]);

        $this->actingAs($user)->post('/espace/professeur', ['teacher' => 'rose']);
        $rose = self::instructions($this->geminiSetup($user->fresh(), '/seance/connexion'));

        foreach (['I have 25 years', 'Niveau : A2', "TODAY'S LESSON"] as $common) {
            $this->assertStringContainsString($common, $joe);
            $this->assertStringContainsString($common, $rose);
        }

        $this->assertSame(['joe', 'rose'], Lesson::query()->orderBy('id')->pluck('teacher')->all());
        $this->assertSame(Level::A2, $user->fresh()->level);
    }

    public function test_the_daily_lesson_with_rose_at_openai_uses_her_openai_voice(): void
    {
        $this->roseReady();
        config(['services.live.providers' => ['openai']]);
        $user = $this->subscriber('rose');

        $url = $this->actingAs($user)->postJson('/seance/connexion')->assertOk()->assertJsonPath('provider', 'openai')->json('call_url');
        $this->actingAs($user)->postJson($url, ['sdp' => "v=0\r\no=- offre\r\n"])->assertOk();

        $request = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/realtime/calls'))->last()[0];
        $session = json_decode(collect($request->data())->pluck('contents', 'name')['session'], true);

        $this->assertSame('marin', $session['audio']['output']['voice']);
        $this->assertStringContainsString('you are Teacher Rose, a woman', $session['instructions']);
    }

    // --- « Mes cours » et bilan de niveau ---------------------------------------------------------------------------

    public function test_my_courses_with_rose(): void
    {
        $this->roseReady();
        $user = $this->learner();
        $user->forceFill(['welcomed_at' => now(), 'teacher' => 'rose'])->save();
        $course = $this->storedLesson($user);

        $this->actingAs($user)->get(route('courses.session', $course))->assertOk()
            ->assertSee('Teacher Rose a lu votre leçon')
            ->assertSee('Elle vous demandera ce que vous voulez faire')
            ->assertSee('face-rose', false);

        $setup = $this->geminiSetup($user, route('courses.connect', $course));

        $this->assertSame('Sulafat', self::voice($setup));
        $this->assertStringContainsString('you are Teacher Rose, a woman', self::instructions($setup));
        $this->assertSame('rose', $user->courseSessions()->sole()->teacher);
    }

    public function test_the_level_test_with_rose(): void
    {
        $this->roseReady();
        $user = $this->subscriber('rose');
        $program = $user->activeProgram()->first();
        $program->programLessons()->update(['status' => ProgramLessonStatus::Completed, 'completed_at' => now()]);
        $program->update(['status' => ProgramStatus::LevelTestDue]);

        $this->actingAs($user)->get('/bilan-niveau')->assertOk()->assertSee('La professeure vérifie');

        $setup = $this->geminiSetup($user, '/bilan-niveau/connexion');

        $this->assertSame('Sulafat', self::voice($setup));
        $this->assertStringContainsString('Teacher Rose', self::instructions($setup));
        $this->assertSame('rose', $user->levelTests()->sole()->teacher);
    }

    // --- Évaluation gratuite : jamais touchée avant l'essai réel du fondateur ---------------------------------------

    public function test_the_free_assessment_stays_with_teacher_joe_even_when_rose_is_chosen(): void
    {
        $this->roseReady();
        $user = User::factory()->create(['name' => 'Kossi']);
        $user->forceFill(['teacher' => 'rose'])->save();

        $this->actingAs($user)->get('/evaluation')->assertOk()->assertSee('data-teacher-name="Teacher Joe"', false)->assertDontSee('face-rose', false);

        $setup = $this->geminiSetup($user->fresh(), '/evaluation/connexion');

        $this->assertSame('Achird', self::voice($setup));
        $this->assertStringNotContainsString('Teacher Rose', self::instructions($setup));
        $this->assertSame('joe', $user->assessment()->first()->teacher);
    }

    // --- Retour rapide à Teacher Joe seul ---------------------------------------------------------------------------

    public function test_removing_rose_from_the_list_brings_her_learners_back_to_teacher_joe(): void
    {
        $this->roseReady();
        $user = $this->subscriber('rose');
        $this->assertSame('rose', Teachers::forUser($user)->key);

        config(['teachers.list' => array_diff_key(config('teachers.list'), ['rose' => true])]);

        $this->assertSame('joe', Teachers::forUser($user)->key);
        $this->assertFalse(Teachers::hasChoice());
        $setup = $this->geminiSetup($user, '/seance/connexion');
        $this->assertSame('Achird', self::voice($setup));
        $this->actingAs($user)->get('/espace')->assertOk()->assertDontSee('id="professeur"', false);
    }

    public function test_an_old_conversation_with_rose_still_reads_after_she_is_removed(): void
    {
        $user = $this->subscriber();
        $lesson = $user->lessons()->create([
            'subscription_id' => $user->activeSubscription->id, 'date' => today(), 'allowed_seconds' => 900, 'used_seconds' => 600,
            'started_at' => now()->subHour(), 'ended_at' => now(), 'status' => LessonStatus::Completed, 'teacher' => 'rose',
        ]);

        config(['teachers.list' => array_diff_key(config('teachers.list'), ['rose' => true])]);

        $this->assertSame('joe', $lesson->fresh()->teacher()->key);
    }

    // --- Teacher Joe inchangé ---------------------------------------------------------------------------------------

    public function test_teacher_joe_keeps_his_voice_his_name_and_no_personality_block_when_rose_is_ready(): void
    {
        $this->roseReady();
        $user = $this->subscriber('joe');

        $setup = $this->geminiSetup($user, '/seance/connexion');

        $this->assertSame('Achird', self::voice($setup));
        $this->assertStringContainsString('« un professeur virtuel »', self::instructions($setup));
        $this->assertStringNotContainsString('YOUR PERSONALITY', self::instructions($setup));
        $this->assertSame(TeacherVoice::current(), Teachers::default()->geminiVoice());
        $this->assertSame(json_encode(TeacherSetup::lesson(new User(['name' => 'Afi']), 15)), json_encode(TeacherSetup::lesson(new User(['name' => 'Afi']), 15, null, null, 1, Teachers::default())));
    }

    // --- Visage 3D ---------------------------------------------------------------------------------------------------

    public function test_rose_has_her_own_3d_model_with_the_same_bones_and_mouth_shapes_as_joe(): void
    {
        $rose = public_path((string) config('avatar.rose.file'));
        $joe = public_path((string) config('avatar.model.file'));

        $this->assertFileExists($rose);
        $this->assertNotSame(hash_file('sha256', $joe), hash_file('sha256', $rose));
        $this->assertLessThan(5 * 1024 * 1024, filesize($rose));

        // Même squelette et mêmes formes de bouche que Teacher Joe : même animation, mêmes lèvres synchronisées.
        foreach (['Hips', 'Head', 'LeftEye', 'RightHand', 'viseme_aa', 'viseme_PP', 'jawOpen', 'eyeBlinkLeft', 'mouthSmileLeft'] as $name) {
            $this->assertStringContainsString('"'.$name.'"', (string) file_get_contents($rose));
        }

        // Toujours invisible pour les apprenants tant que le fondateur n'a pas validé son visage.
        $this->assertFalse((bool) config('teachers.list.rose.ready'));
    }
}
