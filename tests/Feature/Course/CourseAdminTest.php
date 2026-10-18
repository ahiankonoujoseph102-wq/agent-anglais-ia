<?php

namespace Tests\Feature\Course;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

class CourseAdminTest extends TestCase
{
    use HasCourses, RefreshDatabase;

    public function test_the_learner_sheet_shows_the_lessons_read_only(): void
    {
        $learner = $this->learner();
        $lesson = $this->storedLesson($learner, 'Leçon de Ama', 2);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.users.show', $learner))
            ->assertOk()
            ->assertSeeText('« Mes cours » : 1 leçon envoyée')
            ->assertSeeText('Leçon de Ama')
            ->assertSeeText('2 pages')
            ->assertSee(route('courses.page', [$lesson, $lesson->pages->first()]), false)
            ->assertSeeText('Contenu extrait par l\'IA')
            ->assertSeeText('She works in a bank')
            // Lecture seule : aucun formulaire de suppression ni de modification de leçon.
            ->assertDontSee('data-confirm="course.delete"', false);

        $this->actingAs($admin)->get(route('admin.users.show', $this->learner()))
            ->assertSeeText('« Mes cours » : 0 leçon envoyée')
            ->assertSeeText('Aucune leçon.');
    }

    public function test_the_learner_sheet_is_for_administrators_only(): void
    {
        $this->actingAs($this->learner())->get(route('admin.users.show', $this->learner()))->assertForbidden();
    }

    public function test_the_consumption_page_counts_my_courses_sessions_separately(): void
    {
        $this->fakeGemini();
        config(['services.gemini.key' => 'cle']);
        $learner = $this->learner();
        $lesson = $this->storedLesson($learner, 'Leçon de test');
        $this->freezeSecond();

        $this->actingAs($learner)->postJson(route('courses.connect', $lesson))->assertOk();
        $this->travel(2)->minutes();
        $this->actingAs($learner)->postJson(route('courses.signal'), ['input_tokens' => 1200, 'output_tokens' => 300])->assertOk();
        $this->actingAs($learner)->postJson(route('courses.finish'))->assertOk();

        $page = $this->actingAs(User::factory()->admin()->create())->get(route('admin.usage.index'));

        $page->assertOk()
            ->assertSeeText('Séances « Mes cours »')
            ->assertSeeText('Dernières séances « Mes cours »')
            ->assertSeeText('Leçon de test')
            ->assertSeeText('« Mes cours » (séances, durée)')
            ->assertSeeText('1 · 2 minutes')
            ->assertSeeText('1 200 / 300 jetons');

        // Les séances orales restent comptées à part : aucune séance orale ici.
        $this->assertSame(0, $learner->lessons()->count());
        $this->assertMatchesRegularExpression('/Séances<\/div>\s*<div class="value">0<\/div>/', $page->getContent());
    }

    public function test_the_dashboard_card_comes_after_the_next_step_and_only_with_access(): void
    {
        $learner = $this->learner();
        $html = $this->actingAs($learner)->get('/espace')->assertOk()->getContent();

        $this->assertStringContainsString('Pris sur votre temps du jour', $html);
        $this->assertLessThan(strpos($html, 'Mes cours</h2>'), strpos($html, 'Prochaine étape'));
        $this->assertLessThan(strpos($html, 'Mon niveau'), strpos($html, 'Mes cours</h2>'));
        $this->assertStringContainsString(route('courses.index'), $html);

        $without = $this->actingAs(User::factory()->create())->get('/espace')->assertOk()->getContent();
        $this->assertStringNotContainsString('Pris sur votre temps du jour', $without);
    }

    public function test_my_courses_is_in_the_menu_but_not_in_the_bottom_bar_and_not_linked_for_visitors(): void
    {
        $html = $this->actingAs($this->learner())->get('/espace')->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new \DOMXPath($dom);

        $this->assertGreaterThan(0, $xpath->query('//details[@data-menu]//a[contains(@href,"/mes-cours")]')->length);
        $this->assertSame(0, $xpath->query('//nav[@class="tabbar"]//a[contains(@href,"/mes-cours")]')->length);

        auth()->logout();
        // L'accueil présente « Mes cours » (section élèves), mais un visiteur n'a aucun lien vers la page : son bouton mène à l'inscription.
        $this->get('/')->assertOk()->assertDontSee('/mes-cours', false);
    }

    public function test_check_deployment_counts_the_course_files(): void
    {
        $this->storedLesson($this->learner(), 'X', 2);

        $this->artisan('app:check-deployment')->expectsOutputToContain('Fichiers de « Mes cours » : 2');
    }

    public function test_my_courses_is_presented_as_an_extra_never_as_the_main_content(): void
    {
        $this->actingAs($this->learner())->get('/mes-cours')
            ->assertSeeText('Un complément facultatif')
            ->assertSeeText('Votre programme et votre séance orale du jour restent l\'essentiel');
    }

    public function test_the_main_oral_session_page_is_unchanged(): void
    {
        $learner = $this->learner();
        $this->actingAs($learner)->get('/seance')->assertRedirect(route('program.questionnaire', 1));
    }
}
