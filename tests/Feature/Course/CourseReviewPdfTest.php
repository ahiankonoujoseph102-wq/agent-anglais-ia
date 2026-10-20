<?php

namespace Tests\Feature\Course;

use App\Models\CourseReview;
use App\Support\CourseReviewPdf;
use App\Support\CourseReviews;
use App\Support\PrivateStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\HasCourseReviews;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

/**
 * PDF du document de révision : aux couleurs de Talkami, police Sora intégrée, en-tête et pied de page, gardé sur
 * le disque privé, supprimé avec la leçon. Aucune marque d'intelligence artificielle. Aucun appel réel.
 */
class CourseReviewPdfTest extends TestCase
{
    use HasCourseReviews, HasCourses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->fakeReviewProviders();
    }

    private function readyReview(): CourseReview
    {
        $user = $this->learner();
        $this->finishedSession($user, $this->storedLesson($user));
        $this->artisan('courses:prepare-reviews')->assertSuccessful();

        return CourseReview::query()->sole();
    }

    public function test_the_pdf_is_kept_on_the_private_disk_with_the_sora_font(): void
    {
        $review = $this->readyReview();

        $this->assertTrue($review->isReady());
        $this->assertStringStartsWith('course-reviews/'.$review->user_id.'/'.$review->course_session_id.'-', $review->path);
        Storage::disk('local')->assertExists($review->path);

        $pdf = CourseReviews::file($review);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('Sora', $pdf);
        $this->assertLessThan(300 * 1024, strlen($pdf));
        $this->assertSame('document-de-revision-le-present-simple-'.now()->timezone(config('app.timezone'))->format('Y-m-d').'.pdf', CourseReviews::filename($review));
    }

    public function test_the_layout_has_the_brand_header_and_footer_and_every_part(): void
    {
        $html = CourseReviewPdf::html($this->readyReview());

        // En-tête : élève, date, leçon et niveau ; pied de page : site, WhatsApp, mention du document.
        $this->assertStringContainsString('Ama', $html);
        $this->assertStringContainsString(now()->locale('fr')->translatedFormat('j F Y'), $html);
        $this->assertStringContainsString('Le présent simple', $html);
        $this->assertStringContainsString('niveau A2', $html);
        $this->assertStringContainsString('WhatsApp', $html);
        $this->assertStringContainsString('Document personnel de révision, préparé par '.config('avatar.model.name'), $html);
        $this->assertStringContainsString('#0E2A5C', $html);
        $this->assertStringContainsString('#FF7A1A', $html);
        $this->assertStringContainsString('logo-blanc.png', $html);

        foreach (['La leçon, expliquée simplement', 'Les exercices travaillés pendant la séance', 'Vos erreurs pendant la séance', 'À retenir', 'À refaire seul', 'Solutions de l', 'She works in a bank.'] as $part) {
            $this->assertStringContainsString($part, $html);
        }

        // Un document de révision, jamais une copie à rendre ; aucune marque d'intelligence artificielle.
        $this->assertDoesNotMatchRegularExpression('/correction écrite|copie à rendre|devoir à rendre|Gemini|OpenAI|ChatGPT|Google|intelligence artificielle/iu', strip_tags($html));
    }

    public function test_a_written_document_is_never_asked_again_when_only_the_pdf_is_missing(): void
    {
        $user = $this->learner();
        $session = $this->finishedSession($user, $this->storedLesson($user));
        $review = CourseReview::query()->create([
            'user_id' => $user->id, 'course_session_id' => $session->id, 'course_lesson_id' => $session->course_lesson_id,
            'status' => CourseReview::PENDING, 'content' => $this->reviewContent(), 'provider' => 'gemini', 'attempts' => 1,
        ]);

        $this->assertTrue(CourseReviews::prepare($review));
        $this->assertTrue($review->fresh()->isReady());
        Http::assertNothingSent();
    }

    public function test_deleting_the_lesson_deletes_its_documents(): void
    {
        $review = $this->readyReview();
        $path = $review->path;

        $review->lesson->purge();

        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, CourseReview::query()->count());
        $this->assertNull(PrivateStorage::disk()->exists($path) ? 'present' : null);
    }
}
