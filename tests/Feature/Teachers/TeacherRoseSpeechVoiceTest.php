<?php

namespace Tests\Feature\Teachers;

use App\Models\User;
use App\Support\Gemini\VoiceSampler;
use App\Support\Speech\SpeechProviders;
use App\Support\Teachers\Teachers;
use App\Support\Videos\VideoSettings;
use App\Support\Videos\VideoVoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Voix des vidéos et des messages de Teacher Rose : test en français et en anglais de « Essai des voix » (toutes les
 * voix féminines de Gemini et d'OpenAI), choix à part de sa voix de séance, consigne de prononciation française
 * seulement après ce choix. Teacher Joe ne change pas.
 */
class TeacherRoseSpeechVoiceTest extends TestCase
{
    use RefreshDatabase;

    private const MAIN = 'cle-principale';

    private const SECOND = 'cle-deuxieme';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'filesystems.private_disk' => 'local',
            'services.gemini.key' => self::MAIN,
            'services.gemini.key_2' => self::SECOND,
            'services.openai.key' => 'cle-openai',
            'services.tts.providers' => ['gemini', 'openai'],
            'videos.voice.providers' => ['gemini', 'openai'],
        ]);
        VideoSettings::saveSite('talkami.net');

        Http::fake([
            '*/interactions' => Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'audio', 'mime_type' => 'audio/l16;codec=pcm;rate=24000', 'data' => base64_encode(str_repeat("\x10\x00", 2400))]]]]]),
            '*/audio/speech' => Http::response(str_repeat("\x10\x00", 2400), 200, ['Content-Type' => 'audio/pcm']),
        ]);
    }

    public function test_the_bilingual_phrase_says_plans_prices_and_the_site_in_words(): void
    {
        $text = VoiceSampler::bilingualScript(Teachers::find('rose'))['text'];

        $this->assertStringContainsString('Il y a trois formules : Semaine, Essentiel et Intensif.', $text);
        $this->assertStringContainsString('sept mille cinq cents francs CFA', $text);
        $this->assertStringContainsString('Votre accès a expiré', $text);
        $this->assertStringContainsString('talkami point net', $text);
        $this->assertStringContainsString('How much is it?', $text);
        $this->assertDoesNotMatchRegularExpression('/\d/', $text, 'Aucun chiffre écrit : tout est dit en lettres.');
    }

    public function test_every_female_voice_is_proposed_for_teacher_rose_but_nothing_for_teacher_joe(): void
    {
        $admin = User::factory()->admin()->create();

        $page = $this->actingAs($admin)->get(route('admin.voices.index', ['professeur' => 'rose']))->assertOk();
        $page->assertSee('Voix des vidéos et des messages : test en français et en anglais');

        foreach (['Sulafat', 'Kore', 'Zephyr', 'Leda', 'Aoede', 'Callirrhoe', 'Autonoe', 'Despina', 'Erinome', 'Laomedeia', 'Achernar', 'Gacrux', 'Pulcherrima', 'Vindemiatrix'] as $voice) {
            $page->assertSee(route('admin.voices.speech.sample', ['provider' => 'gemini', 'voice' => $voice, 'professeur' => 'rose']), false);
        }

        foreach (['marin', 'coral', 'shimmer', 'sage', 'nova', 'alloy'] as $voice) {
            $page->assertSee(route('admin.voices.speech.sample', ['provider' => 'openai', 'voice' => $voice, 'professeur' => 'rose']), false);
        }

        $this->actingAs($admin)->get(route('admin.voices.index'))->assertOk()->assertDontSee('test en français et en anglais');
    }

    public function test_a_gemini_sample_uses_the_second_key_first_and_is_kept(): void
    {
        $admin = User::factory()->admin()->create();
        $url = route('admin.voices.speech.sample', ['provider' => 'gemini', 'voice' => 'Kore', 'professeur' => 'rose']);

        $this->actingAs($admin)->get($url)->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $this->actingAs($admin)->get($url)->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->header('x-goog-api-key')[0] === self::SECOND
            && str_contains($request->body(), 'trois formules'));
    }

    public function test_preparing_makes_every_missing_sample(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.voices.speech.prepare'), ['teacher' => 'rose'])
            ->assertRedirect(route('admin.voices.index', ['professeur' => 'rose']).'#voix-francais');

        $rose = Teachers::find('rose');
        $sampler = app(VoiceSampler::class);

        foreach ($rose->speechTrials() as $provider => $voices) {
            foreach (array_keys($voices) as $voice) {
                $this->assertNotNull($sampler->cachedBilingual($provider, $voice, $rose), "{$provider} {$voice}");
            }
        }
    }

    public function test_choosing_changes_her_videos_and_messages_but_not_her_session_voice(): void
    {
        $admin = User::factory()->admin()->create();
        $rose = Teachers::find('rose');
        $styleBefore = $rose->speechStyle('Lis calmement.');

        $this->assertSame('Lis calmement.', $styleBefore, 'Rien de choisi : ton inchangé, aucun fichier refait.');

        $this->actingAs($admin)->post(route('admin.voices.speech.choose'), ['teacher' => 'rose', 'provider' => 'openai', 'voice' => 'coral'])
            ->assertRedirect();

        $rose = Teachers::find('rose');
        $this->assertSame(['provider' => 'openai', 'voice' => 'coral'], $rose->speechVoice());
        $this->assertSame('Sulafat', $rose->geminiVoice(), 'Voix de séance inchangée.');
        $this->assertSame('openai', SpeechProviders::configured($rose)[0]->name());
        $this->assertSame('coral', SpeechProviders::find('openai', $rose)->voice());
        $this->assertSame('openai', VideoVoice::providers($rose)[0]->name());
        $this->assertSame('coral', VideoVoice::voiceFor(SpeechProviders::find('openai'), $rose));
        $this->assertStringContainsString('prononciation française standard', $rose->speechStyle('Lis calmement.'));

        // Teacher Joe : rien ne change.
        $joe = Teachers::default();
        $this->assertNull($joe->speechVoice());
        $this->assertSame('gemini', SpeechProviders::configured($joe)[0]->name());
        $this->assertSame('Lis calmement.', $joe->speechStyle('Lis calmement.'));
    }

    public function test_a_voice_that_is_not_proposed_cannot_be_chosen(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.voices.speech.choose'), ['teacher' => 'rose', 'provider' => 'gemini', 'voice' => 'Achird'])->assertStatus(422);
        $this->actingAs($admin)->post(route('admin.voices.speech.choose'), ['teacher' => 'joe', 'provider' => 'gemini', 'voice' => 'Kore'])->assertNotFound();
        $this->assertNull(Teachers::find('rose')->speechVoice());
    }
}
