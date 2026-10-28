<?php

namespace Tests\Feature\Ambassadors;

use App\Enums\ApplicationStatus;
use App\Models\AdminAlert;
use App\Models\Ambassador;
use App\Models\AmbassadorApplication;
use App\Models\AmbassadorWaitlistEntry;
use App\Models\Setting;
use App\Models\User;
use App\Support\Alerts\AlertBuilder;
use App\Support\Ambassadors\AmbassadorApplications;
use App\Support\Ambassadors\AmbassadorProgram;
use App\Support\Money;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Programme d'ambassadeurs, étape 4 : page « Devenir ambassadeur » (compteur réel, candidature avec un compte,
 * formulaire fermé quand tout est pris, liste d'attente), candidatures et liste d'attente dans l'administration.
 */
class AmbassadorJoinTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Équipe']);
        $this->setPlaces(3);
    }

    private function setPlaces(int $places): void
    {
        Setting::put(AmbassadorProgram::PLACES_SETTING, (string) $places);
        SiteSettings::flush();
    }

    private function ambassador(string $name): Ambassador
    {
        $user = User::factory()->create(['name' => $name]);

        return AmbassadorProgram::appoint($user, $this->admin)['ambassador'];
    }

    /**
     * @return array<string, mixed>
     */
    private function form(array $data = []): array
    {
        return $data + [
            'motivation' => 'J\'aime Talkami et je veux aider mes camarades à parler anglais.',
            'channels' => 'Ma classe et mon église',
            'rules' => '1',
        ];
    }

    public function test_the_counter_is_the_real_number_of_free_places(): void
    {
        $this->get(route('ambassador.join'))
            ->assertOk()
            ->assertSee('Devenir ambassadeur de '.config('platform.name'))
            ->assertSee('Il reste 3 places sur 3.')
            ->assertSee('<meta name="robots" content="index, follow">', false);

        $this->ambassador('Kofi');
        $removed = $this->ambassador('Afi');
        $this->get(route('ambassador.join'))->assertSee('Il reste 1 place sur 3.');

        // Un ambassadeur retiré libère sa place.
        AmbassadorProgram::remove($removed);
        $this->get(route('ambassador.join'))->assertSee('Il reste 2 places sur 3.');

        // Les montants viennent de la configuration (20 % du prix de chaque formule).
        $this->get(route('ambassador.join'))
            ->assertSee(Money::format(400))
            ->assertSee(Money::format(1500))
            ->assertSee(Money::format(2800));
    }

    public function test_a_guest_needs_an_account_and_comes_back_after_logging_in(): void
    {
        $this->get(route('ambassador.join'))
            ->assertSee('il faut un compte '.config('platform.name'))
            ->assertSee(route('ambassador.join.form'))
            ->assertDontSee('Envoyer ma candidature');

        $this->get(route('ambassador.join.form'))->assertRedirect(route('login'));
        $this->post(route('ambassador.apply'), $this->form())->assertRedirect(route('login'));
        $this->assertSame(0, AmbassadorApplication::query()->count());

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('ambassador.join.form'))->assertRedirect(route('ambassador.join').'#candidature');
    }

    public function test_a_learner_applies_once_and_the_admin_is_alerted(): void
    {
        $user = User::factory()->create(['name' => 'Ama Koffi']);

        $this->actingAs($user)->get(route('ambassador.join'))->assertSee('Envoyer ma candidature');

        $this->actingAs($user)->post(route('ambassador.apply'), $this->form(['rules' => null]))
            ->assertSessionHasErrors(['rules' => 'Cochez la case pour accepter les règles du programme.']);
        $this->actingAs($user)->post(route('ambassador.apply'), $this->form(['motivation' => 'Court']))
            ->assertSessionHasErrors('motivation');

        $this->actingAs($user)->post(route('ambassador.apply'), $this->form(['minor' => '1']))
            ->assertRedirect(route('ambassador.join').'#candidature')
            ->assertSessionHas('status');

        $application = $user->ambassadorApplication()->firstOrFail();
        $this->assertSame(ApplicationStatus::Pending, $application->status);
        $this->assertTrue($application->minor);

        $this->actingAs($user)->get(route('ambassador.join'))
            ->assertSee('Votre candidature est envoyée.')
            ->assertDontSee('Envoyer ma candidature');

        // Une seconde candidature pendant l'examen est refusée.
        $this->actingAs($user)->post(route('ambassador.apply'), $this->form())->assertSessionHasErrors('application');
        $this->assertSame(1, AmbassadorApplication::query()->count());

        $alert = AlertBuilder::ambassadorApplication($application);
        $this->assertSame('ambassador_application', $alert->kind);
        $this->assertArrayHasKey('ambassador_application', AdminAlert::KINDS);
        $this->assertStringContainsString('moins de 18 ans', $alert->body);
        $this->assertStringNotContainsString($user->phone, $alert->body);
    }

    public function test_the_admin_accepts_or_rejects_an_application(): void
    {
        $kofi = User::factory()->create(['name' => 'Kofi Mensah']);
        $afi = User::factory()->create(['name' => 'Afi Doe']);
        $this->actingAs($kofi)->post(route('ambassador.apply'), $this->form());
        $this->actingAs($afi)->post(route('ambassador.apply'), $this->form(['minor' => '1']));

        $this->actingAs($this->admin)->get(route('admin.ambassadors.index'))
            ->assertSee('Candidatures à examiner : 2')
            ->assertSee('Kofi Mensah')
            ->assertSee('Ma classe et mon église')
            ->assertSee('Moins de 18 ans');

        $application = $kofi->ambassadorApplication()->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.applications.accept', $application), ['confirmed' => 1])
            ->assertRedirect(route('admin.ambassadors.index').'#candidatures')
            ->assertSessionHas('status');
        $this->assertTrue($kofi->fresh()->isAmbassador());
        $this->assertSame(ApplicationStatus::Accepted, $application->fresh()->status);

        // Déjà traitée : rien de plus.
        $this->actingAs($this->admin)->post(route('admin.applications.accept', $application), ['confirmed' => 1])->assertSessionHasErrors('ambassador');

        $other = $afi->ambassadorApplication()->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.applications.reject', $other), ['confirmed' => 1])->assertSessionHas('status');
        $this->assertSame(ApplicationStatus::Rejected, $other->fresh()->status);
        $this->assertFalse($afi->fresh()->isAmbassador());

        // Après un refus, une nouvelle candidature est possible.
        $this->actingAs($afi)->get(route('ambassador.join'))->assertSee('n\'a pas été retenue', false);
        $this->actingAs($afi)->post(route('ambassador.apply'), $this->form())->assertSessionHasNoErrors();
        $this->assertSame(ApplicationStatus::Pending, $other->fresh()->status);

        // Un ambassadeur voit le lien vers son espace.
        $this->actingAs($kofi)->get(route('ambassador.join'))->assertSee('Ouvrir mon espace ambassadeur');

        // Les candidatures demandent une confirmation et sont réservées à l'administrateur.
        $this->actingAs($afi)->post(route('admin.applications.accept', $other), ['confirmed' => 1])->assertForbidden();
    }

    public function test_when_all_places_are_taken_the_form_is_closed_and_a_waitlist_opens(): void
    {
        $this->setPlaces(1);
        $this->ambassador('Kofi');
        $user = User::factory()->create(['name' => 'Yao Agbo']);

        $this->get(route('ambassador.join'))
            ->assertSee('Toutes les places d\'ambassadeurs sont prises.', false)
            ->assertSee('Me connecter pour être prévenu')
            ->assertDontSee('Il reste');

        $this->actingAs($user)->get(route('ambassador.join'))
            ->assertSee('Toutes les places d\'ambassadeurs sont prises.', false)
            ->assertSee('Me prévenir quand une place se libère')
            ->assertSee(route('plans'))
            ->assertDontSee('Envoyer ma candidature');

        // Formulaire fermé : la candidature est refusée même envoyée à la main.
        $this->actingAs($user)->post(route('ambassador.apply'), $this->form())
            ->assertSessionHasErrors(['application' => 'Toutes les places d\'ambassadeurs sont prises.']);
        $this->assertSame(0, AmbassadorApplication::query()->count());

        $this->actingAs($user)->post(route('ambassador.waitlist'))->assertSessionHas('status');
        $this->actingAs($user)->post(route('ambassador.waitlist'))->assertSessionHasErrors('application');
        $this->assertSame(1, AmbassadorWaitlistEntry::query()->count());
        $this->actingAs($user)->get(route('ambassador.join'))->assertSee('Vous êtes sur la liste d\'attente.', false);

        $this->actingAs($this->admin)->get(route('admin.ambassadors.index'))
            ->assertSee('Ambassadeurs actifs : 1 sur 1')
            ->assertSee('Liste d\'attente : 1', false)
            ->assertSee('Yao Agbo');

        // Une place se libère : l'administrateur est invité à prévenir la liste, par WhatsApp.
        $this->setPlaces(2);
        $entry = AmbassadorWaitlistEntry::query()->firstOrFail();
        $this->actingAs($this->admin)->get(route('admin.ambassadors.index'))->assertSee('Une place est libre');
        $this->actingAs($this->admin)->get(route('admin.waitlist.notify', $entry))
            ->assertRedirect(AmbassadorApplications::waitlistWhatsAppUrl($user));
        $this->assertNotNull($entry->fresh()->notified_at);
        $this->assertStringContainsString(route('ambassador.join'), AmbassadorApplications::waitlistMessage($user));

        // La personne de la liste peut poser sa candidature ; nommée, elle quitte la liste.
        $this->actingAs($user)->post(route('ambassador.apply'), $this->form())->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.applications.accept', $user->ambassadorApplication()->firstOrFail()), ['confirmed' => 1]);
        $this->assertTrue($user->fresh()->isAmbassador());
        $this->assertSame(0, AmbassadorWaitlistEntry::query()->count());
    }

    public function test_leaving_and_removing_from_the_waitlist(): void
    {
        $this->setPlaces(0);
        $user = User::factory()->create(['name' => 'Yao Agbo']);
        $this->actingAs($user)->post(route('ambassador.waitlist'));

        $this->actingAs($user)->delete(route('ambassador.waitlist.leave'))->assertSessionHas('status');
        $this->assertSame(0, AmbassadorWaitlistEntry::query()->count());

        $this->actingAs($user)->post(route('ambassador.waitlist'));
        $entry = AmbassadorWaitlistEntry::query()->firstOrFail();
        $this->actingAs($this->admin)->delete(route('admin.waitlist.remove', $entry))
            ->assertRedirect(route('admin.ambassadors.index').'#liste-d-attente');
        $this->assertSame(0, AmbassadorWaitlistEntry::query()->count());

        // Ni candidature ni liste d'attente pour un administrateur.
        $this->actingAs($this->admin)->get(route('ambassador.join'))->assertSee('Voir les candidatures');
        $this->actingAs($this->admin)->post(route('ambassador.waitlist'))->assertSessionHasErrors('application');
    }
}
