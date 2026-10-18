<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\User;
use App\Support\Countries;
use App\Support\PhoneNumber;
use App\Support\Plan;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Site ouvert aux francophones de tous les pays : inscription avec n'importe quel indicatif,
 * évaluation gratuite pour tous, paiement par WhatsApp hors des pays de Mobile Money,
 * accès ouvert par l'administrateur (« Offrir un accès »), filtre « Autres pays ».
 */
class WorldwideTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pays, numéro tapé localement, numéro enregistré.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function foreignNumbers(): array
    {
        return [
            'Cameroun, numéro local' => ['CM', '6 77 12 34 56', '+237677123456'],
            'Nigeria, numéro local avec le 0' => ['NG', '0803 123 4567', '+2348031234567'],
            'France, numéro local avec le 0' => ['FR', '06 12 34 56 78', '+33612345678'],
            'Cameroun, avec l\'indicatif' => ['CM', '+237 6 77 12 34 56', '+237677123456'],
            'Nigeria, avec 00' => ['NG', '00234 803 123 4567', '+2348031234567'],
            'France, indicatif sans +' => ['FR', '33 6 12 34 56 78', '+33612345678'],
            'Côte d\'Ivoire, le 0 est gardé' => ['CI', '07 09 12 34 56', '+2250709123456'],
            'Autre pays, avec l\'indicatif' => [Countries::OTHER, '+1 202 555 0143', '+12025550143'],
        ];
    }

    #[DataProvider('foreignNumbers')]
    public function test_anyone_can_register_with_the_number_of_their_country(string $country, string $typed, string $stored): void
    {
        $this->post('/inscription', [
            'name' => 'Awa Nguema',
            'country' => $country,
            'phone' => $typed,
            'password' => 'motdepasse',
            'password_confirmation' => 'motdepasse',
        ])->assertRedirect(route('dashboard'));

        $user = User::firstOrFail();
        $this->assertSame($stored, $user->phone);
        $this->assertSame($country, $user->country);

        // On se reconnecte avec le même numéro, avec ou sans indicatif.
        $this->post('/deconnexion');
        $this->post('/connexion', ['phone' => $stored, 'password' => 'motdepasse'])->assertRedirect(route('dashboard'));
    }

    public function test_togolese_numbers_and_existing_accounts_are_unchanged(): void
    {
        $this->assertSame('+22890123456', PhoneNumber::normalize('90 12 34 56'));
        $this->assertSame('+22890123456', PhoneNumber::normalize('90 12 34 56', 'TG'));
        $this->assertSame('+22670123456', PhoneNumber::normalize('+226 70 12 34 56', 'CM'));

        $this->post('/inscription', [
            'name' => 'Kossi', 'country' => 'TG', 'phone' => '90 12 34 56',
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame('+22890123456', User::firstOrFail()->phone);
    }

    public function test_the_registration_form_lists_every_country_francophone_first(): void
    {
        $html = $this->get('/inscription')->assertOk()->getContent();

        $this->assertStringContainsString('name="country"', $html);
        $this->assertStringContainsString('accueille les francophones de tous les pays', $html);

        $payment = strpos($html, 'Pays où l\'on peut payer par Mobile Money');
        $francophone = strpos($html, 'Autres pays francophones');
        $others = strpos($html, 'Tous les autres pays');
        $this->assertTrue($payment < $francophone && $francophone < $others);

        foreach (['Cameroun', 'Sénégal', 'France', 'Nigeria', 'Autre pays'] as $name) {
            $this->assertStringContainsString('>'.$name.'</option>', $html);
        }

        $this->assertGreaterThan(200, count(Countries::all()));
    }

    public function test_an_unknown_country_code_is_refused(): void
    {
        $this->post('/inscription', [
            'name' => 'Awa', 'country' => 'XX', 'phone' => '+237677123456',
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse',
        ])->assertSessionHasErrors('country');

        $this->assertSame(0, User::count());
    }

    public function test_the_country_comes_from_the_dial_code_when_it_is_missing(): void
    {
        $this->assertSame('TG', Countries::forPhone('+22890123456'));
        $this->assertSame('CI', Countries::forPhone('+2250709123456'));
        $this->assertSame('CM', Countries::forPhone('+237677123456'));
        $this->assertSame('NG', Countries::forPhone('+2348031234567'));
        $this->assertSame('FR', Countries::forPhone('+33612345678'));
        $this->assertSame('US', Countries::forPhone('+12025550143'));
        $this->assertNull(Countries::forPhone(null));

        $this->assertSame('CM', User::factory()->create(['phone' => '+237677123456'])->country);
        $this->assertSame('Africa/Douala', Countries::timezone('CM'));
        $this->assertSame('Autre pays', Countries::name(Countries::OTHER));
    }

    public function test_existing_accounts_receive_the_country_of_their_number(): void
    {
        $togo = User::factory()->create(['phone' => '+22890123456']);
        $cameroon = User::factory()->create(['phone' => '+237677123456']);
        DB::table('users')->update(['country' => null]);

        $migration = require database_path('migrations/2026_10_28_000001_add_country_to_users_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame('TG', $togo->fresh()->country);
        $this->assertSame('CM', $cameroon->fresh()->country);
    }

    public function test_the_free_assessment_works_from_any_country(): void
    {
        config(['services.gemini.key' => 'cle']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton'])]);
        $this->freezeSecond();

        foreach (['+237677123456' => 'CM', '+2348031234567' => 'NG', '+33612345678' => 'FR'] as $phone => $country) {
            $user = User::factory()->create(['phone' => $phone, 'country' => $country]);

            $this->actingAs($user)->get('/evaluation')->assertOk()->assertSee('Commencer');
            $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk()->assertJsonPath('provider', 'gemini');
            $this->actingAs($user)->postJson('/evaluation/fin')->assertOk();
        }
    }

    public function test_payment_outside_the_mobile_money_countries_goes_through_whatsapp(): void
    {
        $user = User::factory()->create(['name' => 'Awa Nguema', 'phone' => '+237677123456', 'country' => 'CM']);

        $html = $this->actingAs($user)->get('/abonnement/essentiel')->assertOk()->getContent();

        $this->assertStringContainsString('Le paiement en ligne n\'est pas encore disponible dans votre pays. Contactez-nous sur WhatsApp : nous vous expliquons comment vous abonner.', html_entity_decode($html, ENT_QUOTES));
        $this->assertStringContainsString('Pays : Cameroun', $html);
        $this->assertStringContainsString('btn btn-whatsapp', $html);
        $this->assertStringNotContainsString('J\'ai payé, j\'envoie ma capture', html_entity_decode($html, ENT_QUOTES));

        // Le bouton passe par le serveur (le clic est enregistré), qui renvoie vers le WhatsApp de l'équipe.
        $this->assertStringContainsString('href="'.route('payment.whatsapp', 'essentiel').'"', $html);
        $location = $this->actingAs($user)->get(route('payment.whatsapp', 'essentiel'))->assertRedirect()->headers->get('Location');
        $this->assertMatchesRegularExpression('/^https:\/\/wa\.me\/22872240231\?text=/', $location, 'bouton vers le WhatsApp de l\'équipe (+228 72 24 02 31)');
        $message = urldecode(parse_url($location, PHP_URL_QUERY));
        $this->assertStringContainsString('Nom : Awa Nguema', $message);
        $this->assertStringContainsString('Pays : Cameroun', $message);
        $this->assertStringContainsString('Formule choisie : Essentiel (7', $message);
        $this->assertStringContainsString('FCFA', $message);

        // La liste des formules prévient, en gardant les prix en francs CFA.
        $this->actingAs($user)->get('/abonnement')->assertOk()->assertSee('pas encore disponible dans votre pays')->assertSee(Plan::find('essentiel')->formattedPrice());

        // Aucune capture n'est acceptée.
        $this->actingAs($user)->post('/abonnement/essentiel', [])->assertRedirect(route('payment.create', 'essentiel'));
        $this->assertSame(0, Payment::count());
    }

    public function test_the_four_mobile_money_countries_keep_the_capture_flow(): void
    {
        $user = User::factory()->create(['phone' => '+22890123456']);

        $this->actingAs($user)->get('/abonnement/essentiel')->assertOk()
            ->assertSee('Par quel moyen voulez-vous payer ?')
            ->assertDontSee('pas encore disponible dans votre pays');
    }

    public function test_the_admin_can_offer_access_to_a_learner_from_another_country(): void
    {
        $this->freezeSecond();
        $admin = User::factory()->admin()->create();
        $learner = User::factory()->create(['phone' => '+237677123456', 'country' => 'CM']);

        $this->actingAs($admin)->post(route('admin.users.grant', $learner), [
            'plan' => 'essentiel', 'duration' => '7', 'confirmed' => 1,
        ])->assertRedirect();

        $subscription = $learner->fresh()->activeSubscription;
        $this->assertNotNull($subscription);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->isGranted());

        // L'apprenant a accès à la séance (programme à construire d'abord) et à « Mes cours ».
        $this->actingAs($learner)->get('/mes-cours')->assertOk()->assertDontSee('Choisir ma formule');
    }

    public function test_the_admin_sees_each_country_and_filters_the_other_countries(): void
    {
        $admin = User::factory()->admin()->create(['phone' => '+22890000000']);
        User::factory()->create(['name' => 'Kossi Togo', 'phone' => '+22890123456']);
        User::factory()->create(['name' => 'Awa Cameroun', 'phone' => '+237677123456', 'country' => 'CM']);
        User::factory()->create(['name' => 'Léa France', 'phone' => '+33612345678', 'country' => 'FR']);

        $all = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $all->assertSee('Kossi Togo')->assertSee('Awa Cameroun')->assertSee('Cameroun')->assertSee('Paiement par WhatsApp');

        $this->actingAs($admin)->get(route('admin.users.index', ['pays' => 'autres']))->assertOk()
            ->assertSee('Awa Cameroun')->assertSee('Léa France')->assertDontSee('Kossi Togo');

        $this->actingAs($admin)->get(route('admin.users.index', ['pays' => 'TG']))->assertOk()
            ->assertSee('Kossi Togo')->assertDontSee('Awa Cameroun');

        $cameroon = User::where('country', 'CM')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.users.show', $cameroon))->assertOk()
            ->assertSee('Cameroun')->assertSee('abonnement par WhatsApp');
    }

    public function test_no_ghana_on_the_public_pages_the_site_guide_or_the_curriculum(): void
    {
        $forbidden = '/Ghana|ghanéen|Accra|Aflao|anglais international officiel/iu';
        $pages = array_map(fn (string $url) => parse_url($url, PHP_URL_PATH) ?: '/', Seo::sitemapUrls());
        $pages[] = '/inscription';

        foreach ($pages as $path) {
            // La liste des pays de l'inscription nomme tous les pays du monde, Ghana compris : seul le texte compte.
            $html = preg_replace('/<select.*?<\/select>/s', '', $this->get($path)->assertOk()->getContent());
            $this->assertDoesNotMatchRegularExpression($forbidden, $html, $path);
        }

        $this->assertDoesNotMatchRegularExpression($forbidden, (string) file_get_contents(resource_path('teacher/site-guide.md')));
        $this->assertDoesNotMatchRegularExpression($forbidden, (string) file_get_contents(resource_path('content/pays.php')));

        foreach (glob(resource_path('curriculum/*.php')) as $file) {
            $this->assertDoesNotMatchRegularExpression($forbidden, (string) file_get_contents($file), basename($file));
        }
    }

    public function test_public_pages_say_american_english_and_welcome_every_country(): void
    {
        $home = html_entity_decode($this->get('/')->assertOk()->getContent(), ENT_QUOTES);
        $this->assertStringContainsString('l\'anglais américain, compris partout dans le monde', $home);
        $this->assertStringContainsString('accueille les francophones du monde entier', $home);
        $this->assertStringContainsString('Quel anglais vais-je apprendre ?', $home);

        $plans = html_entity_decode($this->get('/formules')->assertOk()->getContent(), ENT_QUOTES);
        $this->assertStringContainsString('Pour les francophones de tous les pays', $plans);
        $this->assertStringContainsString('Dans les autres pays, le paiement en ligne n\'est pas encore disponible', $plans);

        $this->get('/apprendre-anglais/togo')->assertOk()->assertSee('accueille les francophones de tous les pays');
        $this->get('/a-propos')->assertOk()->assertSee('anglais américain, compris partout dans le monde');
    }
}
