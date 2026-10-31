<?php

namespace Tests\Feature\Live;

use App\Models\User;
use App\Support\Gemini\SiteGuide;
use App\Support\Gemini\TeacherSetup;
use App\Support\Teachers\Teachers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ce que l'écran montre suit ce que fait le professeur (vérification de A à Z du 31 octobre 2026) : bandeau
 * « À vous de parler » branché sur la dernière question réellement posée (public/js/answer-hint.js, testé par
 * tests/js/answer-hint.test.mjs), première question de l'évaluation toujours « What's your name? », boutons et
 * conseils accordés au professeur choisi (« Interrompre la professeure » avec Teacher Rose), minuteur juste après le
 * niveau (« Mini-leçon et questions »).
 */
class ScreenMatchesTeacherTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_assessment_starts_with_the_name_and_the_banner_follows_the_teacher(): void
    {
        $page = $this->actingAs(User::factory()->create(['name' => 'Afi Mensah']))->get('/evaluation')->assertOk();

        // Le bandeau vient du module qui lit la dernière question ; plus aucun exemple écrit d'avance.
        $page->assertSee('data-answer-hint=', false)
            ->assertDontSee('par exemple « My name is … »')
            ->assertSee('vous demande d\'abord votre prénom (« What\'s your name? »)', false);

        $session = file_get_contents(public_path('js/live-session.js'));
        $this->assertStringContainsString("listeningText(state.teacherSaid, { firstAnswer: mode === 'assessment' && !state.learnerAnswered })", $session);
        $this->assertStringNotContainsString('firstAnswer: \'À vous', $session);

        foreach (['assessment_start', 'assessment_start_new'] as $cue) {
            $this->assertStringContainsString('ask exactly "What\'s your name?"', (string) config("teacher.cues.$cue"));
        }

        // La consigne ne fait jamais saluer avec le nom du compte : le nom est la première question.
        $instructions = TeacherSetup::instructions('assessment', User::factory()->create(['name' => 'Kofi Agbo']), 5);
        $this->assertStringContainsString('you never greet them by name: you ask "What\'s your name?" first', $instructions);
        $this->assertStringContainsString('ALWAYS this first question, word for word: "What\'s your name?"', $instructions);
        $this->assertStringContainsString('Your SECOND question', $instructions);
    }

    public function test_the_screen_speaks_of_teacher_rose_in_the_feminine(): void
    {
        $joe = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk();
        $joe->assertSee('Interrompre le professeur')
            ->assertSee('Avec des écouteurs, Teacher Joe vous entendra mieux.');

        $rose = $this->actingAs(User::factory()->create())->get('/evaluation?professeur=rose')->assertOk();
        $rose->assertSee('Interrompre la professeure')
            ->assertDontSee('Interrompre le professeur')
            ->assertSee('Avec des écouteurs, Teacher Rose vous entendra mieux.');

        // Son message de bienvenue et sa fiche du site nomment le même bouton que son écran.
        $paragraphs = Teachers::find('rose')->welcomeParagraphs();
        $this->assertStringContainsString('« Interrompre la professeure »', $paragraphs['lesson_rules']);
        $this->assertStringContainsString('« Interrompre le professeur »', (string) config('teacher.welcome.paragraphs.lesson_rules'));

        $guide = SiteGuide::text('lesson', Teachers::find('rose'));
        $this->assertStringContainsString('« Interrompre la professeure » (pendant qu\'elle parle)', $guide);
        $this->assertStringNotContainsString('Interrompre le professeur', $guide);
    }

    public function test_no_masculine_agreement_is_shown_for_teacher_rose_while_waiting(): void
    {
        $session = file_get_contents(public_path('js/live-session.js'));

        $this->assertStringNotContainsString('est très demandé', $session);
        $this->assertStringContainsString("Beaucoup d'élèves parlent avec \${teacherName} en ce moment.", $session);
        // Après le niveau, le professeur fait d'abord la mini-leçon : le minuteur ne dit plus « Vos questions ».
        $this->assertStringContainsString("'Mini-leçon et questions'", $session);
    }
}
