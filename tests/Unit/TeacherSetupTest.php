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

    public function test_assessment_order_is_strict_and_ends_with_one_announcement(): void
    {
        $user = new User(['name' => 'Afi']);
        // Même pour un apprenant au niveau déjà connu (évaluation réinitialisée) : règles propres à l'évaluation.
        $user->level = Level::A1;
        $text = TeacherSetup::instructions('assessment', $user, 5);

        $this->assertStringContainsString('STRICT ORDER: 1) the greeting; 2) questions adapted to the learner; 3) ONE final announcement of the level', $text);
        $this->assertStringContainsString('never ask another question, never announce the level a second time', $text);
        $this->assertStringContainsString('FIRST call the function "record_assessment_result"', $text);
        $this->assertStringContainsString('BEFORE saying anything about the level', $text);
        $this->assertStringContainsString('« Les formules s\'affichent juste en dessous : il suffit de toucher le bouton orange. »', $text);
        $this->assertStringContainsString('Never say "on the website"', $text);
        $this->assertStringContainsString('the website ends the conversation automatically', $text);
        $this->assertStringContainsString('Never repeat a sentence or a question you have already said', $text);
        $this->assertStringNotContainsString(config('teacher.level_guidance.A1'), $text);
        $this->assertStringContainsString('Stop asking questions. Call record_assessment_result now, before saying anything about the level', TeacherSetup::cue('assessment_warning', ['remaining' => 1]));
    }

    public function test_assessment_teacher_praises_corrects_once_and_uses_french_as_a_short_tool(): void
    {
        $text = TeacherSetup::instructions('assessment', new User(['name' => 'Afi']), 5);

        $this->assertStringContainsString('the assessment is conducted in ENGLISH', $text);
        $this->assertStringContainsString('short, sincere praise ("Great answer!", "Well said.")', $text);
        $this->assertStringContainsString('correct only the most important one, in one sentence: \'You said "…". We say "…".\'', $text);
        $this->assertStringContainsString('Never pretend to hear what you did not clearly hear', $text);
        $this->assertStringContainsString('French is a short EXPLANATION TOOL, never the language of the conversation', $text);
        $this->assertStringContainsString('« Essayez en anglais : … »', $text);
        $this->assertStringContainsString('« "I am living in Lomé" se dit plutôt "I live in Lomé" »', $text);
        $this->assertStringContainsString('how the learner reacts to your corrections and help, and how much French they needed', $text);
        $this->assertStringNotContainsString('LAST RESORT', $text);
        $this->assertStringContainsString('start slowly, then adapt to the level you perceive', $text);
    }

    public function test_pace_follows_the_level_and_lessons_take_more_time_to_correct(): void
    {
        $this->assertStringContainsString('PACE: slow', config('teacher.level_guidance.A1'));
        $this->assertStringContainsString('PACE: slow', config('teacher.level_guidance.A2'));
        $this->assertStringContainsString('PACE: moderate', config('teacher.level_guidance.B1'));
        $this->assertStringContainsString('PACE: natural', config('teacher.level_guidance.B2'));
        $this->assertStringContainsString('Clarity first: articulate every word, never swallow words, one idea per sentence.', config('teacher.persona'));

        $user = new User(['name' => 'Afi']);
        $user->level = Level::A2;
        $lesson = TeacherSetup::instructions('lesson', $user, 15);
        $this->assertStringContainsString('you have more time than in the assessment', $lesson);
        $this->assertStringContainsString('make the learner say the correct form again', $lesson);
        $this->assertStringContainsString(config('teacher.level_guidance.A2'), $lesson);
    }

    public function test_no_function_call_for_expressions_in_any_conversation(): void
    {
        // Les appels de fonction de l'API Live sont synchrones : chaque set_expression faisait reprendre
        // la parole au modèle, qui répétait sa dernière phrase. Seule la fonction de résultat reste.
        config(['avatar.enabled' => true]);

        $this->assertSame([TeacherSetup::END_LESSON], array_column(TeacherSetup::lesson(new User(['name' => 'Afi']), 15)['tools'][0]['functionDeclarations'], 'name'));
        $this->assertSame([TeacherSetup::RECORD_ASSESSMENT_RESULT], array_column(TeacherSetup::assessment(new User(['name' => 'Afi']), 5)['tools'][0]['functionDeclarations'], 'name'));
        $this->assertSame([TeacherSetup::RECORD_LEVEL_TEST_RESULT], array_column(TeacherSetup::levelTest(new User(['name' => 'Afi']), Level::A1, 10)['tools'][0]['functionDeclarations'], 'name'));
    }
}
