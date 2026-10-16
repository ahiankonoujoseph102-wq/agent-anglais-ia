<?php

namespace Tests\Unit;

use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\TeacherSetup;
use App\Support\Gemini\UsageCost;
use Tests\TestCase;

class TeacherSetupTest extends TestCase
{
    public function test_instructions_come_from_the_configuration_file(): void
    {
        config([
            'platform.name' => 'Prof Test',
            'teacher.persona' => 'Teacher of :platform for :name.',
            'teacher.lesson' => 'Lesson of :minutes minutes, warning :warning minutes before.',
        ]);

        $user = new User(['name' => 'Yao']);
        $user->level = Level::B2;

        $this->assertSame(
            "Teacher of Prof Test for Yao.\n\n".config('teacher.level_guidance.B2')."\n\nLesson of 15 minutes, warning 2 minutes before.",
            TeacherSetup::instructions('lesson', $user, 15),
        );
    }

    public function test_learner_name_cannot_inject_placeholders(): void
    {
        config(['teacher.persona' => 'Hello :name, :minutes min.']);

        $user = new User(['name' => ':minutes']);

        $this->assertStringStartsWith('Hello :minutes, 10 min.', TeacherSetup::instructions('lesson', $user, 10));
    }

    public function test_unknown_level_uses_the_beginner_friendly_guidance(): void
    {
        $text = TeacherSetup::instructions('assessment', new User(['name' => 'Afi']), 5);

        $this->assertStringContainsString(config('teacher.level_guidance.unknown'), $text);
        $this->assertStringContainsString('lasts 5 minutes at most', $text);
    }

    public function test_setup_locks_audio_transcription_and_resumption(): void
    {
        config(['services.gemini.voice' => 'Kore']);

        $setup = TeacherSetup::lesson(new User(['name' => 'Afi']), 15, 'poignee');

        $this->assertSame(['AUDIO'], $setup['generationConfig']['responseModalities']);
        $this->assertSame('Kore', $setup['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName']);
        $this->assertSame(['handle' => 'poignee'], $setup['sessionResumption']);
        $this->assertSame('{}', json_encode($setup['inputAudioTranscription']));
        $this->assertSame('{}', json_encode($setup['outputAudioTranscription']));
    }

    public function test_animated_teacher_expressions_are_declared_after_the_main_function(): void
    {
        config(['avatar.enabled' => true]);

        $setup = TeacherSetup::lesson(new User(['name' => 'Afi']), 15);
        $functions = $setup['tools'][0]['functionDeclarations'];

        $this->assertSame([TeacherSetup::END_LESSON, TeacherSetup::SET_EXPRESSION], array_column($functions, 'name'));
        $this->assertSame(array_keys(config('avatar.expressions')), $functions[1]['parameters']['properties']['expression']['enum']);
        $this->assertContains('welcome', $functions[1]['parameters']['properties']['expression']['enum']);
        $this->assertStringContainsString('never mention it', $functions[1]['description']);

        $assessment = TeacherSetup::assessment(new User(['name' => 'Afi']), 5);
        $this->assertSame(TeacherSetup::SET_EXPRESSION, $assessment['tools'][0]['functionDeclarations'][1]['name']);
    }

    public function test_no_expression_function_when_the_animated_teacher_is_disabled(): void
    {
        config(['avatar.enabled' => false]);

        $setup = TeacherSetup::lesson(new User(['name' => 'Afi']), 15);

        $this->assertSame([TeacherSetup::END_LESSON], array_column($setup['tools'][0]['functionDeclarations'], 'name'));
    }

    public function test_cost_estimate_uses_configured_prices(): void
    {
        config([
            'services.gemini.pricing.input_per_million' => 3.0,
            'services.gemini.pricing.output_per_million' => 12.0,
        ]);

        $this->assertEqualsWithDelta(0.009, UsageCost::usd(1_000, 500), 1e-9);
        $this->assertSame("0,0090\u{00A0}$", UsageCost::format(0.009));
    }
}
