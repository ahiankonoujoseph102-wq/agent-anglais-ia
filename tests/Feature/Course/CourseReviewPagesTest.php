<?php

namespace Tests\Feature\Course;

use App\Enums\SubscriptionStatus;
use App\Models\CourseReview;
use App\Models\CourseSession;
use App\Models\User;
use App\Support\Gemini\SiteGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\HasCourseReviews;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

/**
 * Où l'élève trouve son document de révision : « Mes cours » (la leçon) et « Mes séances ». « En préparation » ne
 * bloque jamais la page et se met à jour tout seul ; « Réessayer » après un échec ; téléchargement réservé à son
 * propriétaire (et à l'administrateur). Aucun appel réel.
 */
class CourseReviewPagesTest extends TestCase
{
    use HasCourseReviews, HasCourses, RefreshDatabase;

    private User $user;

    private CourseSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->fakeReviewProviders();
        $this->user = $this->learner();
        $this->session = $this->finishedSession($this->user, $this->storedLesson($this->user));
    }

    public function test_a_document_in_preparation_never_blocks_the_page_and_updates_itself(): void
    {
        $page = $this->actingAs($this->user)->get(route('courses.show', $this->session->course_lesson_id))->assertOk()
            ->assertSee('Mes documents de révision')
            ->assertSee('Votre document de révision est en préparation. Cette page se met à jour toute seule.')
            ->assertSee('data-review-status="'.route('courses.review.status', $this->session).'"', false)
            ->assertSee('js/course-review.js', false);
        // Le bouton de téléchargement attend, caché, que le document soit prêt.
        $this->assertMatchesRegularExpression('/data-review-when="ready"\s+hidden/', $page->getContent());
        $this->assertMatchesRegularExpression('/data-review-when="pending"\s+role="status"/', $page->getContent());

        // La page n'a rien demandé au modèle texte : c'est la tâche du planificateur qui prépare le document.
        Http::assertNothingSent();
        $this->actingAs($this->user)->getJson(route('courses.review.status', $this->session))->assertOk()->assertJson(['state' => 'pending']);

        $this->artisan('courses:prepare-reviews');

        $this->actingAs($this->user)->getJson(route('courses.review.status', $this->session))->assertJson(['state' => 'ready']);
    }

    public function test_a_ready_document_is_downloaded_from_my_courses_and_my_sessions(): void
    {
        $this->artisan('courses:prepare-reviews');
        $download = route('courses.review.download', $this->session);

        $this->actingAs($this->user)->get(route('courses.show', $this->session->course_lesson_id))->assertOk()
            ->assertSee('Télécharger mon document de révision')->assertSee($download, false);
        $this->actingAs($this->user)->get(route('lessons.history'))->assertOk()
            ->assertSee('Mes cours : documents de révision')->assertSee('Le présent simple')->assertSee($download, false);

        $response = $this->actingAs($this->user)->get($download)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('attachment; filename="document-de-revision-le-present-simple-', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_only_its_owner_and_the_administrator_can_download_it_even_after_the_plan_ends(): void
    {
        $this->artisan('courses:prepare-reviews');
        $download = route('courses.review.download', $this->session);

        $this->actingAs(User::factory()->create())->get($download)->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson(route('courses.review.status', $this->session))->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get($download)->assertOk();

        // Formule terminée : le document reste à l'élève.
        $this->user->subscriptions()->update(['status' => SubscriptionStatus::Expired, 'ends_at' => now()->subDay()]);
        $this->actingAs($this->user->fresh())->get($download)->assertOk();
        $this->actingAs($this->user->fresh())->get(route('lessons.history'))->assertOk()->assertSee($download, false);
    }

    public function test_after_a_failure_the_learner_can_retry_a_few_times(): void
    {
        CourseReview::query()->create(['user_id' => $this->user->id, 'course_session_id' => $this->session->id, 'course_lesson_id' => $this->session->course_lesson_id, 'status' => CourseReview::FAILED, 'attempts' => 3]);

        $this->actingAs($this->user)->get(route('lessons.history'))->assertOk()
            ->assertSee('La préparation de votre document de révision n\'a pas abouti. Vous pouvez réessayer.', false)
            ->assertSee(route('courses.review.retry', $this->session), false);

        $this->actingAs($this->user)->from(route('lessons.history'))->post(route('courses.review.retry', $this->session))
            ->assertRedirect(route('lessons.history'))
            ->assertSessionHas('status', 'C\'est noté : votre document de révision est de nouveau en préparation.');
        $this->assertTrue($this->session->review()->first()->isPending());

        foreach (range(1, 3) as $i) {
            $this->session->review()->update(['status' => CourseReview::FAILED]);
            $this->actingAs($this->user)->from(route('lessons.history'))->post(route('courses.review.retry', $this->session));
        }

        $this->assertTrue($this->session->review()->first()->isFailed());
        $this->assertSame(404, $this->actingAs(User::factory()->create())->post(route('courses.review.retry', $this->session))->status());
    }

    public function test_a_too_short_session_says_why_there_is_no_document(): void
    {
        CourseReview::query()->create(['user_id' => $this->user->id, 'course_session_id' => $this->session->id, 'course_lesson_id' => $this->session->course_lesson_id, 'status' => CourseReview::SKIPPED, 'reason' => 'too_short']);

        $this->actingAs($this->user)->get(route('courses.show', $this->session->course_lesson_id))->assertOk()
            ->assertSee('Pas de document de révision pour cette séance : elle était trop courte.')
            ->assertDontSee('Télécharger mon document de révision');
        $this->actingAs($this->user)->get(route('courses.review.download', $this->session))->assertNotFound();
    }

    public function test_privacy_and_the_site_guide_describe_the_revision_document(): void
    {
        $this->get(route('privacy'))->assertOk()
            ->assertSee('votre document personnel de révision en PDF')
            ->assertSee('Les leçons de « Mes cours » et leurs documents de révision')
            ->assertSee('votre document de révision vous est envoyé en pièce jointe');

        $guide = SiteGuide::text();
        $this->assertStringContainsString('DOCUMENT DE RÉVISION', $guide);
        $this->assertStringContainsString('« Télécharger mon document de révision »', $guide);
        $this->assertStringContainsString('jamais une copie à rendre', $guide);
    }
}
