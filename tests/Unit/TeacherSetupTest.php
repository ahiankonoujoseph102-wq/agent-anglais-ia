<?php

namespace Tests\Unit;

use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\TeacherSetup;
use App\Support\Gemini\UsageCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherSetupTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_assessment_is_entirely_in_english_with_french_as_a_last_resort(): void
    {
        $user = new User(['name' => 'Afi']);
        // Même pour un apprenant au niveau déjà connu (évaluation réinitialisée), aucune règle « français ».
        $user->level = Level::A1;
        $text = TeacherSetup::instructions('assessment', $user, 5);

        $this->assertStringContainsString('lasts 5 minutes at most', $text);
        $this->assertStringContainsString('the whole assessment is in ENGLISH, from the first word to the last, including the greeting and the conclusion', $text);
        $this->assertStringContainsString('1) repeat the question more slowly; 2) if still not understood, rephrase it with simpler, shorter words; 3) then move on to an easier question', $text);
        $this->assertStringContainsString('if the learner has said TWICE, in French, that they understand nothing, say ONE short, reassuring sentence in French', $text);
        $this->assertStringContainsString('This difficulty must be reflected in the level you record', $text);
        $this->assertStringContainsString('practical questions about the website (sign-up, payment, plans…) get a short answer in French', $text);
        $this->assertStringContainsString('Greeting, in English, short and simple', $text);
        $this->assertStringContainsString('Then, in simple English, announce the level', $text);
        $this->assertStringNotContainsString(config('teacher.level_guidance.A1'), $text);
        $this->assertStringNotContainsString('Greet the learner in French', $text);
    }

    public function test_lessons_from_a2_do_not_switch_to_french_too_easily(): void
    {
        foreach (['A2', 'B1'] as $value) {
            $user = new User(['name' => 'Afi']);
            $user->level = Level::from($value);
            $text = TeacherSetup::instructions('lesson', $user, 15);

            $this->assertStringContainsString(config("teacher.level_guidance.{$value}"), $text);
            $this->assertStringContainsString('repeat', config("teacher.level_guidance.{$value}"));
            $this->assertStringContainsString('rephrase in simpler English', config("teacher.level_guidance.{$value}"));
        }

        $this->assertStringContainsString('English by default', config('teacher.level_guidance.A2'));
        $this->assertStringContainsString('Never switch to French just because the learner said "I don\'t understand"', config('teacher.level_guidance.A2'));
        $this->assertStringContainsString('English only', config('teacher.level_guidance.B1'));
    }

    public function test_teacher_matches_the_learner_tone_and_shows_active_listening(): void
    {
        $text = TeacherSetup::instructions('lesson', new User(['name' => 'Afi']), 15);

        $this->assertStringContainsString('Match the learner\'s energy and tone', $text);
        $this->assertStringContainsString('Enthusiastic and joyful learner: be enthusiastic and joyful too', $text);
        $this->assertStringContainsString('Shy or hesitant learner: be calm and soft', $text);
        $this->assertStringContainsString('Learner who doubts themselves: be encouraging', $text);
        $this->assertStringContainsString('Never mocking', $text);
        $this->assertStringContainsString('pick up one element of the learner\'s answer', $text);
        $this->assertStringContainsString('Never chain questions mechanically', $text);
    }

    public function test_expressions_are_used_often_but_never_twice_in_a_row(): void
    {
        config(['avatar.enabled' => true]);

        $functions = TeacherSetup::lesson(new User(['name' => 'Afi']), 15)['tools'][0]['functionDeclarations'];
        $description = $functions[1]['description'];

        $this->assertStringContainsString('about once every one or two turns', $description);
        $this->assertStringContainsString('never twice in a row with the same expression', $description);
        $this->assertStringContainsString('"happy": each time the learner answers well', $description);
        $this->assertStringContainsString('"encouraging": when the learner hesitates', $description);
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
        $this->assertStringContainsString('ask your first, very easy question (for example: "What is your name?") and WAIT', $text);
    }

    public function test_resumption_cues_never_ask_for_a_new_welcome(): void
    {
        $this->assertStringContainsString('Do not greet the learner again', TeacherSetup::cue('resume'));
        $this->assertStringContainsString('ask your last question again', TeacherSetup::cue('pause_resume'));
    }
}
