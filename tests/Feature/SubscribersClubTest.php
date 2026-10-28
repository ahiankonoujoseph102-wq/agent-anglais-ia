<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AccessGrant;
use App\Support\Ambassadors\AmbassadorProgram;
use App\Support\Plan;
use App\Support\SiteSettings;
use App\Support\SubscribersClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Club des abonnés : lien du groupe WhatsApp, réglé dans « Réglages », montré seulement aux abonnés payants actifs.
 */
class SubscribersClubTest extends TestCase
{
    use RefreshDatabase;

    private const LINK = 'https://chat.whatsapp.com/LIwa0gAQrc4FwPbJBnhrlm';

    private function learner(): User
    {
        return User::factory()->create(['welcomed_at' => now()]);
    }

    private function payer(): User
    {
        $user = $this->learner();
        Subscription::factory()->for($user)->forPlan('essentiel')->create();

        return $user;
    }

    public function test_the_starting_link_is_set_and_shown_to_an_active_paying_subscriber(): void
    {
        $this->assertSame(self::LINK, SubscribersClub::link());

        $this->actingAs($this->payer())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="club-des-abonnes"', false)
            ->assertSee('Merci d\'avoir choisi '.config('platform.name').' pour apprendre l\'anglais 🙏🇺🇸', false)
            ->assertSee('J\'ai créé un groupe WhatsApp réservé uniquement à nos abonnés : le Club des abonnés '.config('platform.name').'.', false)
            ->assertSee('✅ on pratique l\'anglais ensemble ;', false)
            ->assertSee('✅ on partage nos progrès avec '.config('avatar.model.name').' ;')
            ->assertSee('✅ vous recevez en premier les nouveautés et les astuces pour progresser plus vite.')
            ->assertSee('Rejoindre le Club des abonnés')
            ->assertSee('href="'.self::LINK.'"', false)
            ->assertSee('Coach '.config('platform.founder.name').', fondateur de '.config('platform.name'));
    }

    public function test_free_provisional_ended_and_unsubscribed_accounts_never_see_the_link(): void
    {
        $admin = User::factory()->admin()->create();
        $nobody = $this->learner();
        $ended = $this->learner();
        Subscription::factory()->for($ended)->expired()->create(['status' => SubscriptionStatus::Expired]);
        $provisional = $this->learner();
        Subscription::factory()->for($provisional)->create(['status' => SubscriptionStatus::Provisional, 'provisional_until' => now()->addHours(20)]);
        $offered = $this->learner();
        AccessGrant::offer($offered, $admin, Plan::find('intensif'), 7, null);
        $ambassador = $this->learner();
        AmbassadorProgram::appoint($ambassador, $admin);

        foreach ([$nobody, $ended, $provisional, $offered, $ambassador->fresh()] as $user) {
            $this->assertFalse(SubscribersClub::isMember($user->fresh()));
            $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk()
                ->assertDontSee('Rejoindre le Club des abonnés')->assertDontSee(self::LINK);
        }
    }

    public function test_an_empty_field_hides_the_box(): void
    {
        SiteSettings::save([SubscribersClub::SETTING => '']);

        $this->assertNull(SubscribersClub::link());
        $this->actingAs($this->payer())->get(route('dashboard'))->assertOk()->assertDontSee('Rejoindre le Club des abonnés');
    }

    public function test_the_validated_payment_page_shows_the_invitation(): void
    {
        $user = $this->payer();
        $subscription = $user->subscriptions()->first();
        $payment = Payment::query()->forceCreate([
            'user_id' => $user->id, 'subscription_id' => $subscription->id, 'plan' => 'essentiel', 'plan_name' => 'Essentiel',
            'daily_minutes' => 15, 'duration_days' => 30, 'amount' => 7500, 'currency' => 'XOF', 'country' => 'TG', 'operator' => 'flooz',
            'operator_name' => 'Flooz', 'recipient_number' => '+22890000000', 'proof_path' => 'proofs/a.jpg',
            'status' => PaymentStatus::Validated, 'reviewed_at' => now(),
        ]);

        $this->actingAs($user)->get(route('payment.show', $payment))->assertOk()
            ->assertSeeInOrder(['Paiement validé', 'Aller à mon espace', 'Rejoindre le Club des abonnés']);
    }

    public function test_the_admin_changes_or_empties_the_link_in_the_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('id="club-des-abonnes"', false)
            ->assertSee('Lien du groupe WhatsApp des abonnés')
            ->assertSee(self::LINK);

        $this->actingAs($admin)->put(route('admin.settings.update'), ['club' => ['whatsapp_link' => 'https://chat.whatsapp.com/NouveauLien123']])->assertRedirect();
        SiteSettings::flush();
        $this->assertSame('https://chat.whatsapp.com/NouveauLien123', SubscribersClub::link());

        $this->actingAs($admin)->put(route('admin.settings.update'), ['club' => ['whatsapp_link' => 'https://exemple.com/piege']])
            ->assertSessionHasErrors('club.whatsapp_link');

        $this->actingAs($admin)->put(route('admin.settings.update'), ['club' => ['whatsapp_link' => '']])->assertRedirect();
        SiteSettings::flush();
        $this->assertNull(SubscribersClub::link());

        // Un formulaire sans ce champ ne touche pas au lien.
        SiteSettings::save([SubscribersClub::SETTING => self::LINK]);
        $this->actingAs($admin)->put(route('admin.settings.update'), ['contact' => ['email' => 'contact@talkami.net']])->assertRedirect();
        SiteSettings::flush();
        $this->assertSame(self::LINK, SubscribersClub::link());
    }
}
