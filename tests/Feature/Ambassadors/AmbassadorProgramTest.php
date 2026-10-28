<?php

namespace Tests\Feature\Ambassadors;

use App\Enums\AmbassadorStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Ambassador;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Ambassadors\AmbassadorCode;
use App\Support\Ambassadors\AmbassadorProgram;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Programme d'ambassadeurs, étape 1 : nomination (places réglables), mois gratuit renouvelé en un clic (6 au
 * plus), retrait qui libère la place, accord d'un parent, onglet de l'administration.
 */
class AmbassadorProgramTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Équipe']);
        $this->freezeSecond();
    }

    private function appoint(User $user, array $data = [])
    {
        return $this->actingAs($this->admin)->post(route('admin.users.ambassador', $user), $data + ['confirmed' => 1]);
    }

    private function ambassador(string $name = 'Kofi Mensah'): Ambassador
    {
        $user = User::factory()->create(['name' => $name]);
        $this->appoint($user)->assertSessionHasNoErrors();

        return $user->ambassador()->firstOrFail();
    }

    public function test_appointing_creates_a_personal_code_and_opens_a_free_essentiel_month(): void
    {
        $user = User::factory()->create(['name' => 'Kofi Mensah']);

        $this->appoint($user)
            ->assertRedirect(route('admin.users.show', $user).'#ambassadeur')
            ->assertSessionHas('status');

        $ambassador = $user->ambassador()->firstOrFail();
        $this->assertMatchesRegularExpression('/^KOFI\d{2}$/', $ambassador->code);
        $this->assertSame(AmbassadorStatus::Active, $ambassador->status);
        $this->assertSame(1, $ambassador->months_granted);

        $grant = $user->subscriptions()->sole();
        $this->assertSame('essentiel', $grant->plan);
        $this->assertSame(0, $grant->price);
        $this->assertSame($ambassador->id, $grant->ambassador_id);
        $this->assertTrue($grant->isGranted());
        $this->assertTrue($grant->ends_at->equalTo(now()->addDays(30)));
        $this->assertTrue($user->fresh()->activeSubscription->is($grant));
        // Jamais un paiement ni un revenu.
        $this->assertSame(0, $user->payments()->count());
    }

    public function test_appointing_needs_a_confirmation_and_an_administrator(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.users.ambassador', $user))->assertOk()->assertSee('Faire de ce compte un ambassadeur ?');
        $this->assertNull($user->ambassador()->first());

        $this->actingAs(User::factory()->create())->post(route('admin.users.ambassador', $user), ['confirmed' => 1])->assertForbidden();
        $this->assertNull($user->ambassador()->first());
    }

    public function test_renewing_extends_the_current_month_until_six_months(): void
    {
        $ambassador = $this->ambassador();
        $grant = $ambassador->grants()->sole();

        for ($month = 2; $month <= 6; $month++) {
            $this->actingAs($this->admin)
                ->post(route('admin.ambassadors.renew', $ambassador), ['confirmed' => 1, 'from' => 'list'])
                ->assertRedirect(route('admin.ambassadors.index').'#liste')
                ->assertSessionHasNoErrors();
        }

        $grant->refresh();
        $this->assertSame(6, $ambassador->fresh()->months_granted);
        $this->assertSame(1, $ambassador->grants()->count());
        $this->assertTrue($grant->ends_at->equalTo(now()->addDays(180)));
        $this->assertSame(180, $grant->granted_days);

        // Septième mois : refusé, avec un message clair.
        $this->actingAs($this->admin)
            ->post(route('admin.ambassadors.renew', $ambassador), ['confirmed' => 1])
            ->assertSessionHasErrors(['ambassador' => 'Les 6 mois gratuits ont déjà tous été offerts à cet ambassadeur.']);
        $this->assertSame(6, $ambassador->fresh()->months_granted);
    }

    public function test_renewing_after_the_end_of_the_month_opens_a_new_one_from_now(): void
    {
        $ambassador = $this->ambassador();
        $this->travel(31)->days();

        $this->actingAs($this->admin)->post(route('admin.ambassadors.renew', $ambassador), ['confirmed' => 1])->assertSessionHasNoErrors();

        $this->assertSame(2, $ambassador->grants()->count());
        $this->assertTrue($ambassador->currentGrant()->ends_at->equalTo(now()->addDays(30)));
        $this->assertSame(2, $ambassador->fresh()->months_granted);
    }

    public function test_a_paid_access_in_progress_keeps_the_month_closed(): void
    {
        $user = User::factory()->create(['name' => 'Ama']);
        Subscription::factory()->for($user)->create(['plan' => 'semaine', 'price' => 2000, 'starts_at' => now(), 'ends_at' => now()->addDays(7), 'status' => SubscriptionStatus::Active]);

        $this->appoint($user)->assertSessionHasNoErrors()->assertSessionHas('status', fn (string $status) => str_contains($status, 'Mois gratuit non ouvert'));

        $ambassador = $user->ambassador()->firstOrFail();
        $this->assertTrue($ambassador->isActive());
        $this->assertSame(0, $ambassador->months_granted);
        $this->assertSame(0, $ambassador->grants()->count());
    }

    public function test_the_places_come_from_the_settings_and_appointing_is_refused_when_they_are_all_taken(): void
    {
        config(['platform.ambassadors.places' => 10]);
        $this->assertSame(10, AmbassadorProgram::places());

        Setting::put(AmbassadorProgram::PLACES_SETTING, '2');
        SiteSettings::flush();

        $this->ambassador('Kofi');
        $this->ambassador('Afi');
        $this->assertSame(0, AmbassadorProgram::freePlaces());

        $third = User::factory()->create(['name' => 'Yao']);
        $this->appoint($third)->assertSessionHasErrors(['ambassador' => 'Les 2 places d\'ambassadeurs sont toutes prises. Retirez un ambassadeur ou augmentez le nombre de places dans « Réglages ».']);
        $this->assertNull($third->ambassador()->first());

        $this->actingAs($this->admin)->get(route('admin.users.show', $third))
            ->assertSee('Toutes les places d\'ambassadeurs sont prises.', false)
            ->assertDontSee('Faire ambassadeur</button>', false);

        // Plus de places dans « Réglages » : la nomination passe.
        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['ambassadors' => ['places' => '3']])->assertSessionHasNoErrors();
        $this->assertSame(3, AmbassadorProgram::places());
        $this->appoint($third)->assertSessionHasNoErrors();
        $this->assertTrue($third->fresh()->isAmbassador());
    }

    public function test_removing_frees_the_place_and_stops_the_free_month(): void
    {
        Setting::put(AmbassadorProgram::PLACES_SETTING, '1');
        SiteSettings::flush();
        $ambassador = $this->ambassador();
        $this->assertSame(0, AmbassadorProgram::freePlaces());

        $this->actingAs($this->admin)->delete(route('admin.ambassadors.remove', $ambassador), ['confirmed' => 1])->assertSessionHasNoErrors();

        $ambassador->refresh();
        $this->assertSame(AmbassadorStatus::Removed, $ambassador->status);
        $this->assertNotNull($ambassador->removed_at);
        $this->assertNull($ambassador->currentGrant());
        $this->assertNull($ambassador->user->fresh()->activeSubscription);
        $this->assertSame(1, AmbassadorProgram::freePlaces());

        // Réintégré : même code, mois déjà offerts gardés, un nouveau mois s'ouvre.
        $code = $ambassador->code;
        $this->appoint($ambassador->user)->assertSessionHasNoErrors();
        $ambassador->refresh();
        $this->assertTrue($ambassador->isActive());
        $this->assertSame($code, $ambassador->code);
        $this->assertSame(2, $ambassador->months_granted);
    }

    public function test_an_administrator_cannot_be_an_ambassador_and_no_one_is_appointed_twice(): void
    {
        $this->appoint(User::factory()->admin()->create())->assertSessionHasErrors(['ambassador' => 'Un compte administrateur ne peut pas être ambassadeur.']);

        $ambassador = $this->ambassador();
        $this->appoint($ambassador->user)->assertSessionHasErrors(['ambassador' => 'Ce compte est déjà ambassadeur.']);
        $this->assertSame(1, Ambassador::query()->count());
    }

    public function test_the_parental_consent_is_noted_and_removed(): void
    {
        $ambassador = $this->ambassador();

        $this->actingAs($this->admin)->put(route('admin.ambassadors.consent', $ambassador), ['received' => '1'])->assertSessionHasNoErrors();
        $this->assertNotNull($ambassador->fresh()->parental_consent_at);

        $this->actingAs($this->admin)->put(route('admin.ambassadors.consent', $ambassador), ['received' => '0']);
        $this->assertNull($ambassador->fresh()->parental_consent_at);
    }

    public function test_the_admin_list_shows_the_places_months_and_daily_sessions(): void
    {
        $ambassador = $this->ambassador('Kofi Mensah');
        $ambassador->user->lessons()->create(['date' => today(), 'allowed_seconds' => 900, 'used_seconds' => 600, 'status' => 'completed']);
        $ambassador->user->lessons()->create(['date' => today()->subDays(2), 'allowed_seconds' => 900, 'used_seconds' => 30, 'status' => 'completed']);

        $this->actingAs($this->admin)->get(route('admin.ambassadors.index'))
            ->assertOk()
            ->assertSee('Ambassadeurs actifs : 1 sur 10')
            ->assertSee('Kofi Mensah')
            ->assertSee($ambassador->code)
            ->assertSee('Mois 1 sur 6, jusqu\'au')
            // Une séance de 30 secondes ne compte pas.
            ->assertSee('1 jour sur les 30 derniers')
            ->assertSee('Renouveler 1 mois');

        $this->actingAs(User::factory()->create())->get(route('admin.ambassadors.index'))->assertForbidden();
    }

    public function test_codes_are_readable_unique_and_normalized(): void
    {
        $this->assertMatchesRegularExpression('/^EDEM\d{2}$/', AmbassadorCode::generate('Édem Agbéko'));
        $this->assertMatchesRegularExpression('/^TALK\d{2}$/', AmbassadorCode::generate('A'));
        $this->assertSame('KOFI27', AmbassadorCode::normalize(' kofi-27 '));
    }
}
