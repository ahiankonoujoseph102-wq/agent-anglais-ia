<?php

namespace Tests\Feature\Teachers;

use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\SiteGuide;
use App\Support\Gemini\TeacherSetup;
use App\Support\Live\LiveVoiceSample;
use App\Support\Live\OpenAiRealtime;
use App\Support\Teachers\Teacher;
use App\Support\Teachers\Teachers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Teacher Rose enseigne EXACTEMENT comme Teacher Joe (30 octobre 2026, « elle parle trop vite, elle écoute moins ») :
 * dans toutes les conversations, ses consignes sont celles de Teacher Joe au caractère près, une fois le prénom, le
 * féminin et son court bloc de personnalité retirés ; même débit, même silence avant de répondre, même vitesse chez
 * OpenAI. Seules changent la voix et le visage. Débit de chaque voix mesuré par « Écouter en séance ».
 */
class RoseTeachesLikeJoeTest extends TestCase
{
    use RefreshDatabase;

    private function learner(?Level $level): User
    {
        $user = new User(['name' => 'Afi']);
        $user->level = $level;

        return $user;
    }

    /**
     * @return array<string, array{0: callable(Teacher): array<string, mixed>, 1: string, 2: User}>
     */
    private function conversations(): array
    {
        $beginner = $this->learner(null);
        $a1 = $this->learner(Level::A1);
        $a2 = $this->learner(Level::A2);
        $b1 = $this->learner(Level::B1);

        return [
            'évaluation' => [fn (Teacher $t) => TeacherSetup::assessment($beginner, 5, null, $t), 'assessment', $beginner],
            'séance du jour A1' => [fn (Teacher $t) => TeacherSetup::lesson($a1, 15, null, "LESSON OF THE DAY\nUnit 1.", 1, $t), 'lesson', $a1],
            'séance du jour B1' => [fn (Teacher $t) => TeacherSetup::lesson($b1, 30, null, "LESSON OF THE DAY\nUnit 4.", 5, $t), 'lesson', $b1],
            'Mes cours' => [fn (Teacher $t) => TeacherSetup::course($a2, 10, 'My lesson: the present simple.', null, 1, $t), 'course', $a2],
            'bilan de niveau' => [fn (Teacher $t) => TeacherSetup::levelTest($b1, Level::B1, 10, null, $t), 'level_test', $b1],
        ];
    }

    /** Texte de Teacher Rose ramené à celui de Teacher Joe : prénom, féminin et bloc de personnalité retirés. */
    private static function asJoe(string $text, Teacher $rose): string
    {
        $text = str_replace(self::personality($rose)."\n\n", '', $text);
        $text = strtr($text, array_flip($rose->siteGuideTexts()));

        return strtr($text, [$rose->name() => Teachers::default()->name(), '« une professeure virtuelle »' => '« un professeur virtuel »']);
    }

    private static function personality(Teacher $rose): string
    {
        return str_replace(':teacher', $rose->name(), $rose->personality());
    }

    public function test_her_instructions_are_teacher_joes_word_for_word_in_every_conversation(): void
    {
        $joe = Teachers::default();
        $rose = Teachers::find('rose');

        foreach ($this->conversations() as $name => [$build, $kind, $user]) {
            $joeSetup = $build($joe);
            $roseSetup = $build($rose);
            $joeText = $joeSetup['systemInstruction']['parts'][0]['text'];
            $roseText = $roseSetup['systemInstruction']['parts'][0]['text'];

            $this->assertStringContainsString(self::personality($rose), $roseText, $name);
            $this->assertSame($joeText, self::asJoe($roseText, $rose), "Consignes différentes : {$name}");

            // Même détection de la parole (silence avant de répondre), même débit chez OpenAI.
            $this->assertSame($joeSetup['realtimeInputConfig'], $roseSetup['realtimeInputConfig'], $name);
            $pace = TeacherSetup::paceLevel($kind, $user);
            $this->assertSame(
                OpenAiRealtime::session($joeSetup, $pace, $joe)['audio']['output']['speed'] ?? null,
                OpenAiRealtime::session($roseSetup, $pace, $rose)['audio']['output']['speed'] ?? null,
                $name,
            );
        }
    }

    public function test_her_personality_asks_for_teacher_joes_pace_and_never_more_talk(): void
    {
        $personality = Teachers::find('rose')->personality();

        $this->assertStringContainsString('EXACTLY with the method, the pace, the patience and the listening', $personality);
        $this->assertStringContainsString('follow the PACE rules strictly', $personality);
        $this->assertStringNotContainsString('humour', $personality);
        $this->assertSame('', Teachers::default()->personality());
    }

    public function test_the_site_guide_speaks_of_her_in_the_feminine_and_every_sentence_still_exists(): void
    {
        $rose = Teachers::find('rose');
        $joeGuide = SiteGuide::text(null, Teachers::default());
        $roseGuide = SiteGuide::text(null, $rose);

        foreach ($rose->siteGuideTexts() as $masculine => $feminine) {
            $this->assertStringContainsString($masculine, $joeGuide, 'Phrase introuvable dans la fiche du site : '.$masculine);
            $this->assertStringContainsString($feminine, $roseGuide);
        }

        $this->assertStringNotContainsString('Il explique en français simple', $roseGuide);
    }

    public function test_slower_voices_are_offered_in_session_and_the_default_voice_does_not_change(): void
    {
        $rose = Teachers::find('rose');

        $this->assertArrayHasKey('Achernar', $rose->geminiTrials());
        $this->assertArrayHasKey('Gacrux', $rose->geminiTrials());
        $this->assertSame('Sulafat', $rose->geminiVoice());
    }

    public function test_speech_duration_ignores_the_silence_around_the_sentence(): void
    {
        // 0,5 seconde de silence, 2 secondes de voix, 1 seconde de silence, à 24 kHz.
        $data = str_repeat("\x00\x00", 12000).str_repeat("\x00\x10", 48000).str_repeat("\x00\x00", 24000);
        $wav = 'RIFF'.pack('V', 36 + strlen($data)).'WAVE'.'fmt '.pack('VvvVVvv', 16, 1, 1, 24000, 48000, 2, 16).'data'.pack('V', strlen($data)).$data;

        $this->assertEqualsWithDelta(2.0, LiveVoiceSample::speechSeconds($wav), 0.03);
        $this->assertNull(LiveVoiceSample::speechSeconds('<html>'));
    }

    public function test_listening_in_session_measures_the_pace_and_shows_it_next_to_teacher_joe(): void
    {
        Storage::fake('local');
        config([
            'filesystems.private_disk' => 'local',
            'services.gemini.key' => 'cle-principale',
            'services.gemini.voice' => 'Achird',
        ]);
        Http::fake(['*/auth_tokens' => fn (Request $request) => Http::response(['name' => 'auth_tokens/jeton'])]);
        $admin = User::factory()->admin()->create();

        $data = str_repeat("\x00\x00", 2400).str_repeat("\x00\x10", 120000).str_repeat("\x00\x00", 2400);
        $wav = 'RIFF'.pack('V', 36 + strlen($data)).'WAVE'.'fmt '.pack('VvvVVvv', 16, 1, 1, 24000, 48000, 2, 16).'data'.pack('V', strlen($data)).$data;

        foreach ([['joe', 'Achird'], ['rose', 'Achernar']] as [$teacher, $voice]) {
            $start = $this->actingAs($admin)->postJson(route('admin.voices.live', ['voice' => $voice, 'professeur' => $teacher, 'fournisseur' => 'gemini']))->assertOk();
            $this->actingAs($admin)->post($start->json('store_url'), ['audio' => UploadedFile::fake()->createWithContent('extrait.wav', $wav)])
                ->assertOk()
                ->assertJsonPath('pace.seconds', 5);
        }

        $pace = LiveVoiceSample::pace('gemini', 'Achernar', Teachers::find('rose'));
        $this->assertSame(5, (int) $pace['seconds']);
        $this->assertGreaterThan(1, $pace['words']);

        $this->actingAs($admin)->get(route('admin.voices.index', ['professeur' => 'rose', 'fournisseur' => 'gemini']))->assertOk()
            ->assertSee('Débit en séance : '.$pace['words'].' mots en 5,0 secondes')
            ->assertSee('Repère, Teacher Joe (Achird) :');
    }
}
