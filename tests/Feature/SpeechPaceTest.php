<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Support\Gemini\LevelMessage;
use App\Support\Gemini\WelcomeMessage;
use App\Support\Speech\GeminiSpeech;
use App\Support\Speech\OpenAiSpeech;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Messages vocaux (bienvenue, résultat de l'évaluation) : ton calme, jamais rapide ; chez OpenAI, en plus, le
 * réglage de vitesse de la configuration. Un réglage modifié fait préparer de nouveaux fichiers.
 */
class SpeechPaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['filesystems.private_disk' => 'local', 'services.openai.key' => 'cle-openai-de-test', 'services.gemini.key' => 'cle-gemini-de-test']);
    }

    public function test_openai_messages_are_said_calmly_with_the_configured_speed(): void
    {
        Http::fake(['*/audio/speech' => Http::response(str_repeat("\0\0", 2400), 200, ['Content-Type' => 'audio/pcm'])]);

        (new OpenAiSpeech)->synthesize('Bonjour Ama !', (string) config('teacher.welcome.style'), 'Essai');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/audio/speech')
            && $request['speed'] === 0.95
            && str_contains($request['instructions'], 'speaking French clearly and calmly, never fast')
            && str_contains($request['instructions'], 'the few English words are pronounced slowly and clearly'));
    }

    public function test_the_speed_is_bounded_and_can_be_switched_off(): void
    {
        config(['services.openai.tts_speed' => 9]);
        $this->assertSame(4.0, (new OpenAiSpeech)->speed());
        config(['services.openai.tts_speed' => 0.1]);
        $this->assertSame(0.25, (new OpenAiSpeech)->speed());
        config(['services.openai.tts_speed' => null]);
        $this->assertNull((new OpenAiSpeech)->speed());
        $this->assertSame('', (new OpenAiSpeech)->settings());
    }

    public function test_both_message_styles_are_calm_and_never_fast(): void
    {
        foreach (['teacher.welcome.style', 'teacher.level_messages.style'] as $key) {
            $this->assertStringContainsString('clearly and calmly, never fast', (string) config($key), $key);
            $this->assertStringNotContainsString('natural pace', (string) config($key), $key);
        }
    }

    public function test_a_new_openai_speed_prepares_new_files_and_never_changes_gemini_files(): void
    {
        $openai = new OpenAiSpeech;
        $gemini = app(GeminiSpeech::class);

        $welcome = WelcomeMessage::path('Bonjour', $openai);
        $level = LevelMessage::path(Level::A2, $openai);
        $geminiWelcome = WelcomeMessage::path('Bonjour', $gemini);
        $geminiLevel = LevelMessage::path(Level::A2, $gemini);

        config(['services.openai.tts_speed' => 0.9]);

        $this->assertNotSame($welcome, WelcomeMessage::path('Bonjour', $openai));
        $this->assertNotSame($level, LevelMessage::path(Level::A2, $openai));
        $this->assertSame($geminiWelcome, WelcomeMessage::path('Bonjour', $gemini));
        $this->assertSame($geminiLevel, LevelMessage::path(Level::A2, $gemini));
        $this->assertSame('', $gemini->settings());
    }
}
