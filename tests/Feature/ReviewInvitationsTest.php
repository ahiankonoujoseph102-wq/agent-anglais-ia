<?php

namespace Tests\Feature;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Mail\FollowUpMail;
use App\Models\LearnerMail;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Reminders\FollowUps;
use App\Support\Reviews\ReviewInvitations;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Invitations par e-mail à laisser un avis (demande du fondateur, 2 novembre 2026 : un seul avis sur le site) :
 * une quelques heures après la première expérience, un rappel quelques jours après, puis rien ; jamais à qui a déjà
 * laissé un avis, à l'équipe ni à qui a demandé à ne plus rien recevoir ; jamais une demande de bonne note.
 */
class ReviewInvitationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-06 10:00:00', 'Africa/Lome'));
        Mail::fake();
        config([
            'platform.follow_ups.enabled' => true,
            'mail.default' => 'smtp',
            'mail.from.address' => 'equipe@example.test',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.username' => 'utilisateur-smtp',
            'mail.mailers.smtp.password' => 'mot-de-passe-smtp',
        ]);
    }

    private function assessed(string $name, int $hoursAgo, array $attributes = []): User
    {
        $user = User::factory()->level(Level::A2)->create(['name' => $name, 'email' => strtolower(explode(' ', $name)[0]).'@example.test'] + $attributes);
        $user->assessment()->create([
            'allowed_seconds' => 300, 'used_seconds' => 300, 'level' => 'A2', 'status' => 'completed',
            'ended_at' => now()->subHours($hoursAgo),
        ]);

        return $user->fresh();
    }

    public function test_a_first_invitation_a_few_hours_after_the_assessment_then_one_reminder(): void
    {
        $kofi = $this->assessed('Kofi Mensah', 4);
        $this->assessed('Afi Recent', 1);

        FollowUps::run();

        Mail::assertSent(FollowUpMail::class, 1);
        Mail::assertSent(FollowUpMail::class, function (FollowUpMail $mail) use ($kofi) {
            $html = $mail->render();

            return $mail->hasTo($kofi->email)
                && $mail->body['subject'] === 'Votre avis compte : comment se passe '.config('platform.name').' pour vous ?'
                && str_contains($html, 'Vous avez passé votre évaluation orale gratuite avec')
                && str_contains($html, 'positif comme négatif')
                && str_contains($html, 'quelle que soit votre note')
                && str_contains($html, route('reviews.edit', ['note' => 5]))
                && str_contains($html, 'Donner mon avis')
                && ! str_contains(mb_strtolower($html), 'bonne note');
        });

        // Le rappel : quelques jours plus tard seulement, puis plus rien.
        $this->travel(2)->days();
        FollowUps::run();
        Mail::assertSent(FollowUpMail::class, 2); // Afi, à son tour.
        $this->travel(4)->days();
        FollowUps::run();
        Mail::assertSent(FollowUpMail::class, fn (FollowUpMail $mail) => $mail->hasTo($kofi->email) && str_starts_with($mail->body['subject'], 'Petit rappel'));
        $this->travel(10)->days();
        FollowUps::run();
        $this->assertSame(2, LearnerMail::query()->where('user_id', $kofi->id)->whereIn('campaign', LearnerMail::REVIEWS)->count());
    }

    public function test_a_subscriber_after_a_first_lesson_and_never_after_a_review_or_a_stop(): void
    {
        $user = User::factory()->level(Level::A1)->create(['name' => 'Ama Koffi', 'email' => 'ama@example.test', 'welcomed_at' => now()]);
        $subscription = Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $user->lessons()->create([
            'subscription_id' => $subscription->id, 'date' => today(), 'allowed_seconds' => 900, 'used_seconds' => 600,
            'started_at' => now()->subHours(5), 'ended_at' => now()->subHours(4), 'status' => LessonStatus::Completed,
        ]);
        $reviewed = $this->assessed('Yao Kodjo', 10);
        $reviewed->review()->forceCreate(['rating' => 4, 'body' => 'Très bien pour pratiquer chaque jour.', 'status' => 'published', 'kind' => 'free']);
        $this->assessed('Kossi Stop', 10, ['follow_up_opt_out_at' => now()]);
        User::factory()->admin()->create(['email' => 'admin@example.test']);

        FollowUps::run();

        Mail::assertSent(FollowUpMail::class, 1);
        Mail::assertSent(FollowUpMail::class, fn (FollowUpMail $mail) => $mail->hasTo('ama@example.test')
            && str_contains($mail->render(), 'Vous avez fait votre première séance avec'));
    }

    public function test_a_review_left_in_between_cancels_the_pending_invitation(): void
    {
        $kofi = $this->assessed('Kofi Mensah', 4);
        ReviewInvitations::queue();
        $kofi->review()->forceCreate(['rating' => 2, 'body' => 'La connexion coupe trop souvent chez moi.', 'status' => 'pending', 'kind' => 'free']);

        FollowUps::sendPending();

        Mail::assertNothingSent();
        $this->assertSame(LearnerMail::SKIPPED, LearnerMail::query()->where('user_id', $kofi->id)->value('status'));
    }

    public function test_a_small_batch_per_day(): void
    {
        config(['platform.reviews.emails_per_day' => 2]);

        foreach (['Ama Un', 'Kofi Deux', 'Afi Trois'] as $name) {
            $this->assessed($name, 6);
        }

        FollowUps::run();
        FollowUps::run();

        Mail::assertSent(FollowUpMail::class, 2);
    }
}
