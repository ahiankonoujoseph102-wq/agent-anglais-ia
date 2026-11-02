<?php

namespace Tests\Feature\Live;

use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Gemini\TeacherSetup;
use App\Support\Teachers\Teachers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Plainte d'un élève débutant (vidéo du 1er novembre 2026) : il ne comprenait pas, le professeur continuait en anglais,
 * trop vite, disait « Repeat after me » et le coupait pendant qu'il donnait son numéro. Désormais, pour Teacher Joe ET
 * Teacher Rose, dans toutes les conversations : s'arrêter au premier signe, expliquer en français (débutants), vérifier
 * (« Avez-vous compris ? »), puis seulement « En anglais, on dit … » et « Répétez après moi » ; plus de silence avant
 * de reprendre la parole pour les débutants.
 */
class BeginnerUnderstandingTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    public function test_both_teachers_teach_in_english_then_simpler_english_then_french_then_back_to_english(): void
    {
        $user = User::factory()->level(Level::A1)->create(['name' => 'Kossi']);

        foreach (['joe', 'rose'] as $key) {
            foreach (['assessment', 'lesson', 'course', 'level_test'] as $kind) {
                $text = TeacherSetup::instructions($kind, $user, 15, teacher: Teachers::find($key));
                $where = "{$key} {$kind}";

                // Le cours se fait en anglais, au niveau de l'élève, dès les premiers mots ; jamais tout traduire.
                $this->assertStringContainsString('THE LESSON IS IN ENGLISH', $text, $where);
                $this->assertStringContainsString('from your very first words', $text, $where);
                $this->assertStringContainsString('French is a LAST RESORT, never the language of the lesson, and you never translate everything you say.', $text, $where);
                // a) Repérer et S'ARRÊTER.
                $this->assertStringContainsString('the SAME incomplete answer twice (e.g. they repeat the model sentence instead of finishing it)', $text, $where);
                $this->assertStringContainsString('At ONE sign, STOP the exercise', $text, $where);
                // b) Échelle : anglais plus simple, puis explication en français (sens, mots anglais dedans), vérifier,
                // puis RETOUR à l'anglais : la même question, à laquelle l'élève répond.
                $this->assertStringContainsString('1) SIMPLER ENGLISH FIRST', $text, $where);
                $this->assertStringContainsString('Never French at the first difficulty.', $text, $where);
                $this->assertStringContainsString('2) Still not understood: explain in FRENCH', $text, $where);
                $this->assertStringContainsString('Explain the point, do not translate everything. Then ask « Vous avez compris ? » and WAIT.', $text, $where);
                $this->assertStringContainsString('3) Understood: go BACK TO ENGLISH at once: ask the same question again in English', $text, $where);
                $this->assertLessThan(strpos($text, '2) Still not understood'), strpos($text, '1) SIMPLER ENGLISH FIRST'), $where);
                $this->assertLessThan(strpos($text, '3) Understood'), strpos($text, '2) Still not understood'), $where);
                $this->assertStringNotContainsString('switch to FRENCH at once', $text, $where);
                // c) Apprendre à le dire en anglais : « I don't understand ».
                $this->assertStringContainsString('"When you don\'t understand, you can say: I don\'t understand."', $text, $where);
                // d) Jamais « Repeat after me » sans le sens compris ; sa propre réponse : d'abord en français.
                $this->assertStringContainsString('NEVER "Repeat after me" before the meaning is understood.', $text, $where);
                $this->assertStringContainsString('« Dites-le-moi d\'abord en français, et je vous aide à le dire en anglais. »', $text, $where);
                $this->assertStringContainsString('help piece by piece', $text, $where);
                // Demande d'explication en français, même mal entendue : tout de suite.
                $this->assertStringContainsString('anything that sounds like "explain in French"', $text, $where);
                // Lentement avec les débutants, et du temps pour répondre ; accent américain clair.
                $this->assertStringContainsString('SLOW is right: about two words per second', $text, $where);
                $this->assertStringContainsString('GIVE TIME', $text, $where);
                $this->assertStringContainsString('a clear, natural American accent', $text, $where);
            }
        }
    }

    public function test_the_assessment_and_its_mini_lesson_follow_the_same_ladder(): void
    {
        $prospect = User::factory()->create(['name' => 'Kossi']);
        $tester = User::factory()->create(['name' => 'Ama']);
        $tester->forceFill(['is_test_account' => true])->save();
        $old = TeacherSetup::instructions('assessment', $prospect, 5);
        $new = TeacherSetup::instructions('assessment', $tester->fresh(), 5);

        // Question pas comprise : d'abord en anglais plus simple, puis le français, puis la même question en anglais.
        $this->assertStringContainsString('first the same question in simpler, slower English', $old);
        $this->assertStringContainsString('Never go on to the next question while they are lost', $old);
        $this->assertStringContainsString('first the same question in VERY simple, slow English', $new);
        foreach ([$old, $new] as $text) {
            $this->assertStringContainsString('« Vous avez compris ? » and WAIT', $text);
            $this->assertStringContainsString('ask the question again IN ENGLISH', $text);
            $this->assertStringContainsString('piece by piece', $text);
        }

        // Mini-leçon : en anglais simple, comprise d'abord (le français seulement si besoin), répétée ensuite.
        foreach (['assessment_next_step', 'assessment_next_step_phrase'] as $key) {
            $step = config("teacher.{$key}");
            $this->assertStringContainsString('Do you understand?', $step, $key);
            $this->assertStringContainsString('not understood → explain it in French', $step, $key);
            $this->assertLessThan(strpos($step, 'Repeat after me'), strpos($step, 'Do you understand?'), $key);
            $this->assertStringNotContainsString('Répétez après moi', $step, $key);
        }
    }

    public function test_every_level_is_taught_in_english_at_its_own_level(): void
    {
        $this->assertStringContainsString('the lesson is IN ENGLISH', config('teacher.lesson_language.common'));
        $this->assertStringContainsString('VERY SIMPLE ENGLISH', config('teacher.lesson_language.A1'));
        $this->assertStringNotContainsString('MAINLY IN FRENCH', config('teacher.lesson_language.A1'));
        $this->assertStringContainsString('French only through WHEN THE LEARNER DOES NOT UNDERSTAND', config('teacher.lesson_language.A2'));
        $this->assertStringContainsString('English only', config('teacher.lesson_language.B1'));
        $this->assertStringContainsString('Everything in English', config('teacher.lesson_language.C1'));
        $this->assertSame('I don\'t understand.', config('teacher.useful_phrases.0.en'));
    }

    public function test_beginners_get_more_time_before_the_teacher_speaks_again(): void
    {
        // Avant : 1 000 millisecondes en A1, A2 et à l'évaluation ; 800 ailleurs.
        $this->assertSame(1200, TeacherSetup::silenceMs('A1'));
        $this->assertSame(1000, TeacherSetup::silenceMs('A2'));
        $this->assertSame(800, TeacherSetup::silenceMs('B1'));
        $this->assertSame(1000, TeacherSetup::silenceMs('unknown'));
        $this->assertSame(800, TeacherSetup::silenceMs('B2'));
        $this->assertSame(800, TeacherSetup::silenceMs('C2'));

        // Le navigateur ne signale jamais la fin du tour avant ce silence (A1 : 1,2 + 0,5 seconde, jamais moins de 2).
        $user = User::factory()->level(Level::A1)->create(['name' => 'Kossi', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A1);
        $html = $this->actingAs($user)->get('/seance')->assertOk()->getContent();
        preg_match("/data-audio='([^']+)'/", $html, $match);
        $audio = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);
        $this->assertEquals(2, $audio['end_of_turn_seconds']);
    }
}
