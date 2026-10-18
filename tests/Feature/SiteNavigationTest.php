<?php

namespace Tests\Feature;

use App\Models\CourseLesson;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

/**
 * Un site qui se conduit tout seul : chaque action mène à l'étape suivante et à la bonne partie de la page,
 * les erreurs sont visibles en haut avec le focus sur le premier champ, un bouton d'envoi ne part qu'une
 * fois, les états vides proposent l'action à faire, les pages d'erreur ramènent quelque part d'utile.
 */
class SiteNavigationTest extends TestCase
{
    use HasCourses, RefreshDatabase;

    /* ---------- Redirections vers l'étape suivante et la bonne partie de la page ---------- */

    public function test_a_new_lesson_leads_straight_to_the_part_where_the_learner_talks_with_the_teacher(): void
    {
        config(['filesystems.private_disk' => 'bucket', 'services.gemini.key' => 'cle-de-test']);
        Storage::fake('bucket');
        $this->fakeGemini();
        $user = $this->learner();

        $response = $this->actingAs($user)->post('/mes-cours', ['pages' => [$this->photo()], 'title' => '']);

        $response->assertRedirect(route('courses.show', CourseLesson::sole()).'#travailler');
        $this->get(route('courses.show', CourseLesson::sole()))
            ->assertSee('id="travailler"', false)
            ->assertSee('id="travailler-titre"', false);
    }

    public function test_every_anchor_used_by_a_redirection_exists_on_its_page(): void
    {
        $admin = User::factory()->admin()->create();
        $learner = User::factory()->create();
        $learner->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 300, 'level' => 'A2', 'status' => 'completed']);

        $page = $this->actingAs($admin)->get(route('admin.users.show', $learner))->assertOk();
        $page->assertSee('id="acces-offert"', false)->assertSee('id="evaluation"', false);

        $this->actingAs($admin)->post(route('admin.users.password', $learner), ['confirmed' => 1])
            ->assertRedirect(route('admin.users.show', $learner).'#mot-de-passe-provisoire');
        $this->get(route('admin.users.show', $learner))->assertSee('id="mot-de-passe-provisoire"', false);

        $this->actingAs($learner)->get(route('assessment.show'))->assertSee('id="formules"', false);
    }

    /* ---------- Erreurs : en haut de l'écran, focus sur le premier champ ---------- */

    public function test_a_form_with_errors_shows_them_at_the_top_and_focuses_the_first_field(): void
    {
        $page = $this->from(route('register'))->followingRedirects()
            ->post('/inscription', ['name' => '', 'phone' => 'abc', 'password' => 'court', 'password_confirmation' => 'autre']);

        $html = $page->getContent();
        $page->assertSee('class="toast toast-error" role="alert"', false)->assertSee('points à corriger');
        // Un seul champ reçoit le focus : le premier en erreur (même sans JavaScript).
        $this->assertSame(1, substr_count($html, ' autofocus'));
        $this->assertMatchesRegularExpression('/id="name"[^>]*aria-invalid="true"[^>]*autofocus/s', $html);
    }

    public function test_a_single_error_says_what_to_correct(): void
    {
        $this->from(route('login'))->followingRedirects()
            ->post('/connexion', ['phone' => '+22890000000', 'password' => 'mauvais'])
            ->assertSee('À corriger :')
            ->assertSee('role="alert"', false);
    }

    /* ---------- Boutons d'envoi et liens internes (public/js/app.js) ---------- */

    public function test_every_page_loads_the_script_that_guards_against_double_sending(): void
    {
        $script = file_get_contents(public_path('js/app.js'));

        // Un second toucher pendant l'envoi est ignoré, le bouton dit « Envoi en cours… ».
        $this->assertStringContainsString("if (form.dataset.busy) {\n        event.preventDefault();", $script);
        $this->assertStringContainsString('Envoi en cours…', $script);
        $this->assertStringContainsString("button.setAttribute('aria-disabled', 'true')", $script);
        // Après confirmation d'une action grave, le bouton passe aussi en « en cours ».
        $this->assertStringContainsString("markBusy(form, form.querySelector('button[type=\"submit\"], button:not([type])'));\n    form.submit();", $script);
        // Lien vers une partie : défilement (doux ou direct selon « moins d'animations ») et focus sur le titre.
        $this->assertStringContainsString("behavior: reduceMotion() ? 'auto' : 'smooth'", $script);
        $this->assertStringContainsString('focusWithoutScroll(headingOf(target))', $script);

        $this->get(route('home'))->assertSee('js/app.js', false);
    }

    /* ---------- États vides ---------- */

    public function test_empty_states_offer_the_action_to_do(): void
    {
        $user = $this->learner();

        $this->actingAs($user)->get(route('courses.index'))
            ->assertSee("Vous n'avez pas encore ajouté de leçon.", false)
            ->assertSeeInOrder(['empty-state', 'Ajouter une leçon'], false);

        $this->get(route('lessons.history'))
            ->assertSee('Aucune séance pour le moment.')
            ->assertSee('Aller à mon espace');
    }

    /* ---------- Pages d'erreur ---------- */

    public function test_error_pages_are_branded_in_french_with_a_useful_way_back(): void
    {
        Route::middleware('web')->get('/essai-erreur/{code}', fn (int $code) => abort($code));
        config(['app.debug' => false]);

        $expected = [
            404 => ['Page introuvable', "Retour à l'accueil"],
            419 => ['Page expirée', 'Recharger la page'],
            429 => ['Trop de demandes en peu de temps', 'Réessayer'],
            500 => ['Un problème est survenu', "Retour à l'accueil"],
            503 => ['Site en maintenance', null],
        ];

        foreach ($expected as $code => [$title, $button]) {
            $page = $this->get('/essai-erreur/'.$code)->assertStatus($code);
            $text = html_entity_decode($page->getContent(), ENT_QUOTES);
            $page->assertSee('<html lang="fr">', false)->assertSee('noindex', false);
            $this->assertStringContainsString($title, $text, "Page {$code}");

            if ($button !== null) {
                $this->assertStringContainsString($button, $text, "Page {$code}");
            }
        }
    }

    /* ---------- Parcours principal inchangé ---------- */

    public function test_the_main_path_is_unchanged(): void
    {
        $this->post('/inscription', ['name' => 'Koffi Mensah', 'phone' => '+22890123456', 'password' => 'motdepasse', 'password_confirmation' => 'motdepasse'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertSee('Passez votre évaluation gratuite');
        $this->assertStringNotContainsString('jours de travail', file_get_contents(resource_path('views/dashboard.blade.php')));
        $this->assertStringNotContainsString('jours de travail', file_get_contents(resource_path('views/program/show.blade.php')));
        $this->assertSame(['home', 'plans', 'about', 'country', 'contact', 'register', 'privacy'], Seo::INDEXABLE_ROUTES);
    }
}
