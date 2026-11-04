<?php

namespace Tests\Feature\Live;

use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\TeacherSetup;
use App\Support\Live\LiveVoiceSample;
use App\Support\Teachers\Teachers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Voix des séances (demande du fondateur, 4 novembre 2026) : français de France bien articulé, jamais un accent
 * anglais, petite pause au changement de langue, ton naturel ; « Écouter en séance » fait entendre le français ET
 * l'anglais avec la vraie voix des séances.
 */
class SpeakingVoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_conversation_gets_the_speaking_voice_rule(): void
    {
        $user = User::factory()->level(Level::A1)->create(['name' => 'Afi']);

        foreach (['assessment', 'lesson', 'course', 'level_test'] as $kind) {
            $text = TeacherSetup::instructions($kind, $user, 10);
            $this->assertStringContainsString('YOUR VOICE: French like a native speaker from France', $text, $kind);
            $this->assertStringContainsString('never an English accent', $text, $kind);
            $this->assertStringContainsString('a tiny pause when you switch language', $text, $kind);
            $this->assertStringNotContainsString(':speaking_voice', $text, $kind);
        }

        $this->assertStringContainsString('never robotic or sing-song', (string) config('teacher.pace.rules'));
    }

    public function test_listening_in_session_says_french_then_english_with_the_same_rule(): void
    {
        foreach (Teachers::all() as $teacher) {
            $phrase = LiveVoiceSample::phrase($teacher);
            $this->assertStringContainsString('Bonjour ! Je suis '.$teacher->name(), $phrase);
            $this->assertStringContainsString('Comment dit-on « C\'est combien ? » en anglais ?', $phrase);
            $this->assertStringContainsString('How much is it?', $phrase);

            $instructions = LiveVoiceSample::instructions($teacher);
            $this->assertStringContainsString((string) config('teacher.speaking_voice'), $instructions);
            $this->assertStringContainsString('American pronunciation', $instructions);
            $this->assertStringContainsString($phrase, $instructions);
        }
    }
}
