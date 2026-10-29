<?php

namespace Tests\Feature\Teachers;

use App\Enums\LessonStatus;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Gemini\TeacherVoice;
use App\Support\Gemini\VoiceSampler;
use App\Support\Live\LiveUsage;
use App\Support\Live\Transcripts;
use App\Support\PrivateStorage;
use App\Support\Teachers\Teachers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Teacher Rose dans l'administration : « Essai des voix » par professeur (Gemini et OpenAI, aucun appel réel),
 * « Consommation » par professeur, essai de 30 secondes du diagnostic avec sa voix, « Lire la conversation ».
 * Tout cela marche avant qu'elle soit proposée aux apprenants.
 */
class TeacherRoseAdminTest extends TestCase
{
    use RefreshDatabase;

    private const WAV = "RIFF\x24\x00\x00\x00WAVEfmt fausse-voix";

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.key' => 'cle-secrete-du-serveur',
            'services.gemini.voice' => 'Achird',
            'services.openai.key' => 'sk-cle-openai-secrete',
            'services.openai.realtime_voice' => 'cedar',
        ]);
        Http::fake([
            '*/interactions' => Http::response(['steps' => [
                ['type' => 'user_input'],
                ['type' => 'model_output', 'content' => [['type' => 'audio', 'data' => base64_encode(self::WAV)]]],
            ]]),
            'api.openai.com/v1/audio/speech' => Http::response(str_repeat("\x00\x01", 2400)),
            '*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton']),
            'api.openai.com/v1/realtime/calls' => Http::response("v=0\r\n", 201, ['Location' => '/v1/realtime/calls/rtc_1']),
            '*/realtime/calls/*/hangup' => Http::response('', 200),
        ]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_voice_trial_has_a_tab_per_teacher_and_teacher_joe_is_unchanged(): void
    {
        $this->actingAs($this->admin())->get('/admin/voix')->assertOk()
            ->assertSee('Voix actuelle : <strong>Achird</strong>', false)
            ->assertSee('Teacher Rose')
            ->assertDontSee('Sulafat')
            ->assertDontSee('Voix OpenAI');
    }

    public function test_rose_tab_lists_female_voices_at_gemini_and_openai(): void
    {
        $this->actingAs($this->admin())->get('/admin/voix?professeur=rose')->assertOk()
            ->assertSee('choisissez celle de Teacher Rose')
            ->assertSee('pas encore proposée aux apprenants')
            ->assertSee('Voix Gemini (séances en direct)')
            ->assertSee('Voix actuelle : <strong>Sulafat</strong>', false)
            ->assertSee('Kore')->assertSee('Aoede')->assertSee('Despina')->assertSee('Erinome')->assertSee('Vindemiatrix')
            ->assertSee('Voix OpenAI (secours des séances en direct)')
            ->assertSee('Voix actuelle : <strong>marin</strong>', false)
            ->assertSee('shimmer')->assertSee('coral')->assertSee('sage')
            ->assertSee('/admin/voix/Kore/extrait?professeur=rose&amp;fournisseur=gemini', false)
            ->assertSee('/admin/voix/shimmer/extrait?professeur=rose&amp;fournisseur=openai', false)
            ->assertDontSee('Charon');
    }

    public function test_rose_gemini_sample_is_said_with_her_name_and_a_female_style(): void
    {
        $this->actingAs($this->admin())->get('/admin/voix/Kore/extrait?professeur=rose&fournisseur=gemini')
            ->assertOk()->assertHeader('Content-Type', 'audio/wav');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/interactions')
            && $request['generation_config']['speech_config'] === [['voice' => 'Kore']]
            && str_contains($request['input'][0]['content'][0]['text'], 'Teacher Rose')
            && str_contains($request['input'][0]['content'][0]['annotations'][0]['style'], 'female teacher'));

        $this->assertTrue(PrivateStorage::disk()->exists(VoiceSampler::path('Kore', Teachers::find('rose'))));
        // L'extrait de Teacher Joe (autre phrase, autre ton) n'est pas le même fichier.
        $this->assertNotSame(VoiceSampler::path('Kore'), VoiceSampler::path('Kore', Teachers::find('rose')));
    }

    public function test_rose_openai_sample_uses_the_openai_speech_with_her_voice_then_is_kept(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/voix/marin/extrait?professeur=rose&fournisseur=openai')->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $this->actingAs($admin)->get('/admin/voix/marin/extrait?professeur=rose&fournisseur=openai')->assertOk();

        $sent = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/audio/speech'));
        $this->assertCount(1, $sent);
        $this->assertSame('marin', $sent[0][0]['voice']);
        $this->assertStringContainsString('Teacher Rose', $sent[0][0]['input']);
        // La voix des messages vocaux d'OpenAI n'est pas changée par l'essai.
        $this->assertNotSame('marin', config('services.openai.tts_voice'));
    }

    public function test_openai_sample_without_key_says_so(): void
    {
        config(['services.openai.key' => null]);

        $this->actingAs($this->admin())->get('/admin/voix/marin/extrait?professeur=rose&fournisseur=openai')
            ->assertStatus(503)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'OPENAI_API_KEY'));
    }

    public function test_voices_not_proposed_for_a_teacher_are_refused(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/voix/Charon/extrait?professeur=rose')->assertNotFound();
        $this->actingAs($admin)->get('/admin/voix/Kore/extrait')->assertNotFound();
        $this->actingAs($admin)->get('/admin/voix/marin/extrait?professeur=joe&fournisseur=openai')->assertNotFound();
        $this->actingAs($admin)->post('/admin/voix', ['voice' => 'Charon', 'teacher' => 'rose'])->assertStatus(422);
        $this->actingAs(User::factory()->create())->get('/admin/voix?professeur=rose')->assertForbidden();
    }

    public function test_choosing_rose_voices_never_changes_teacher_joe(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/voix', ['voice' => 'Kore', 'teacher' => 'rose', 'provider' => 'gemini'])
            ->assertRedirect('/admin/voix?professeur=rose')
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'pour Teacher Rose'));
        $this->actingAs($admin)->post('/admin/voix', ['voice' => 'shimmer', 'teacher' => 'rose', 'provider' => 'openai'])->assertRedirect('/admin/voix?professeur=rose');

        $rose = Teachers::find('rose');
        $this->assertSame('Kore', $rose->geminiVoice());
        $this->assertSame('shimmer', $rose->realtimeVoice());
        $this->assertSame('Achird', TeacherVoice::current());
        $this->assertSame('Achird', Teachers::default()->geminiVoice());
        $this->assertSame('cedar', Teachers::default()->realtimeVoice());
        $this->assertNull(Setting::get(TeacherVoice::SETTING));

        $this->actingAs($admin)->get('/admin/voix?professeur=rose')->assertSee('Voix actuelle : <strong>Kore</strong>', false)->assertSee('(choisie ici)');
    }

    public function test_usage_shows_lessons_per_teacher(): void
    {
        $learner = User::factory()->create();
        $subscription = Subscription::factory()->for($learner)->create();

        foreach ([null, 'joe', 'rose', 'rose'] as $teacher) {
            $learner->lessons()->create([
                'subscription_id' => $subscription->id, 'date' => today(), 'allowed_seconds' => 900, 'used_seconds' => 600,
                'started_at' => now()->subHour(), 'ended_at' => now(), 'status' => LessonStatus::Completed, 'teacher' => $teacher,
            ]);
        }

        $totals = LiveUsage::byTeacher();
        $this->assertSame(2, $totals['joe']['count']);
        $this->assertSame(1200, $totals['joe']['seconds']);
        $this->assertSame(2, $totals['rose']['count']);

        $this->actingAs($this->admin())->get('/admin/consommation')->assertOk()
            ->assertSee('Séances en direct par professeur')
            ->assertSee('data-teacher-usage="rose"', false)
            ->assertSee('Teacher Rose');
    }

    public function test_the_diagnostic_trial_can_be_done_with_rose_before_she_is_offered(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/diagnostic-texte')->assertOk()->assertSee('Essayer 30 secondes avec Teacher Rose');

        $this->actingAs($admin)->get('/admin/diagnostic-texte/direct/gemini/conversation?professeur=rose')->assertOk()
            ->assertSee('data-teacher-name="Teacher Rose"', false)
            ->assertSee('face-rose', false)
            ->assertSee('connexion?professeur=rose', false);

        $setup = $this->actingAs($admin)->postJson('/admin/diagnostic-texte/direct/gemini/conversation/connexion?professeur=rose')->assertOk()->json('setup');
        $this->assertSame('Sulafat', $setup['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName']);
        $this->assertStringContainsString('Teacher Rose', $setup['systemInstruction']['parts'][0]['text']);

        // Sans paramètre : Teacher Joe, comme avant.
        $joe = $this->actingAs($admin)->postJson('/admin/diagnostic-texte/direct/gemini/conversation/connexion')->assertOk()->json('setup');
        $this->assertSame('Achird', $joe['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName']);
    }

    public function test_reading_a_conversation_shows_the_teacher_of_that_session(): void
    {
        $learner = User::factory()->create(['name' => 'Afi']);
        $subscription = Subscription::factory()->for($learner)->create();
        $lesson = $learner->lessons()->create([
            'subscription_id' => $subscription->id, 'date' => today(), 'allowed_seconds' => 900, 'used_seconds' => 600,
            'started_at' => now()->subHour(), 'ended_at' => now(), 'status' => LessonStatus::Completed, 'teacher' => 'rose',
        ]);
        Transcripts::record($lesson, [['seq' => 1, 'speaker' => 'teacher', 'text' => 'Hello Afi!', 'at' => now()->subMinutes(50)->getTimestampMs()]]);

        $this->actingAs($this->admin())->get(route('admin.conversations.show', [$learner, 'lesson', $lesson->id]))->assertOk()
            ->assertSee('<strong>Teacher Rose</strong>', false);
    }
}
