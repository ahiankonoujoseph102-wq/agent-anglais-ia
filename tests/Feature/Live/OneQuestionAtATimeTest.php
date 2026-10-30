<?php

namespace Tests\Feature\Live;

use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\LearnerSituation;
use App\Support\Gemini\TeacherSetup;
use App\Support\LearnerName;
use App\Support\Teachers\Teachers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Début des séances (signalé par le fondateur, 30 octobre 2026) : le professeur disait tout d'un seul coup (salut,
 * durée, « comment travailler avec moi », « how are you today? », sujet, première phrase à répéter) sans attendre la
 * réponse, car l'indication de début et la règle du « premier tour » le lui demandaient. Désormais : une question à la
 * fois, un petit échauffement, la continuité avec la séance d'avant, puis la leçon ; jamais « Administrateur ».
 */
class OneQuestionAtATimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_conversation_says_that_a_question_ends_the_turn(): void
    {
        config(['teachers.list.rose.ready' => true]);
        $user = new User(['name' => 'Afi Mensah']);
        $user->level = Level::A2;

        foreach (['joe', 'rose'] as $key) {
            $teacher = Teachers::find($key);

            foreach (['assessment', 'lesson', 'course', 'level_test'] as $kind) {
                $text = TeacherSetup::instructions($kind, $user, 15, teacher: $teacher);
                $this->assertStringContainsString('A QUESTION ENDS YOUR TURN: as soon as you have asked the learner something', $text, "{$key} {$kind}");
                $this->assertStringContainsString('ANSWER WHAT THEY JUST SAID', $text, "{$key} {$kind}");
                $this->assertStringContainsString('"I see this is not clear. Let me say it more simply: …"', $text, "{$key} {$kind}");
            }
        }
    }

    public function test_the_daily_lesson_starts_with_a_greeting_and_waits(): void
    {
        $cue = TeacherSetup::cue('lesson_start', ['minutes' => 15, 'duration_note' => '', 'welcome_note' => '', 'handoff_note' => '.']);

        $this->assertStringContainsString('Say your FIRST TURN now, and only it', $cue);
        $this->assertStringContainsString('then ask how they are today and STOP: wait for their answer', $cue);
        $this->assertStringContainsString('Do not announce the topic and do not teach anything in this turn.', $cue);
        $this->assertStringNotContainsString('teach the first item of the board', $cue);

        $lesson = (string) config('teacher.lesson');
        $this->assertStringContainsString('FIRST TURN, AND NOTHING MORE', $lesson);
        $this->assertStringContainsString('(3) Only then, in a new turn: today\'s topic', $lesson);
    }

    public function test_the_assessment_welcomes_asks_for_questions_then_gives_the_instructions(): void
    {
        foreach (['assessment', 'assessment_new'] as $key) {
            $text = (string) config("teacher.{$key}");
            $this->assertStringContainsString('ONE thing per turn', $text, $key);
            $this->assertStringContainsString('« Avez-vous des questions avant de commencer ? »', $text, $key);
            $this->assertStringContainsString('« Je vais vous poser quelques questions en anglais pour trouver votre niveau.', $text, $key);
            $this->assertLessThan(strpos($text, 'Je vais vous poser'), strpos($text, 'Avez-vous des questions avant de commencer'), $key);
        }

        $this->assertStringContainsString('STOP and wait for the answer', TeacherSetup::cue('assessment_start'));
        $this->assertStringContainsString('STOP: wait for the answer', TeacherSetup::cue('level_test_start'));
        $this->assertStringContainsString('STOP and wait for their answer', TeacherSetup::cue('course_start', ['minutes' => 10]));
    }

    public function test_the_teacher_uses_the_first_name_and_never_a_role_name(): void
    {
        $this->assertSame('Joseph', LearnerName::first(new User(['name' => 'Joseph Ahiankonou'])));
        $this->assertSame('Afi', LearnerName::first(new User(['name' => 'afi'])));

        foreach (['Administrateur', 'admin', 'Assistant', 'Élève', '  '] as $name) {
            $this->assertNull(LearnerName::first(new User(['name' => $name])), $name);
        }

        $admin = User::factory()->admin()->create(['name' => 'Administrateur']);
        $text = TeacherSetup::instructions('lesson', $admin, 15);
        $this->assertStringContainsString('You do not know the learner\'s first name: greet them warmly WITHOUT a name', $text);
        $this->assertStringNotContainsString('first name is "Administrateur"', $text);
        $this->assertStringNotContainsString('First name: Administrateur', $text);
        $this->assertStringNotContainsString('Prénom : Administrateur', LearnerSituation::describe($admin, 'lesson'));

        $joseph = new User(['name' => 'Joseph Ahiankonou']);
        $text = TeacherSetup::instructions('assessment', $joseph, 5);
        $this->assertStringContainsString('The learner\'s first name is "Joseph": use it to greet them', $text);
        $this->assertStringContainsString('Prénom : Joseph', $text);
        $this->assertStringNotContainsString('Prénom : Joseph Ahiankonou', $text);
    }
}
