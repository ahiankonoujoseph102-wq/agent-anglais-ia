<?php

namespace Tests\Feature\Videos;

use App\Models\LessonVideo;
use App\Support\Avatar;
use App\Support\Speech\SpeechProviders;
use App\Support\Teachers\Teachers;
use App\Support\Videos\ClubScriptWriter;
use App\Support\Videos\LessonVideos;
use App\Support\Videos\VideoTeachers;
use App\Support\Videos\VideoVoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Vidéos en alternance (étape 3 de « Teacher Rose partout ») : Teacher Joe un jour, Teacher Rose le lendemain, pour
 * les vidéos de promotion ET la vidéo du Club ; même nombre de vidéos (aucune minute de GitHub en plus). Chaque vidéo
 * garde son professeur : modèle 3D, cadrage, voix, ton, filigrane, nom du fichier, signature. Teacher Joe : tout
 * comme avant.
 */
class VideoTeachersTest extends TestCase
{
    use RefreshDatabase;

    private function video(string $day, string $kind = LessonVideo::PROMO): LessonVideo
    {
        Carbon::setTestNow($day.' 08:00:00');
        app(LessonVideos::class)->plan(today(), 1);

        return LessonVideo::query()->latest('id')->first();
    }

    public function test_one_day_each_for_promotion_and_club_videos(): void
    {
        $this->assertSame('joe', VideoTeachers::forDay(Carbon::parse('2026-10-30'))->key);
        $this->assertSame('rose', VideoTeachers::forDay(Carbon::parse('2026-10-31'))->key);
        $this->assertSame('joe', VideoTeachers::forDay(Carbon::parse('2026-11-01'))->key);
        $this->assertSame('rose', VideoTeachers::forDay(Carbon::parse('2026-10-29'))->key);

        $this->assertSame('joe', $this->video('2026-10-30')->teacher()->key);
        $this->assertSame('rose', $this->video('2026-10-31')->teacher()->key);

        // Retour à Teacher Joe seul : lui chaque jour.
        config(['teachers.list.rose.ready' => false]);
        $this->assertSame('joe', VideoTeachers::forDay(Carbon::parse('2026-10-31'))->key);
    }

    public function test_the_rendering_job_uses_the_teacher_model_and_watermark(): void
    {
        $rose = app(LessonVideos::class)->job($this->video('2026-10-31'));

        $this->assertSame('rose', $rose['teacher']);
        $this->assertSame('/teacher3d/models/teacher-rose.glb', $rose['avatar']['url']);
        $this->assertFileExists(public_path('teacher3d/models/teacher-rose.glb'));
        $this->assertSame(config('avatar.rose.voice_hz'), $rose['avatar']['voice_hz']);
        $this->assertSame('Teacher Rose', $rose['render']['watermark']);

        // Teacher Joe : le même modèle et les mêmes réglages qu'avant.
        $joe = app(LessonVideos::class)->job($this->video('2026-10-30'));
        $before = array_intersect_key(Avatar::clientConfig()['model'], array_flip(['profile', 'camera', 'desk_height', 'resting_smile', 'voice_hz']));
        $this->assertSame(['url' => '/teacher3d/models/teacher-joe.glb'] + $before, $joe['avatar']);
        $this->assertSame('Teacher Joe', $joe['render']['watermark']);
        $this->assertStringStartsWith('teacher-joe-2026-10-30-', $this->video('2026-10-30')->downloadName());
        $this->assertStringStartsWith('teacher-rose-2026-10-31-', $this->video('2026-10-31')->downloadName());
    }

    public function test_teacher_rose_keeps_her_own_voice_and_a_feminine_tone(): void
    {
        config(['services.gemini.key' => 'cle-gemini', 'services.openai.key' => 'cle-openai', 'services.gemini.voice' => 'Achird', 'services.openai.tts_voice' => 'cedar']);
        $rose = Teachers::find('rose');

        $this->assertSame('Sulafat', VideoVoice::voiceFor(SpeechProviders::find('gemini'), $rose));
        $this->assertSame('marin', VideoVoice::voiceFor(SpeechProviders::find('openai'), $rose));
        $this->assertSame('Achird', VideoVoice::voiceFor(SpeechProviders::find('gemini')));
        $this->assertSame('cedar', VideoVoice::voiceFor(SpeechProviders::find('openai')));

        $style = $this->video('2026-10-31')->voiceStyle();
        $this->assertStringContainsString('une professeure d\'anglais chaleureuse et patiente', $style);
        $this->assertSame(config('videos.voice.style'), $this->video('2026-10-30')->voiceStyle());
    }

    public function test_scripts_are_written_for_her_and_signed_by_her(): void
    {
        $rose = Teachers::find('rose');
        $instructions = VideoTeachers::instructions('Teacher speaks to BEGINNERS he follows personally. He speaks as himself.', $rose);

        $this->assertStringContainsString('she follows personally. She speaks as herself.', $instructions);
        $this->assertStringContainsString('THE TEACHER IS A WOMAN', $instructions);
        $this->assertSame('He speaks as himself.', VideoTeachers::instructions('He speaks as himself.', Teachers::default()));

        $this->assertStringEndsWith('— Teacher Rose', ClubScriptWriter::publication('Voici la vidéo du jour.', $rose));
        $this->assertStringEndsWith('— Teacher Joe', ClubScriptWriter::publication('Voici la vidéo du jour.'));
    }
}
