<?php

namespace Tests\Feature;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Enums\ReviewStatus;
use App\Enums\Role;
use App\Models\Review;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Alerts\AlertBuilder;
use App\Support\Live\SessionEnd;
use App\Support\Reviews\ReviewAsk;
use App\Support\Reviews\Reviews;
use App\Support\Reviews\TimeBonuses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Avis des élèves : publiés tout de suite (positifs comme négatifs), sauf une note très basse ou un texte à vérifier ;
 * 5 minutes offertes pour tout avis, une fois ; page publique, accueil, administration, phrase du professeur.
 */
class ReviewsTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    private const GOOD = 'Teacher Joe est patient, je parle enfin anglais sans avoir honte. Merci beaucoup !';

    private function assessed(string $name = 'Kofi Mensah'): User
    {
        $user = User::factory()->level(Level::A2)->create(['name' => $name]);
        $user->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 300, 'level' => 'A2', 'status' => 'completed']);

        return $user;
    }

    private function subscriber(string $name = 'Ama Koffi'): User
    {
        $user = User::factory()->level(Level::A1)->create(['name' => $name, 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A1);

        return $user->fresh();
    }

    private function heldLesson(User $user): void
    {
        $user->lessons()->create([
            'subscription_id' => $user->activeSubscription->id, 'date' => today(), 'allowed_seconds' => 900,
            'used_seconds' => 600, 'started_at' => now()->subMinutes(20), 'status' => LessonStatus::Completed,
        ]);
    }

    private function send(User $user, int $rating, string $body = self::GOOD)
    {
        return $this->actingAs($user)->put(route('reviews.save'), ['rating' => $rating, 'body' => $body, 'consent' => '1']);
    }

    public function test_only_learners_who_tried_can_review(): void
    {
        $this->assertFalse(Reviews::canReview(User::factory()->create()));
        $this->assertTrue(Reviews::canReview($this->assessed()));
        $this->assertFalse(Reviews::canReview(User::factory()->admin()->create()));

        $fresh = User::factory()->create();
        $this->actingAs($fresh)->get(route('reviews.edit'))->assertRedirect(route('dashboard'));
        $this->actingAs($fresh)->put(route('reviews.save'), ['rating' => 5, 'body' => self::GOOD, 'consent' => '1'])->assertForbidden();

        $subscriber = $this->subscriber();
        $this->assertFalse(Reviews::canReview($subscriber));
        $this->heldLesson($subscriber);
        $this->assertTrue(Reviews::canReview($subscriber));
    }

    public function test_a_positive_or_critical_review_is_published_at_once(): void
    {
        $kofi = $this->assessed();
        $this->send($kofi, 5)->assertRedirect(route('dashboard'))->assertSessionHas('status');
        $this->assertSame(ReviewStatus::Published, $kofi->review->status);

        // Une critique honnête (3 étoiles) est publiée telle quelle.
        $afi = $this->assessed('Afi Doe');
        $this->send($afi, 3, 'Les séances sont bien, mais la connexion coupe parfois et je dois reprendre.');
        $this->assertTrue($afi->review->isPublished());

        $this->get(route('reviews.index'))->assertOk()
            ->assertSee('Kofi M.')->assertDontSee('Kofi Mensah')->assertDontSee($kofi->phone)
            ->assertSee('la connexion coupe parfois')
            ->assertSee('Évaluation gratuite')
            ->assertSee('quelle que soit sa note');
    }

    public function test_a_very_negative_or_suspicious_review_waits_for_the_admin(): void
    {
        $this->assertSame(['low_rating'], Reviews::flags(1, 'Je ne suis pas content du tout de ce site.'));
        $this->assertContains('phone', Reviews::flags(5, 'Super ! Appelez-moi au 90 12 34 56 pour en parler.'));
        $this->assertContains('link', Reviews::flags(5, 'Allez voir www.autre-site.com, c\'est mieux.'));
        $this->assertContains('email', Reviews::flags(5, 'Écrivez-moi à kofi@example.com pour en savoir plus.'));
        $this->assertContains('words', Reviews::flags(4, 'C\'est une ARNAQUE, je vous le dis franchement.'));
        $this->assertContains('brand', Reviews::flags(5, 'Bien meilleur que ChatGPT pour parler anglais.'));
        $this->assertSame([], Reviews::flags(4, 'Bien, mais j\'aimerais des séances plus longues le week-end.'));

        $kofi = $this->assessed();
        $this->send($kofi, 1, 'Je ne suis pas content du tout, rien ne marche chez moi.');
        $review = $kofi->review;
        $this->assertSame(ReviewStatus::Pending, $review->status);
        $this->get(route('reviews.index'))->assertDontSee('rien ne marche chez moi');

        $alert = AlertBuilder::reviewPending($review->load('user'));
        $this->assertSame('review_pending', $alert->kind);
        $this->assertStringNotContainsString($kofi->phone, $alert->body);
    }

    public function test_five_minutes_are_given_once_whatever_the_rating(): void
    {
        $user = $this->subscriber();
        $this->heldLesson($user);
        $before = $user->remainingSecondsToday();

        $this->send($user, 2, 'Pas assez de temps chaque jour pour vraiment progresser.');
        $this->assertSame($before + TimeBonuses::seconds(), $user->fresh()->remainingSecondsToday());

        // Modifier son avis ne donne rien de plus.
        $this->send($user, 4, 'Finalement, avec de la régularité, ça avance bien. Merci !');
        $this->assertSame(1, $user->timeBonuses()->count());
        $this->assertSame(1, Review::query()->count());
    }

    public function test_a_free_learner_keeps_the_minutes_for_the_first_session_with_a_plan(): void
    {
        $kofi = $this->assessed();
        $this->send($kofi, 5)->assertSessionHas('status', fn ($message) => str_contains($message, 'première séance avec une formule'));
        $this->assertNull($kofi->fresh()->remainingSecondsToday());
        $this->assertSame(TimeBonuses::seconds(), TimeBonuses::pendingSeconds($kofi));

        Subscription::factory()->for($kofi)->forPlan('essentiel')->create();
        $this->assertSame(15 * 60 + TimeBonuses::seconds(), $kofi->fresh()->remainingSecondsToday());

        // Une séance ouverte les attache à aujourd'hui : demain, elles ne comptent plus.
        TimeBonuses::pin($kofi);
        $this->travel(1)->days();
        $this->assertSame(15 * 60, $kofi->fresh()->remainingSecondsToday());
    }

    public function test_the_admin_publishes_hides_and_replies_without_changing_the_text(): void
    {
        $kofi = $this->assessed();
        $this->send($kofi, 1, 'Je ne suis pas content du tout, rien ne marche chez moi.');
        $review = $kofi->review;
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.reviews.index'))->assertOk()
            ->assertSee('rien ne marche chez moi')->assertSee('Note très basse')->assertSee('À vérifier');
        $this->post(route('admin.reviews.publish', $review))->assertRedirect();
        $this->post(route('admin.reviews.reply', $review), ['reply' => 'Merci, écrivez-nous sur WhatsApp : nous réglons cela avec vous.']);
        $this->assertTrue($review->fresh()->isPublished());
        $this->assertSame('Je ne suis pas content du tout, rien ne marche chez moi.', $review->fresh()->body);

        $this->get(route('reviews.index'))->assertSee('rien ne marche chez moi')->assertSee('Réponse de l\'équipe', false);

        $this->post(route('admin.reviews.hide', $review), ['confirmed' => '1']);
        $this->assertSame(ReviewStatus::Hidden, $review->fresh()->status);
        $this->get(route('reviews.index'))->assertDontSee('rien ne marche chez moi');

        // Un avis retiré, puis modifié par l'élève, repasse par l'administrateur.
        $this->send($kofi, 5, 'Mon souci est réglé, merci à l\'équipe pour son aide rapide.');
        $this->assertSame(ReviewStatus::Pending, $review->fresh()->status);
    }

    public function test_assistants_never_moderate_reviews(): void
    {
        $assistant = User::factory()->create();
        $assistant->forceFill(['role' => Role::Assistant])->save();

        $this->actingAs($assistant)->get(route('admin.reviews.index'))->assertForbidden();
    }

    public function test_invitations_on_dashboard_and_result_page_disappear_once_reviewed(): void
    {
        $kofi = $this->assessed();
        $this->actingAs($kofi)->get(route('dashboard'))->assertSee('Votre avis compte')->assertSee('minutes de séance offertes');
        $this->get(route('assessment.show'))->assertSee('Votre avis compte');

        $this->send($kofi, 5);
        $this->get(route('dashboard'))->assertDontSee('Votre avis compte')->assertSee('Modifier mon avis');
    }

    public function test_the_teacher_asks_once_at_the_end_of_the_third_session(): void
    {
        $user = $this->subscriber();
        $this->heldLesson($user);
        $this->heldLesson($user);
        $third = $user->lessons()->create([
            'subscription_id' => $user->activeSubscription->id, 'date' => today(), 'allowed_seconds' => 900,
            'used_seconds' => 0, 'started_at' => now(), 'status' => LessonStatus::InProgress,
        ]);

        $note = ReviewAsk::note($user, $third);
        $this->assertStringContainsString('laissez votre avis dans Mon espace', $note);
        $this->assertStringContainsString('« À demain ! »', $note);
        $this->assertStringContainsString('laissez votre avis', SessionEnd::client('lesson', 900, $note)['conclude']);

        // Pas à la 2e ni à la 4e séance, ni après un avis.
        $this->assertSame('', ReviewAsk::note($this->subscriber('Yao Kodjo'), $third));
        $this->heldLesson($user);
        $this->assertSame('', ReviewAsk::note($user, $third));
    }

    public function test_home_shows_reviews_only_from_a_few_published(): void
    {
        $this->get('/')->assertDontSee('Ce que disent nos élèves');

        foreach (['Kofi Mensah', 'Ama Koffi', 'Afi Doe', 'Yao Kodjo', 'Esi Adzo'] as $name) {
            $this->send($this->assessed($name), 5);
        }
        auth()->logout();

        $this->get('/')->assertSee('Ce que disent nos élèves')->assertSee('Voir tous les avis');
        $this->assertNull(Reviews::stats()['average']);
    }
}
