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

        $this->assertStringStartsWith(
            "Teacher of Prof Test for Yao.\n\n".config('teacher.level_guidance.B2')."\n\nLesson of 15 minutes, warning 2 minutes before.\n\nQUESTIONS ABOUT THE WEBSITE",
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
        config(['services.gemini.voice' => 'Iapetus']);

        $setup = TeacherSetup::lesson(new User(['name' => 'Afi']), 15, 'poignee');

        $this->assertSame(['AUDIO'], $setup['generationConfig']['responseModalities']);
        $this->assertSame('Iapetus', $setup['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName']);
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

    public function test_speech_detection_settings_come_from_the_configuration(): void
    {
        config([
            'services.gemini.activity_detection.prefix_padding_ms' => 250,
            'services.gemini.activity_detection.silence_duration_ms' => 1500,
        ]);

        foreach ([
            TeacherSetup::assessment(new User(['name' => 'Afi']), 5),
            TeacherSetup::lesson(new User(['name' => 'Afi']), 15),
            TeacherSetup::levelTest(new User(['name' => 'Afi']), Level::A1, 10),
        ] as $setup) {
            $detection = $setup['realtimeInputConfig']['automaticActivityDetection'];

            $this->assertFalse($detection['disabled']);
            $this->assertSame('START_SENSITIVITY_LOW', $detection['startOfSpeechSensitivity']);
            $this->assertSame('END_SENSITIVITY_LOW', $detection['endOfSpeechSensitivity']);
            $this->assertSame(250, $detection['prefixPaddingMs']);
            $this->assertSame(1500, $detection['silenceDurationMs']);
        }
    }

    public function test_teacher_greets_once_asks_one_question_and_waits(): void
    {
        $text = TeacherSetup::instructions('assessment', new User(['name' => 'Afi']), 5);

        $this->assertStringContainsString('Greet the learner only ONCE', $text);
        $this->assertStringContainsString('Never repeat your introduction', $text);
        $this->assertStringContainsString('Ask ONE simple question at a time, then wait.', $text);
        $this->assertStringContainsString('rephrase the question ONCE, more simply', $text);
        $this->assertStringContainsString('move on to another, easier question', $text);
        $this->assertStringContainsString('Never speak over the learner', $text);
        $this->assertStringContainsString('accept that the learner answers in French', $text);
        $this->assertStringContainsString('ask your first, very easy question (for example their name) and WAIT', $text);
    }

    public function test_resumption_cues_never_ask_for_a_new_welcome(): void
    {
        $this->assertStringContainsString('Do not greet the learner again', TeacherSetup::cue('resume'));
        $this->assertStringContainsString('ask your last question again', TeacherSetup::cue('pause_resume'));
    }
}
