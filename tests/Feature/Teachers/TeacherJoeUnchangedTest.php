<?php

namespace Tests\Feature\Teachers;

use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\SiteGuide;
use App\Support\Gemini\TeacherSetup;
use App\Support\Gemini\TeacherVoice;
use App\Support\Live\AssessmentVersion;
use App\Support\Live\OpenAiRealtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Garde-fou de la deuxième professeure (Teacher Rose) : tout ce que reçoit Teacher Joe dans une conversation
 * (consignes, voix, configuration Gemini Live et OpenAI Realtime, fiche du site) reste IDENTIQUE au caractère
 * près à ce qu'il recevait avant l'arrivée des professeurs. Chaque cas est comparé par son empreinte SHA-256
 * (tests/fixtures/teacher-joe-setup.json), enregistrée avec le code de main d'avant le socle des professeurs.
 *
 * Changer VOLONTAIREMENT les consignes de Teacher Joe (config/teacher.php, site-guide.md…) change ces empreintes :
 * relancer alors ce test avec TEACHER_SNAPSHOT_UPDATE=1 pour les réenregistrer, et le dire dans la pull request.
 */
class TeacherJoeUnchangedTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE = 'fixtures/teacher-joe-setup.json';

    public function test_teacher_joe_receives_exactly_the_same_configuration(): void
    {
        $cases = $this->cases();
        $path = base_path('tests/'.self::FIXTURE);

        if (getenv('TEACHER_SNAPSHOT_UPDATE')) {
            file_put_contents($path, json_encode($cases, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
            $this->markTestSkipped('Empreintes de Teacher Joe réenregistrées.');
        }

        $expected = json_decode((string) file_get_contents($path), true);

        $this->assertSame(array_keys($expected), array_keys($cases), 'Liste des cas différente.');

        foreach ($expected as $name => $fingerprint) {
            $this->assertSame($fingerprint, $cases[$name], "Teacher Joe a changé : {$name}");
        }
    }

    /**
     * @return array<string, array{length: int, sha256: string}>
     */
    private function cases(): array
    {
        Carbon::setTestNow('2026-10-29 10:00:00');
        // Tout ce qui dépend de la machine est fixé : l'empreinte est la même en local et dans la CI.
        config([
            'app.url' => 'https://talkami.net',
            'services.gemini.voice' => 'Achird',
            'services.openai.realtime_voice' => 'cedar',
            'services.openai.realtime_input_transcription' => true,
            'services.openai.realtime_reasoning' => 'minimal',
        ]);
        URL::forceRootUrl('https://talkami.net');

        $beginner = $this->learner('Kossi', null);
        $a1 = $this->learner('Afi', Level::A1);
        $a2 = $this->learner('Yawa', Level::A2);
        $b1 = $this->learner('Komi', Level::B1);
        $b2 = $this->learner('Abla', Level::B2);
        $context = "LESSON OF THE DAY\nUnit 1, lesson 1: greetings at the Grand Market.\nBOARD\n- Hello, how are you?";

        $cases = [];

        $cases['assessment.old'] = AssessmentVersion::forcing(false, fn () => $this->live('assessment', $beginner, TeacherSetup::assessment($beginner, 5)));
        $cases['assessment.new'] = AssessmentVersion::forcing(true, fn () => $this->live('assessment', $beginner, TeacherSetup::assessment($beginner, 5, 'handle-1')));
        $cases['lesson.A1.first'] = $this->live('lesson', $a1, TeacherSetup::lesson($a1, 15, null, $context, 1));
        $cases['lesson.B2.later'] = $this->live('lesson', $b2, TeacherSetup::lesson($b2, 30, 'handle-2', $context, 4));
        $cases['lesson.without_programme'] = $this->live('lesson', $b1, TeacherSetup::lesson($b1, 12));
        $cases['course.A2'] = $this->live('course', $a2, TeacherSetup::course($a2, 10, "My English lesson: the present simple.\nExercise 1: complete the sentences.", null, 1));
        $cases['level_test.B1'] = $this->live('level_test', $b1, TeacherSetup::levelTest($b1, Level::B1, 10));
        $cases['trial'] = $this->live('trial', $beginner, TeacherSetup::trial($beginner));

        // Voix choisie par l'administrateur (page « Essai des voix »).
        TeacherVoice::choose('Charon');
        $cases['lesson.A1.chosen_voice'] = $this->live('lesson', $a1, TeacherSetup::lesson($a1, 15, null, $context, 2));

        foreach ([null, 'assessment', 'lesson', 'course', 'level_test', 'trial'] as $kind) {
            $cases['site_guide.'.($kind ?? 'all')] = $this->fingerprint(SiteGuide::text($kind));
        }

        $cases['cue.lesson_start'] = $this->fingerprint(TeacherSetup::cue('lesson_start', ['minutes' => 15]));

        return $cases;
    }

    private function learner(string $name, ?Level $level): User
    {
        $user = new User(['name' => $name]);
        $user->level = $level;

        return $user;
    }

    /**
     * Configuration Gemini Live et session OpenAI Realtime d'une conversation.
     *
     * @param  array<string, mixed>  $setup
     * @return array{length: int, sha256: string}
     */
    private function live(string $kind, User $user, array $setup): array
    {
        $openai = OpenAiRealtime::session($setup, TeacherSetup::paceLevel($kind, $user));

        return $this->fingerprint(json_encode(['gemini' => $setup, 'openai' => $openai], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array{length: int, sha256: string}
     */
    private function fingerprint(string $text): array
    {
        return ['length' => mb_strlen($text), 'sha256' => hash('sha256', $text)];
    }
}
