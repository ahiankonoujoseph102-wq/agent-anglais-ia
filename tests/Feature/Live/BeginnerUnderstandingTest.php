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

    public function test_both_teachers_stop_explain_in_french_check_then_make_repeat_in_every_conversation(): void
    {
        $user = User::factory()->level(Level::A1)->create(['name' => 'Kossi']);

        foreach (['joe', 'rose'] as $key) {
            foreach (['assessment', 'lesson', 'course', 'level_test'] as $kind) {
                $text = TeacherSetup::instructions($kind, $user, 15, teacher: Teachers::find($key));
                $where = "{$key} {$kind}";

                // a) Repérer : silence, hésitation, français, réponse sans rapport, mauvaise répétition… et S'ARRÊTER.
                $this->assertStringContainsString('the SAME incomplete answer twice (e.g. they repeat the model sentence instead of finishing it)', $text, $where);
                $this->assertStringContainsString('At ONE sign, STOP the exercise: no more "Almost", "Very close" or "Repeat after me" for it', $text, $where);
                // b) à d) Expliquer en français, vérifier, puis l'anglais et seulement ensuite « Répétez après moi ».
                $this->assertStringContainsString('EXPLAIN in French what we are learning and what the sentence MEANS', $text, $where);
                $this->assertStringContainsString('2) « Avez-vous compris ? » and WAIT', $text, $where);
                $this->assertStringContainsString('3) only then « En anglais, on dit : … » (slowly, in small pieces), then « Répétez après moi : … ».', $text, $where);
                // e) Jamais « Repeat after me » sans le sens compris.
                $this->assertStringContainsString('NEVER "Repeat after me" before the meaning is understood.', $text, $where);
                // f) Son propre numéro, sa propre phrase : d'abord en français, puis morceau par morceau.
                $this->assertStringContainsString('« Dites-le-moi d\'abord en français, et je vous aide à le dire en anglais. »', $text, $where);
                $this->assertStringContainsString('help piece by piece', $text, $where);
                // Demande d'explication en français, même mal entendue.
                $this->assertStringContainsString('anything that sounds like "explain in French"', $text, $where);
                // Lentement avec les débutants, et du temps pour répondre.
                $this->assertStringContainsString('SLOW is right: about two words per second', $text, $where);
                $this->assertStringContainsString('GIVE TIME', $text, $where);
                // Accent américain clair.
                $this->assertStringContainsString('a clear, natural American accent', $text, $where);
            }
        }
    }

    public function test_the_assessment_and_its_mini_lesson_follow_the_same_method(): void
    {
        $prospect = User::factory()->create(['name' => 'Kossi']);
        $tester = User::factory()->create(['name' => 'Ama']);
        $tester->forceFill(['is_test_account' => true])->save();
        $old = TeacherSetup::instructions('assessment', $prospect, 5);
        $new = TeacherSetup::instructions('assessment', $tester->fresh(), 5);

        // Question pas comprise : le français tout de suite, « Avez-vous compris ? », puis l'anglais très simple.
        $this->assertStringContainsString('follow WHEN THE LEARNER DOES NOT UNDERSTAND at once: say the question in French', $old);
        $this->assertStringContainsString('Never go on to the next question while they are lost', $old);
        $this->assertStringContainsString('At ONCE (follow WHEN THE LEARNER DOES NOT UNDERSTAND)', $new);
        foreach ([$old, $new] as $text) {
            $this->assertStringContainsString('« Avez-vous compris ? » and WAIT', $text);
            $this->assertStringContainsString('piece by piece', $text);
        }

        // Mini-leçon : comprendre d'abord, répéter ensuite (avec ou sans erreurs transmises).
        foreach (['assessment_next_step', 'assessment_next_step_phrase'] as $key) {
            $step = config("teacher.{$key}");
            $this->assertLessThan(strpos($step, 'Répétez après moi'), strpos($step, 'Avez-vous compris'), $key);
            $this->assertStringNotContainsString('Repeat after me', $step, $key);
        }
    }

    public function test_beginners_get_explanations_mainly_in_french_and_more_english_as_the_level_goes_up(): void
    {
        $this->assertStringContainsString('EXPLANATIONS MAINLY IN FRENCH', config('teacher.lesson_language.A1'));
        $this->assertStringContainsString('Not understood: French at once.', config('teacher.lesson_language.A2'));
        $this->assertStringContainsString('English only', config('teacher.lesson_language.B1'));
        $this->assertStringContainsString('Everything in English', config('teacher.lesson_language.C1'));
    }

    public function test_beginners_get_more_time_before_the_teacher_speaks_again(): void
    {
        // Avant : 1 000 millisecondes en A1, A2 et à l'évaluation ; 800 ailleurs.
        $this->assertSame(2500, TeacherSetup::silenceMs('A1'));
        $this->assertSame(2000, TeacherSetup::silenceMs('A2'));
        $this->assertSame(1200, TeacherSetup::silenceMs('B1'));
        $this->assertSame(1800, TeacherSetup::silenceMs('unknown'));
        $this->assertSame(800, TeacherSetup::silenceMs('B2'));
        $this->assertSame(800, TeacherSetup::silenceMs('C2'));

        // Le navigateur ne signale jamais la fin du tour avant ce silence (A1 : 2,5 + 1,5 secondes).
        $user = User::factory()->level(Level::A1)->create(['name' => 'Kossi', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A1);
        $html = $this->actingAs($user)->get('/seance')->assertOk()->getContent();
        preg_match("/data-audio='([^']+)'/", $html, $match);
        $audio = json_decode(html_entity_decode($match[1], ENT_QUOTES), true);
        $this->assertSame(4, (int) $audio['end_of_turn_seconds']);
    }
}
