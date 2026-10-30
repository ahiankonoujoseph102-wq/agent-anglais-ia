<?php

namespace Tests\Unit;

use App\Support\Gemini\LevelMessage;
use App\Support\Gemini\SpeechPreparer;
use App\Support\Gemini\VoiceSampler;
use App\Support\Speech\Loudness;
use App\Support\Speech\SpeechLoudness;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Messages vocaux préparés : même volume pour tous, crête à −1 décibel.
 */
class LoudnessTest extends TestCase
{
    /** WAV PCM 16 bits mono 24 kHz : une sinusoïde de la crête donnée. */
    private function wav(int $peak, int $samples = 24000): string
    {
        $pcm = '';

        for ($i = 0; $i < $samples; $i++) {
            $pcm .= pack('v', (int) round($peak * sin(2 * M_PI * 220 * $i / 24000)) & 0xFFFF);
        }

        return VoiceSampler::toWav($pcm, 'audio/l16;rate=24000');
    }

    public function test_a_quiet_message_is_brought_to_minus_one_decibel(): void
    {
        $quiet = $this->wav(4000);
        $this->assertEqualsWithDelta(-18.3, Loudness::peakDb($quiet), 0.1);

        $normalized = Loudness::normalize($quiet);

        $this->assertSame(strlen($quiet), strlen($normalized), 'même durée, même en-tête');
        $this->assertSame(substr($quiet, 0, 44), substr($normalized, 0, 44));
        $this->assertEqualsWithDelta(-1.0, Loudness::peakDb($normalized), 0.05);
    }

    public function test_a_loud_message_is_lowered_and_never_clipped(): void
    {
        $normalized = Loudness::normalize($this->wav(32767));

        $this->assertEqualsWithDelta(-1.0, Loudness::peakDb($normalized), 0.05);
    }

    public function test_a_message_already_at_the_right_level_is_left_untouched(): void
    {
        $once = Loudness::normalize($this->wav(9000));

        $this->assertSame($once, Loudness::normalize($once));
    }

    public function test_silence_and_unreadable_audio_are_left_untouched(): void
    {
        $silence = $this->wav(0);

        $this->assertSame($silence, Loudness::normalize($silence));
        $this->assertSame('pas un fichier audio', Loudness::normalize('pas un fichier audio'));
        $this->assertNull(Loudness::peakDb('RIFF'));
    }

    public function test_every_stored_message_is_brought_to_the_loudness_of_the_videos_and_old_files_once(): void
    {
        Storage::fake('local');
        config(['filesystems.private_disk' => 'local']);

        // Message gardé : −14 LUFS (comme les vidéos), crête jamais au-dessus de −1 décibel.
        VoiceSampler::store('level-messages/A1-essai.wav', $this->wav(3000), 'Essai');
        $stored = Storage::disk('local')->get('level-messages/A1-essai.wav');
        $this->assertEqualsWithDelta(-14.0, SpeechLoudness::lufs($stored), 0.5);
        $this->assertLessThanOrEqual(-0.95, Loudness::peakDb($stored));

        // Message très fort : ramené à −14 LUFS, crêtes limitées.
        $loud = SpeechLoudness::normalize($this->wav(32767));
        $this->assertEqualsWithDelta(-14.0, SpeechLoudness::lufs($loud), 0.5);
        $this->assertLessThanOrEqual(-0.95, Loudness::peakDb($loud));

        // Fichier gardé avant : harmonisé une seule fois.
        Storage::disk('local')->put(LevelMessage::DIRECTORY.'/A2-ancien.wav', $this->wav(3000));
        $this->assertSame(1, SpeechPreparer::normalizeStoredFiles());
        $this->assertEqualsWithDelta(-14.0, SpeechLoudness::lufs(Storage::disk('local')->get(LevelMessage::DIRECTORY.'/A2-ancien.wav')), 0.5);
        $this->assertSame(0, SpeechPreparer::normalizeStoredFiles());
    }

    public function test_the_loudness_measure_follows_the_standard(): void
    {
        // Sinusoïde de 1 kilohertz à −20 dBFS : −23 LUFS environ selon BS.1770 (−20 − 3,01 + 0,69 − 0,69).
        $pcm = '';

        for ($i = 0; $i < 48000; $i++) {
            $pcm .= pack('v', (int) round(0.1 * 32767 * sin(2 * M_PI * 1000 * $i / 24000)) & 0xFFFF);
        }

        $this->assertEqualsWithDelta(-23.0, SpeechLoudness::lufs(VoiceSampler::toWav($pcm, 'audio/l16;rate=24000')), 0.3);
    }
}
