<?php

namespace Tests\Feature\Course;

use App\Mail\CourseReviewMail;
use App\Mail\PaymentAlertMail;
use App\Models\CourseReview;
use App\Models\User;
use App\Support\CourseReviewMailer;
use App\Support\EmailAddress;
use App\Support\MailQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\HasCourseReviews;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

/**
 * Document de révision par e-mail (Brevo, faux envoi : aucun e-mail réel) : PDF joint, jamais bloquant, limite du jour
 * partagée avec les alertes de l'administrateur, invitation à ajouter son adresse.
 */
class CourseReviewMailTest extends TestCase
{
    use HasCourseReviews, HasCourses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        $this->fakeReviewProviders();
        config([
            'alerts.emails' => ['admin@example.test'],
            'mail.default' => 'smtp',
            'mail.from.address' => 'expediteur@example.test',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.username' => 'utilisateur-smtp',
            'mail.mailers.smtp.password' => 'MOT-DE-PASSE-SMTP-SECRET',
        ]);
    }

    private function learnerWithSession(?string $email = 'ama@example.test'): User
    {
        $user = $this->learner();
        $user->forceFill(['email' => $email])->save();
        $this->finishedSession($user, $this->storedLesson($user));

        return $user;
    }

    public function test_a_ready_document_is_sent_with_the_pdf_attached(): void
    {
        $user = $this->learnerWithSession();

        $this->artisan('courses:prepare-reviews')->assertSuccessful();

        $review = CourseReview::query()->sole();
        $this->assertSame(CourseReviewMailer::SENT, $review->mail_status);
        $this->assertNotNull($review->mailed_at);

        Mail::assertSent(CourseReviewMail::class, function (CourseReviewMail $mail) use ($user, $review) {
            $attachment = $mail->attachments()[0];
            $html = $mail->render();

            return $mail->hasTo($user->email)
                && $mail->envelope()->subject === 'Votre document de révision : Le présent simple'
                && $attachment->as === 'document-de-revision-le-present-simple-'.now()->timezone(config('app.timezone'))->format('Y-m-d').'.pdf'
                && str_contains($html, 'Bonjour Ama,')
                && str_contains($html, 'vous le trouverez en pièce jointe')
                && str_contains($html, 'Ouvrir Mes cours')
                && str_contains($html, route('courses.show', $review->course_lesson_id).'#documents')
                && str_contains($html, 'Document personnel de révision, préparé par '.config('avatar.model.name'))
                && ! preg_match('/Gemini|OpenAI|ChatGPT|Google|correction écrite/i', $html);
        });

        $this->actingAs($user)->get(route('lessons.history'))->assertSee('Envoyé aussi par e-mail.');
    }

    public function test_without_an_email_address_the_document_stays_downloadable_with_a_discreet_invitation(): void
    {
        $user = $this->learnerWithSession(null);

        $this->artisan('courses:prepare-reviews');

        $this->assertSame(CourseReviewMailer::NO_EMAIL, CourseReview::query()->sole()->mail_status);
        Mail::assertNothingSent();
        $this->actingAs($user)->get(route('lessons.history'))->assertOk()
            ->assertSee('Ajoutez votre adresse e-mail dans')
            ->assertSee(route('account.edit'), false)
            ->assertSee('Télécharger mon document de révision');
    }

    public function test_without_mail_settings_nothing_is_sent_and_nothing_is_blocked(): void
    {
        config(['mail.mailers.smtp.password' => null]);
        $this->learnerWithSession();

        $this->artisan('courses:prepare-reviews');

        $review = CourseReview::query()->sole();
        $this->assertTrue($review->isReady());
        $this->assertNull($review->mail_status);
        Mail::assertNothingSent();
    }

    public function test_a_sending_failure_is_noted_without_secret_and_blocks_nothing(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connection refused, password=MOT-DE-PASSE-SMTP-SECRET'));
        $this->learnerWithSession();

        $this->artisan('courses:prepare-reviews')->assertSuccessful();

        $review = CourseReview::query()->sole();
        $this->assertTrue($review->isReady());
        $this->assertSame(CourseReviewMailer::FAILED, $review->mail_status);
        $this->assertStringNotContainsString('MOT-DE-PASSE-SMTP-SECRET', (string) $review->mail_error);
    }

    public function test_beyond_the_daily_limit_the_document_stays_downloadable_and_the_administrator_is_warned_once(): void
    {
        config(['platform.courses.review.daily_emails' => 1]);
        $first = $this->learnerWithSession('kofi@example.test');
        $this->artisan('courses:prepare-reviews');
        $second = $this->learnerWithSession('yawa@example.test');
        $third = $this->learnerWithSession('akossiwa@example.test');
        $this->artisan('courses:prepare-reviews');

        $statuses = CourseReview::query()->orderBy('id')->pluck('mail_status')->all();
        $this->assertSame([CourseReviewMailer::SENT, CourseReviewMailer::QUOTA, CourseReviewMailer::QUOTA], $statuses);
        Mail::assertSent(CourseReviewMail::class, 1);
        // Une seule alerte du jour pour l'administrateur.
        Mail::assertSent(PaymentAlertMail::class, 1);
        Mail::assertSent(PaymentAlertMail::class, fn (PaymentAlertMail $mail) => $mail->alert->title === 'Limite des e-mails du jour atteinte');
        $this->actingAs($second)->get(route('lessons.history'))->assertSee('Télécharger mon document de révision');
    }

    public function test_the_daily_counter_is_shared_with_the_administrator_alerts(): void
    {
        config(['mail.daily_limit' => 1]);
        $this->learnerWithSession();
        $this->artisan('courses:prepare-reviews');

        $this->assertSame(1, MailQuota::usedToday());
        $this->assertFalse(MailQuota::allowsAlert());
        $this->assertFalse(MailQuota::allowsReview());
    }

    public function test_the_email_field_now_says_what_it_is_for(): void
    {
        $this->assertSame('Facultatif. Pour recevoir vos documents de révision par e-mail.', EmailAddress::HINT);
        $this->get(route('register'))->assertOk()->assertSee(EmailAddress::HINT);
        // Abonné payant : l'adresse devient obligatoire (rappels de séance, LearnerFollowUpTest).
        $this->actingAs($this->learner())->get(route('account.edit'))->assertOk()->assertSee(EmailAddress::REQUIRED_HINT);
    }
}
