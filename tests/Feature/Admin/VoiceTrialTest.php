<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Support\Gemini\TeacherSetup;
use App\Support\Gemini\TeacherVoice;
use App\Support\Gemini\VoiceSampler;
use App\Support\PrivateStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Page « Essai des voix » : extraits simulés (aucun appel réel à Gemini), cache, limite, choix.
 */
class VoiceTrialTest extends TestCase
{
    use RefreshDatabase;

    private const WAV = "RIFF\x24\x00\x00\x00WAVEfmt fausse-voix";

    /** Réponse simulée de Gemini (null = extrait délivré). */
    private mixed $geminiResponse = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur']);
        Http::fake(['*/interactions' => fn () => $this->geminiResponse ?? Http::response(['steps' => [
            ['type' => 'user_input'],
            ['type' => 'model_output', 'content' => [['type' => 'audio', 'data' => base64_encode(self::WAV)]]],
        ]])]);
    }

    public function test_page_lists_five_male_voices_with_their_official_description(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/voix')
            ->assertOk()
            ->assertSee('Essai des voix')
            ->assertSee('Charon')->assertSee('« Informative »', false)
            ->assertSee('Orus')->assertSee('Alnilam')->assertSee('« Firm »', false)
            ->assertSee('Algenib')->assertSee('« Gravelly »', false)
            ->assertSee('Rasalgethi')
            ->assertSee('Écouter')
            ->assertSee('Choisir cette voix')
            ->assertSee('Voix actuelle : <strong>Achird</strong>', false);

        // Que des voix masculines (liste de Google Cloud, Chirp 3 HD).
        $male = ['Achird', 'Algenib', 'Algieba', 'Alnilam', 'Charon', 'Enceladus', 'Fenrir', 'Iapetus', 'Orus', 'Puck', 'Rasalgethi', 'Sadachbia', 'Sadaltager', 'Schedar', 'Umbriel', 'Zubenelgenubi'];
        $this->assertEmpty(array_diff(array_keys(TeacherVoice::trials()), $male));
        $this->assertGreaterThanOrEqual(5, count(TeacherVoice::trials()));
    }

    public function test_page_and_samples_are_for_administrators_only(): void
    {
        $learner = User::factory()->create();

        $this->actingAs($learner)->get('/admin/voix')->assertForbidden();
        $this->actingAs($learner)->get('/admin/voix/Charon/extrait')->assertForbidden();
        $this->actingAs($learner)->post('/admin/voix', ['voice' => 'Charon'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_sample_is_generated_once_with_the_tts_model_then_kept(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/voix/Charon/extrait')
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/wav');

        $this->actingAs($admin)->get('/admin/voix/Charon/extrait')->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/interactions'
            && $request['model'] === 'gemini-3.8-flash-tts'
            && $request['generation_config']['speech_config'] === [['voice' => 'Charon']]
            && $request['response_format'] === ['type' => 'audio']
            && str_contains($request['input'][0]['content'][0]['text'], config('avatar.model.name'))
            && $request['input'][0]['content'][0]['annotations'][0]['type'] === 'speech_metadata');

        $this->assertSame(self::WAV, PrivateStorage::disk()->get(VoiceSampler::path('Charon')));
    }

    public function test_new_samples_are_limited_per_hour(): void
    {
        config(['services.gemini.voice_trial_per_hour' => 2]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/voix/Charon/extrait')->assertOk();
        $this->actingAs($admin)->get('/admin/voix/Orus/extrait')->assertOk();
        $this->actingAs($admin)->get('/admin/voix/Alnilam/extrait')
            ->assertStatus(429)
            ->assertJsonPath('message', fn ($message) => str_starts_with($message, 'Trop d\'essais pour cette heure.'));

        // Une voix déjà écoutée reste disponible.
        $this->actingAs($admin)->get('/admin/voix/Charon/extrait')->assertOk();
        Http::assertSentCount(2);
    }

    public function test_unknown_voice_and_gemini_failure(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/voix/Kore/extrait')->assertNotFound();

        $this->geminiResponse = Http::response(['error' => ['status' => 'INTERNAL']], 500);
        $this->actingAs($admin)->get('/admin/voix/Orus/extrait')
            ->assertStatus(503)
            ->assertJsonPath('message', 'L\'extrait n\'a pas pu être créé. Réessayez dans quelques instants. Cause : Demande refusée par le service (code 500).');

        // Clé Gemini absente : le message nomme la bonne variable.
        config(['services.gemini.key' => null]);
        $this->actingAs($admin)->get('/admin/voix/Charon/extrait')
            ->assertStatus(503)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'GEMINI_API_KEY') && ! str_contains($message, 'OPENAI'));
    }

    public function test_chosen_voice_is_saved_and_locked_in_every_new_token(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/voix', ['voice' => 'Algenib'])
            ->assertRedirect('/admin/voix')
            ->assertSessionHas('status');
        $this->assertSame('Algenib', Setting::get(TeacherVoice::SETTING));

        $setup = TeacherSetup::assessment(new User(['name' => 'Afi']), 5);
        $this->assertSame('Algenib', $setup['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName']);

        $this->actingAs($admin)->post('/admin/voix', ['voice' => 'Kore'])->assertStatus(422);
        $this->assertSame('Algenib', TeacherVoice::current());
    }

    public function test_default_voice_comes_from_the_environment_when_nothing_is_chosen(): void
    {
        config(['services.gemini.voice' => 'Iapetus']);
        $this->assertSame('Iapetus', TeacherVoice::current());

        // Un réglage invalide en base est ignoré.
        Setting::put(TeacherVoice::SETTING, 'Inconnue');
        $this->assertSame('Iapetus', TeacherVoice::current());
    }
}
