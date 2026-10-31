<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Duration;
use App\Support\FriendInvite;
use App\Support\Reminders\LessonReminders;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Revue des pages d'un élève payant (31 octobre 2026), avec Teacher Joe et avec Teacher Rose : prénom seulement,
 * heures et jours sans abréviation, professeur de l'élève partout (invitation d'un ami comprise), texte de secours
 * du programme sans faute, lien pour renouveler ou changer de formule à tout moment.
 */
class PayingLearnerPagesTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    private function learner(?string $teacher = null): User
    {
        $user = User::factory()->level(Level::A2)->create(['name' => 'Afi Mensah', 'email' => fake()->unique()->safeEmail()]);
        $user->forceFill(['teacher' => $teacher, 'welcomed_at' => now()])->save();
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A2);

        return $user->fresh();
    }

    public function test_my_space_greets_by_first_name_and_writes_days_and_hours_in_full(): void
    {
        $page = $this->actingAs($this->learner('rose'))->get('/espace')->assertOk();

        $page->assertSee('Bonjour Afi')
            ->assertDontSee('Bonjour Afi Mensah')
            ->assertSee('Vos séances : tous les jours, à <strong>18 heures</strong>.', false)
            ->assertDontSee(' h 00')
            // Carte « Votre professeur » : jamais « Votre professeur » au masculin à côté de Teacher Rose.
            ->assertSee('<strong>Teacher Rose</strong> <span class="muted">Votre choix</span>', false)
            ->assertSee('Renouveler ou changer')
            ->assertSee('Invitez-le à passer l\'évaluation orale gratuite avec Teacher Joe ou Teacher Rose.', false);
    }

    public function test_hours_and_dates_never_use_abbreviations(): void
    {
        $this->assertSame('18 heures', LessonReminders::timeLabel('18:00'));
        $this->assertSame('7 heures 30', LessonReminders::timeLabel('07:30'));
        $this->assertSame('1 heure 05', LessonReminders::timeLabel('01:05'));
        $this->assertSame('2 novembre 2026 à 11 heures 15', Duration::at(Carbon::parse('2026-11-02 11:15')));
        $this->assertSame('2 novembre à 9 heures', Duration::at(Carbon::parse('2026-11-02 09:00'), false));
    }

    public function test_the_program_presentation_has_no_grammar_slip_and_uses_the_first_name(): void
    {
        $intro = $this->learner()->activeProgram()->first()->content['intro'];

        $this->assertStringContainsString('Bienvenue dans votre programme A2, Afi !', $intro);
        $this->assertStringNotContainsString('liées à le', $intro);
        $this->assertStringContainsString('Nous travaillerons ensemble tous les jours', $intro);
        $this->actingAs($this->learner())->get('/programme')->assertOk()->assertSee('vers 18 heures')->assertDontSee(' h 00');
    }

    public function test_the_friend_invitation_names_the_learners_own_teacher(): void
    {
        $this->assertStringContainsString('avec Teacher Rose, la professeure de Talkami', FriendInvite::message($this->learner('rose')));
        $this->assertStringContainsString('avec Teacher Joe, le professeur de Talkami', FriendInvite::message($this->learner()));
        $this->assertStringContainsString('avec Teacher Joe, le professeur de Talkami', FriendInvite::message());
    }

    public function test_the_daily_lesson_page_names_her_and_a_granted_access_offers_a_paid_plan(): void
    {
        $this->actingAs($this->learner('rose'))->get('/seance')->assertOk()
            ->assertSee('Teacher Rose vous annoncera la durée au début')
            ->assertDontSee('La professeure vous annoncera');

        $granted = User::factory()->create();
        Subscription::factory()->for($granted)->forPlan('essentiel')->create(['price' => 0, 'granted_days' => 7]);
        $this->actingAs($granted)->get('/espace')->assertOk()->assertSee('Choisir une formule')->assertDontSee('Renouveler ou changer');
    }
}
