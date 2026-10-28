<?php

namespace Tests\Feature\Ambassadors;

use App\Models\Ambassador;
use App\Models\AmbassadorReferral;
use App\Models\User;
use App\Support\Ambassadors\AmbassadorProgram;
use App\Support\Ambassadors\AmbassadorReferrals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Programme d'ambassadeurs, étape 2 : suivi des inscrits. Lien gardé 30 jours (le premier l'emporte), code tapé à
 * l'inscription qui passe avant tout, signaux de triche, espace ambassadeur (prénom et date seulement),
 * administration (inscrits, signaux, correction), provenance.
 */
class AmbassadorReferralTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->freezeSecond();
    }

    private function ambassador(string $name = 'Kofi Mensah'): Ambassador
    {
        return AmbassadorProgram::appoint(User::factory()->create(['name' => $name]), $this->admin)['ambassador'];
    }

    /** @param array<string, mixed> $data */
    private function register(array $data = [])
    {
        return $this->post('/inscription', $data + ['name' => 'Ama Koffi', 'phone' => '91 23 45 67', 'password' => 'motdepasse']);
    }

    private function newUser(): User
    {
        return User::query()->where('phone', '+22891234567')->firstOrFail();
    }

    /** Cookie du lien tel que le site le pose (code et date). */
    private function withLink(Ambassador $ambassador, ?int $at = null): static
    {
        return $this->withCookie(AmbassadorReferrals::LINK_COOKIE, json_encode(['code' => $ambassador->code, 'at' => $at ?? now()->timestamp]));
    }

    public function test_the_personal_link_remembers_the_code_for_thirty_days_then_leads_home(): void
    {
        $ambassador = $this->ambassador();

        // Un code inconnu ne laisse rien.
        $this->get('/a/INCONNU99')->assertRedirect(route('home'))->assertCookieMissing(AmbassadorReferrals::LINK_COOKIE);

        $this->get('/a/'.strtolower($ambassador->code))
            ->assertRedirect(route('home'))
            ->assertCookie(AmbassadorReferrals::LINK_COOKIE);
    }

    public function test_the_first_link_wins_over_a_later_one(): void
    {
        $first = $this->ambassador('Kofi');
        $second = $this->ambassador('Afi');

        $this->withLink($first)->get('/a/'.$second->code)->assertCookieMissing(AmbassadorReferrals::LINK_COOKIE);

        $this->withLink($first)->register()->assertRedirect(route('dashboard'));

        $referral = $this->newUser()->referral;
        $this->assertTrue($referral->ambassador->is($first));
        $this->assertSame('link', $referral->source);
    }

    public function test_an_expired_link_is_forgotten(): void
    {
        $ambassador = $this->ambassador();

        $this->withLink($ambassador, now()->subDays(31)->timestamp)->register();

        $this->assertNull($this->newUser()->referral);
    }

    public function test_the_typed_code_comes_before_the_link(): void
    {
        $linked = $this->ambassador('Kofi');
        $typed = $this->ambassador('Afi');

        $this->withLink($linked)->register(['ambassador_code' => ' '.strtolower($typed->code).' '])->assertRedirect(route('dashboard'));

        $referral = $this->newUser()->referral;
        $this->assertTrue($referral->ambassador->is($typed));
        $this->assertSame('code', $referral->source);
    }

    public function test_the_registration_form_offers_the_code_of_the_link_and_hides_the_field_otherwise(): void
    {
        $ambassador = $this->ambassador();

        $this->get('/inscription')->assertSee('Vous avez un code ambassadeur ?')->assertDontSee('value="'.$ambassador->code.'"', false);
        $this->withLink($ambassador)->get('/inscription')->assertSee('value="'.$ambassador->code.'"', false)->assertSee('<details class="ambassador-field"  open', false);
    }

    public function test_an_unknown_or_removed_code_is_refused_clearly(): void
    {
        $this->register(['ambassador_code' => 'NIMPORTE12'])
            ->assertSessionHasErrors(['ambassador_code' => 'Ce code ambassadeur n\'existe pas. Vérifiez-le, ou laissez le champ vide.']);
        $this->assertSame(0, User::query()->where('phone', '+22891234567')->count());

        $ambassador = $this->ambassador();
        AmbassadorProgram::remove($ambassador);
        $this->register(['ambassador_code' => $ambassador->code])->assertSessionHasErrors('ambassador_code');
    }

    public function test_a_removed_ambassador_link_no_longer_counts(): void
    {
        $ambassador = $this->ambassador();
        AmbassadorProgram::remove($ambassador);

        $this->withLink($ambassador)->register()->assertRedirect(route('dashboard'));

        $this->assertNull($this->newUser()->referral);
    }

    public function test_a_registration_without_link_or_code_is_attributed_to_no_one(): void
    {
        $this->ambassador();
        $this->register()->assertRedirect(route('dashboard'));

        $this->assertSame(0, AmbassadorReferral::query()->count());
    }

    public function test_a_logged_in_ambassador_opening_his_own_link_is_ignored(): void
    {
        $ambassador = $this->ambassador();

        $this->actingAs($ambassador->user)->get('/a/'.$ambassador->code)->assertCookieMissing(AmbassadorReferrals::LINK_COOKIE);
    }

    public function test_cheating_signals_are_noted_without_blocking(): void
    {
        $ambassador = $this->ambassador();

        // L'ambassadeur se connecte depuis ce téléphone : repère de l'appareil et empreinte du réseau.
        $this->post('/connexion', ['phone' => $ambassador->user->phone, 'password' => 'password'])
            ->assertCookie(AmbassadorReferrals::DEVICE_COOKIE, (string) $ambassador->id);
        $this->assertNotNull($ambassador->fresh()->last_ip_hash);
        $this->post('/deconnexion');

        // Même téléphone, même réseau : l'inscription passe, mais elle est signalée.
        $this->withCookie(AmbassadorReferrals::DEVICE_COOKIE, (string) $ambassador->id)
            ->register(['ambassador_code' => $ambassador->code])
            ->assertRedirect(route('dashboard'));

        $referral = $this->newUser()->referral;
        $this->assertSame(['same_device', 'same_network'], $referral->flags);
        $this->assertNotNull($referral->ip_hash);
        $this->assertNotSame('127.0.0.1', $referral->ip_hash);

        // Une seconde inscription depuis le même réseau : signalée aussi.
        $this->post('/deconnexion');
        $this->post('/inscription', ['name' => 'Yao', 'phone' => '92 00 00 01', 'password' => 'motdepasse', 'ambassador_code' => $ambassador->code]);
        $this->assertContains('shared_network', User::query()->where('phone', '+22892000001')->firstOrFail()->referral->flags);
    }

    public function test_the_ambassador_sees_first_names_and_dates_only(): void
    {
        $ambassador = $this->ambassador();
        $this->withLink($ambassador)->register(['name' => 'Ama Koffi Mensah']);
        $this->post('/deconnexion');

        $this->actingAs($ambassador->user)->get(route('ambassador.show'))
            ->assertOk()
            ->assertSee('Vos inscrits : 1')
            ->assertSee('Ama')
            ->assertDontSee('Ama Koffi Mensah')
            ->assertDontSee('91 23 45 67')
            ->assertDontSee('+22891234567');
    }

    public function test_the_administrator_sees_referrals_signals_and_can_correct_them(): void
    {
        $kofi = $this->ambassador('Kofi');
        $afi = $this->ambassador('Afi');
        $this->withLink($kofi)->register();
        $learner = $this->newUser();
        AmbassadorReferral::query()->where('user_id', $learner->id)->update(['flags' => json_encode(['same_device'])]);

        $this->actingAs($this->admin)->get(route('admin.ambassadors.index'))->assertSee('1 à vérifier', false);
        $this->actingAs($this->admin)->get(route('admin.users.show', $kofi->user))
            ->assertSee('Ses inscrits : 1')
            ->assertSee('Ama Koffi')
            ->assertSee('Inscrit depuis le téléphone ou l\'ordinateur de l\'ambassadeur');
        $this->actingAs($this->admin)->get(route('admin.users.show', $learner))
            ->assertSee('Inscrit grâce à Kofi ('.$kofi->code.')');

        $this->actingAs($this->admin)->put(route('admin.users.referral', $learner), ['ambassador_id' => $afi->id])->assertSessionHasNoErrors();
        $this->assertTrue($learner->fresh()->referral->ambassador->is($afi));
        $this->assertSame('admin', $learner->fresh()->referral->source);

        $this->actingAs($this->admin)->put(route('admin.users.referral', $learner), ['ambassador_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($learner->fresh()->referral);

        // Jamais rattaché à lui-même ; jamais par un apprenant.
        $this->actingAs($this->admin)->put(route('admin.users.referral', $kofi->user), ['ambassador_id' => $kofi->id])->assertSessionHasErrors('ambassador');
        $this->actingAs($learner)->put(route('admin.users.referral', $learner), ['ambassador_id' => $kofi->id])->assertForbidden();
    }

    public function test_the_origin_counts_the_ambassador_channel_and_code(): void
    {
        $ambassador = $this->ambassador();

        $this->get('/a/'.$ambassador->code);
        $this->register();

        $origin = $this->newUser()->origin;
        $this->assertSame('ambassador', $origin->channel);
        $this->assertSame($ambassador->code, $origin->utm_campaign);
    }
}
