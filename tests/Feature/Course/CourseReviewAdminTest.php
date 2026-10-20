<?php

namespace Tests\Feature\Course;

use App\Models\CourseReview;
use App\Models\User;
use App\Support\CourseReviewMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\HasCourseReviews;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

/**
 * Suivi des documents de révision dans l'administration : « Consommation » (documents, échecs, fournisseurs, coût)
 * et « Alertes » (e-mails du jour sur la limite de Brevo). Aucun appel réel ni envoi réel.
 */
class CourseReviewAdminTest extends TestCase
{
    use HasCourseReviews, HasCourses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        $this->fakeReviewProviders();
        config([
            'mail.default' => 'smtp',
            'mail.from.address' => 'expediteur@example.test',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.username' => 'utilisateur-smtp',
            'mail.mailers.smtp.password' => 'MOT-DE-PASSE-SMTP-SECRET',
        ]);

        $user = $this->learner();
        $user->forceFill(['email' => 'ama@example.test'])->save();
        $this->finishedSession($user, $this->storedLesson($user));
        $this->artisan('courses:prepare-reviews');
        CourseReview::query()->create(['user_id' => $user->id, 'course_session_id' => $this->finishedSession($user, $user->courseLessons()->first())->id, 'status' => CourseReview::FAILED, 'attempts' => 3]);
    }

    public function test_consumption_shows_documents_failures_providers_and_emails(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.usage.index'))->assertOk()
            ->assertSee('Documents de révision (Mes cours)')
            ->assertSeeInOrder(['Préparés', '1', 'dont 1 aujourd\'hui'], false)
            ->assertSeeInOrder(['En échec', '1'])
            ->assertSee('Gemini : 1')
            ->assertSee('E-mails envoyés : 1, en échec : 0, non envoyés (limite du jour) : 0')
            ->assertSee('Documents de révision (Mes cours)', false);
    }

    public function test_alerts_show_the_emails_of_the_day_against_the_shared_limit(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.alerts.index'))->assertOk()
            ->assertSee('E-mails des documents de révision')
            ->assertSeeText('Aujourd\'hui : 1 e-mail sur 300 (documents et alertes ensemble)')
            ->assertSee('documents envoyés : 1, en échec : 0, non envoyés (limite) : 0')
            ->assertSee('Au plus 280 e-mails de documents par jour : les 20 derniers restent gardés pour vos alertes.')
            ->assertDontSee('MOT-DE-PASSE-SMTP-SECRET');
        $this->assertSame(CourseReviewMailer::SENT, CourseReview::query()->where('status', CourseReview::READY)->sole()->mail_status);
    }

    public function test_the_deployment_check_reports_the_documents_without_failing_on_them(): void
    {
        $this->artisan('app:check-deployment')
            ->expectsOutputToContain('Documents de révision (Mes cours)')
            ->expectsOutputToContain('E-mails du jour : 1 sur 300')
            ->expectsOutputToContain('Documents prêts : 1');
    }
}
