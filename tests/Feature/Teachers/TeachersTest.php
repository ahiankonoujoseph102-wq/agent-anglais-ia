<?php

namespace Tests\Feature\Teachers;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\SiteGuide;
use App\Support\Gemini\TeacherSetup;
use App\Support\Gemini\TeacherVoice;
use App\Support\Live\OpenAiRealtime;
use App\Support\Teachers\Teachers;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Socle des professeurs (étape 1 du chantier « Teacher Rose ») : liste dans config/teachers.php, professeur de
 * l'apprenant (users.teacher) et de chaque séance (colonne teacher). Le second professeur de ces tests est FICTIF :
 * il prouve que le nom et les voix suivent le professeur, sans que Teacher Rose existe encore.
 */
class TeachersTest extends TestCase
{
    use RefreshDatabase;

    private function withSecondTeacher(): void
    {
        config([
            'avatar.second' => ['name' => 'Teacher Test'] + (array) config('avatar.model'),
            'teachers.list.second' => ['avatar' => 'second', 'ready' => true, 'gemini_voice' => 'Kore', 'realtime_voice' => 'marin'],
        ]);
    }

    public function test_teacher_joe_is_the_default_and_teacher_rose_is_offered_too(): void
    {
        $this->assertSame(['joe', 'rose'], array_keys(Teachers::choosable()));
        $this->assertSame('joe', Teachers::default()->key);
        $this->assertSame(config('avatar.model.name'), Teachers::default()->name());
        $this->assertSame(config('avatar.model'), Teachers::default()->avatar());
        $this->assertSame('joe', Teachers::forUser(null)->key);
        $this->assertSame('joe', Teachers::forUser(User::factory()->create())->key);
    }

    public function test_teacher_joe_keeps_the_voice_chosen_in_the_voice_trial(): void
    {
        config(['services.gemini.voice' => 'Achird', 'services.openai.realtime_voice' => 'cedar']);

        $this->assertSame('Achird', Teachers::default()->geminiVoice());
        $this->assertSame('cedar', Teachers::default()->realtimeVoice());

        TeacherVoice::choose('Charon');

        $this->assertSame('Charon', Teachers::default()->geminiVoice());
    }

    public function test_an_unknown_teacher_falls_back_to_the_default_one(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['teacher' => 'retired'])->save();

        $this->assertSame('joe', Teachers::forUser($user->fresh())->key);
        $this->assertNull(Teachers::find('retired'));
        $this->assertNull(Teachers::find(null));
    }

    public function test_the_teacher_column_is_never_mass_assignable(): void
    {
        $this->assertNotContains('teacher', (new User)->getFillable());

        $this->expectException(MassAssignmentException::class);
        new User(['name' => 'Afi', 'teacher' => 'second']);
    }

    public function test_each_conversation_keeps_the_teacher_it_started_with(): void
    {
        $user = User::factory()->create();

        $assessment = $user->assessment()->create(['status' => AssessmentStatus::InProgress, 'started_at' => now(), 'allowed_seconds' => 300]);

        $this->assertSame('joe', $assessment->fresh()->getAttributes()['teacher']);
        $this->assertSame('joe', $assessment->teacher()->key);
    }

    public function test_a_conversation_from_before_the_teachers_is_with_teacher_joe(): void
    {
        $user = User::factory()->create();
        $assessment = $user->assessment()->create(['status' => AssessmentStatus::InProgress, 'started_at' => now(), 'allowed_seconds' => 300]);
        $assessment->forceFill(['teacher' => null])->saveQuietly();

        $this->assertSame('joe', $assessment->fresh()->teacher()->key);
    }

    public function test_a_second_teacher_brings_their_own_name_and_voices(): void
    {
        $this->withSecondTeacher();
        config(['services.gemini.voice' => 'Achird', 'services.openai.realtime_voice' => 'cedar']);

        $user = User::factory()->create(['name' => 'Afi']);
        $user->level = Level::A2;
        $user->forceFill(['teacher' => 'second'])->save();

        $second = Teachers::forUser($user);
        $this->assertSame('second', $second->key);

        $setup = TeacherSetup::lesson($user, 15, null, 'LESSON OF THE DAY');
        $instructions = $setup['systemInstruction']['parts'][0]['text'];

        $this->assertSame('Kore', $setup['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName']);
        $this->assertStringContainsString('Teacher Test', $instructions);
        $this->assertStringNotContainsString((string) config('avatar.model.name'), $instructions);
        $this->assertStringContainsString('Teacher Test', SiteGuide::text('lesson', $second));
        $this->assertSame('marin', OpenAiRealtime::session($setup, 'A2', $second)['audio']['output']['voice']);

        // Une séance créée sans professeur (évaluation gratuite) prend le professeur par défaut ; les contrôleurs de la
        // séance du jour, de « Mes cours » et du bilan de niveau donnent celui de l'apprenant.
        $assessment = $user->assessment()->create(['status' => AssessmentStatus::InProgress, 'started_at' => now(), 'allowed_seconds' => 300]);
        $this->assertSame('joe', $assessment->teacher()->key);
        $assessment->forceFill(['teacher' => 'second'])->save();
        $this->assertSame('second', $assessment->fresh()->teacher()->key);

        // Teacher Joe, lui, ne change pas.
        $joe = TeacherSetup::lesson($user, 15, null, 'LESSON OF THE DAY', 1, Teachers::default());
        $this->assertSame('Achird', $joe['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName']);
        $this->assertStringContainsString((string) config('avatar.model.name'), $joe['systemInstruction']['parts'][0]['text']);
        $this->assertSame('cedar', OpenAiRealtime::session($joe)['audio']['output']['voice']);
    }
}
