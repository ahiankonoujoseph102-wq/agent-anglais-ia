<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\TeacherSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

/**
 * L'accueil, les formules et la page de résultat présentent « Mes cours » comme une aide EN PLUS
 * de l'oral, uniquement pour les cours d'anglais ; aucune promesse que le site ne tient pas.
 */
class CourseHelpHomeTest extends TestCase
{
    use HasCourses;
    use RefreshDatabase;

    /** Formulations interdites partout où le site parle de « Mes cours ». */
    private const FORBIDDEN = ['fait les devoirs', 'tous vos cours', 'correction écrite', 'donne les réponses', 'donne les reponses'];

    public function test_the_home_page_presents_the_course_help_for_pupils_and_parents(): void
    {
        $html = html_entity_decode($this->get('/')->assertOk()->getContent(), ENT_QUOTES);

        foreach ([
            "Vos cours d'anglais enfin compris",
            "Photographiez votre leçon ou votre exercice d'anglais",
            'en français quand il le faut',
            'Posez-lui toutes vos questions',
            'Il vous guide pour faire votre exercice vous-même, puis corrige',
            "Uniquement pour les cours d'anglais.",
            'Parents',
            'ne fait pas les devoirs à la place de votre enfant',
            "vous aide aussi à comprendre vos cours d'anglais de l'école",
        ] as $text) {
            $this->assertStringContainsString($text, $html);
        }

        // L'oral reste le message principal : le titre du haut de page n'a pas changé.
        $this->assertStringContainsString('Parlez anglais avec un professeur', $html);
    }

    public function test_the_course_help_button_follows_the_visitor(): void
    {
        // Visiteur : inscription. Aucun lien vers la page réservée aux comptes.
        $this->get('/')->assertDontSee('/mes-cours', false);

        // Compte sans formule active : jamais renvoyé vers une page qui lui refuse l'accès.
        $this->actingAs(User::factory()->create())->get('/')->assertDontSee('Ouvrir Mes cours');

        // Formule active : le bouton ouvre « Mes cours ».
        $this->actingAs($this->learner())->get('/')->assertSee('Ouvrir Mes cours')->assertSee(route('courses.index'), false);
    }

    public function test_no_forbidden_wording_on_the_pages_that_present_my_courses(): void
    {
        $pages = [
            $this->get('/')->getContent(),
            file_get_contents(resource_path('views/components/plan-card.blade.php')),
            file_get_contents(resource_path('views/assessment/result.blade.php')),
            file_get_contents(resource_path('teacher/site-guide.md')),
            file_get_contents(resource_path('views/courses/index.blade.php')),
        ];

        foreach ($pages as $page) {
            foreach (self::FORBIDDEN as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase($forbidden, $page);
            }
        }
    }

    public function test_every_plan_includes_the_course_help_with_the_limit_from_the_configuration(): void
    {
        config(['platform.courses.sessions_per_day' => 7]);

        $this->get('/')->assertSee("Aide à vos cours d'anglais (Mes cours) : 7 leçons par jour", false);
    }

    public function test_the_result_page_reminds_that_the_plans_include_the_course_help(): void
    {
        $user = $this->learner();
        $user->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 300, 'level' => 'A2', 'status' => 'completed']);
        $user->level = Level::A2;
        $user->save();

        $this->actingAs($user)->get('/evaluation')->assertOk()
            ->assertSee("Chaque formule comprend aussi l'aide à vos cours d'anglais", false);
    }

    public function test_the_home_page_stays_light(): void
    {
        $bytes = 0;

        // Poids transféré (texte compressé) : la page, les feuilles de style et le JavaScript commun.
        foreach ([$this->get('/')->getContent(), file_get_contents(public_path('css/tokens.css')), file_get_contents(public_path('css/app.css')), file_get_contents(public_path('js/app.js'))] as $file) {
            $bytes += strlen(gzencode($file, 9));
        }

        $this->assertLessThan(150 * 1024, $bytes, 'La page d\'accueil dépasse 150 Ko (hors image de Teacher Joe et police).');
    }

    public function test_the_teacher_instructions_cover_confusion_slowing_down_and_french_as_a_tool(): void
    {
        $user = new User(['name' => 'Yao']);

        $daily = TeacherSetup::instructions('lesson', $user, 15);
        $course = TeacherSetup::instructions('course', $user, 10, ['lesson' => 'x', 'warning_seconds' => 90]);

        foreach ([$daily, $course] as $text) {
            // Mal compris : autre explication, plus lente et plus simple, en français si c'était en anglais.
            $this->assertStringContainsString('"je ne comprends pas", "hein ?"', $text);
            $this->assertStringContainsString('ANOTHER way', $text);
            $this->assertStringContainsString('switch to French', $text);
            // N'entend pas / ne suit pas : ralentir nettement, phrases plus courtes.
            $this->assertStringContainsString('slow down clearly', $text);
            $this->assertStringContainsString('much shorter sentences', $text);
            // Ton, âge, encouragements sans flatterie ; une seule correction par réponse conservée.
            $this->assertStringContainsString('apparent age', $text);
            $this->assertStringContainsString('never mocking', $text);
            $this->assertStringContainsString('without excessive flattery', $text);
            $this->assertStringContainsString('at most one correction per answer', $text);
        }

        // Séance orale : faire parler en anglais, le français seulement pour débloquer.
        $this->assertStringContainsString('use French only to unblock them, then return to English at once', $daily);
        $this->assertStringContainsString('French is a short EXPLANATION TOOL', $daily);

        // « Mes cours » : le français est la langue principale ; détail complet de chaque point ; vérifications.
        $this->assertStringContainsString('French is the MAIN language of EXPLANATION', $course);
        $this->assertStringContainsString('go back to French as soon as an explanation gets difficult', $course);
        $this->assertStringContainsString('each one with its translation in French', $course);
        $this->assertStringContainsString('EVERY point of the lesson', $course);
        $this->assertStringContainsString('never skimming', $course);
        $this->assertStringContainsString('ask a short question to check they understood', $course);
    }
}
