<?php

namespace Tests\Feature\Admin;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Enums\PaymentStatus;
use App\Models\User;
use App\Models\UserOrigin;
use App\Support\Origins;
use App\Support\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Provenance des inscrits : première page vue, site d'origine et lien partagé, gardés en session puis copiés à
 * l'inscription ; page « Provenance » de l'administration. Aucun outil extérieur.
 */
class OriginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://talkami.net']);
    }

    private function register(): User
    {
        $this->post('/inscription', ['name' => 'Kossi Mensah', 'phone' => '90 12 34 56', 'password' => 'motdepasse'])->assertRedirect(route('dashboard'));
        $user = User::query()->latest('id')->firstOrFail();
        auth()->logout();

        return $user;
    }

    public function test_a_visitor_from_google_is_counted_with_the_first_page_seen(): void
    {
        $this->get('/apprendre-anglais/togo', ['Referer' => 'https://www.google.com/search?q=cours+anglais+lome'])->assertOk();
        $this->get('/formules', ['Referer' => 'https://talkami.net/apprendre-anglais/togo'])->assertOk();
        $this->get('/inscription')->assertOk();

        $origin = $this->register()->origin;

        $this->assertSame('google', $origin->channel);
        $this->assertSame('/apprendre-anglais/togo', $origin->landing_path);
        // Le nom de domaine seulement : jamais l'adresse complète ni la recherche tapée.
        $this->assertSame('google.com', $origin->referrer_host);
        $this->assertNotNull($origin->first_seen_at);
    }

    public function test_a_shared_link_is_counted_by_its_utm_source(): void
    {
        $this->get('/?utm_source=whatsapp&utm_medium=partage&utm_campaign=niveau')->assertOk();

        $origin = $this->register()->origin;

        $this->assertSame(['whatsapp', '/', 'partage', 'niveau', null], [$origin->channel, $origin->landing_path, $origin->utm_medium, $origin->utm_campaign, $origin->referrer_host]);
    }

    public function test_channels_are_read_from_sites_and_android_apps(): void
    {
        $this->assertSame('facebook', Origins::channel(null, 'l.facebook.com'));
        $this->assertSame('tiktok', Origins::channel(null, 'com.zhiliaoapp.musically'));
        $this->assertSame('whatsapp', Origins::channel(null, 'com.whatsapp'));
        $this->assertSame('google', Origins::channel(null, 'com.google.android.googlequicksearchbox'));
        $this->assertSame('tiktok', Origins::channel('tiktok', 'google.com'));
        $this->assertSame('other', Origins::channel(null, 'republicoftogo.com'));
        $this->assertSame('direct', Origins::channel(null, null));
    }

    public function test_the_site_itself_files_and_signed_in_visits_are_never_an_origin(): void
    {
        // Le site lui-même (ancienne adresse comprise) n'est pas un site d'origine.
        $this->get('/a-propos', ['Referer' => 'https://talkami.laravel.cloud/'])->assertOk();
        $origin = $this->register()->origin;
        $this->assertSame(['direct', '/a-propos', null], [$origin->channel, $origin->landing_path, $origin->referrer_host]);

        // Robots et manifeste ne comptent pas comme une page vue.
        $this->flushSession();
        $this->get('/robots.txt');
        $this->get('/manifest.webmanifest');
        $this->assertNull(session(Origins::SESSION));

        // Un compte connecté ne laisse rien en session.
        $this->actingAs(User::factory()->create())->get('/formules');
        $this->assertNull(session(Origins::SESSION));
    }

    public function test_the_admin_sees_sign_ups_assessments_and_subscriptions_by_channel_and_page(): void
    {
        $google = User::factory()->create(['name' => 'Afi Google']);
        Origins::record($google, ['path' => '/apprendre-anglais/benin', 'referrer' => 'google.com']);
        $google->assessment()->create(['status' => AssessmentStatus::Completed, 'started_at' => now(), 'allowed_seconds' => 300, 'level' => Level::A2]);
        $plan = collect(Plan::all())->first();
        $google->payments()->create([
            'plan' => $plan->key, 'plan_name' => $plan->name, 'daily_minutes' => $plan->dailyMinutes,
            'duration_days' => $plan->durationDays, 'amount' => $plan->price, 'currency' => 'FCFA',
            'country' => 'BJ', 'operator' => 'mtn', 'operator_name' => 'MTN', 'recipient_number' => '+229 01 00 00 00 00', 'proof_path' => 'payment-proofs/x.png',
        ])->forceFill(['status' => PaymentStatus::Validated])->save();

        $whatsapp = User::factory()->create(['name' => 'Kodjo WhatsApp']);
        Origins::record($whatsapp, ['path' => '/', 'utm_source' => 'whatsapp', 'utm_campaign' => 'niveau']);
        Origins::record(User::factory()->create(), null);

        $html = $this->actingAs(User::factory()->admin()->create())->get('/admin/provenance')->assertOk()
            ->assertSeeText('Provenance des inscrits')
            ->assertSeeText('Par canal (3 inscrits)')
            ->assertSeeText('Afi Google')
            ->assertSeeText('Accès direct ou inconnu')
            ->getContent();

        $this->assertMatchesRegularExpression('/Google<\/td>\s*<td[^>]*>1<\/td>\s*<td[^>]*>1 \(100 %\)<\/td>\s*<td[^>]*>1 \(100 %\)/u', html_entity_decode($html));
        $this->assertMatchesRegularExpression('/WhatsApp<\/td>\s*<td[^>]*>1<\/td>\s*<td[^>]*>0 \(0 %\)<\/td>\s*<td[^>]*>0 \(0 %\)/u', html_entity_decode($html));
        $this->assertStringContainsString('/apprendre-anglais/benin', $html);

        // Fiche de l'inscrit.
        $this->get('/admin/inscrits/'.$google->id)->assertSeeText('Provenance')->assertSeeText('Google (google.com)');

        // Période : une inscription plus ancienne n'est pas comptée sur 7 jours.
        UserOrigin::query()->where('user_id', $google->id)->update(['created_at' => now()->subDays(20)]);
        $google->forceFill(['created_at' => now()->subDays(20)])->save();
        $this->get('/admin/provenance?jours=7')->assertSeeText('Par canal (2 inscrits)');
        $this->get('/admin/provenance?jours=tout')->assertSeeText('Par canal (3 inscrits)');
    }

    public function test_only_admins_see_the_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/provenance')->assertForbidden();
    }
}
