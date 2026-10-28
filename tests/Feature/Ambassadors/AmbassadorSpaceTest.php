<?php

namespace Tests\Feature\Ambassadors;

use App\Http\Controllers\Ambassador\AmbassadorSpaceController;
use App\Models\Ambassador;
use App\Models\User;
use App\Support\Ambassadors\AmbassadorProgram;
use App\Support\Ambassadors\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Espace ambassadeur (étape 1) : lien, code et code QR personnels, mois gratuit, régularité ; lien qui mène
 * à l'accueil ; « Mon espace » qui parle du mois gratuit d'ambassadeur.
 */
class AmbassadorSpaceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://talkami.net']);
        $this->admin = User::factory()->admin()->create();
    }

    private function ambassador(): Ambassador
    {
        $user = User::factory()->create(['name' => 'Kofi Mensah']);

        return AmbassadorProgram::appoint($user, $this->admin)['ambassador'];
    }

    public function test_the_ambassador_sees_his_link_code_qr_and_free_month(): void
    {
        $ambassador = $this->ambassador();

        $this->actingAs($ambassador->user)->get(route('ambassador.show'))
            ->assertOk()
            ->assertSee('Espace ambassadeur')
            ->assertSee('talkami.net/a/'.$ambassador->code)
            ->assertSee('https://talkami.net/a/'.$ambassador->code, false)
            ->assertSee($ambassador->code)
            ->assertSee('Partager mon lien sur WhatsApp')
            ->assertSee('Copier mon lien')
            ->assertSee('<svg xmlns="http://www.w3.org/2000/svg"', false)
            ->assertSee('Télécharger mon code QR')
            ->assertSee('Formule Essentiel, jusqu\'au', false)
            ->assertSee('1 sur 6')
            ->assertSee('0 jour avec une séance du jour sur les 30 derniers jours')
            // Rapports et vidéos restent dans WhatsApp : rien à envoyer ici.
            ->assertDontSee('Envoyer mon rapport');
    }

    public function test_the_share_message_says_only_true_facts_with_the_personal_link(): void
    {
        $ambassador = $this->ambassador();
        $message = AmbassadorSpaceController::shareMessage($ambassador);

        $this->assertStringContainsString('https://talkami.net/a/'.$ambassador->code, $message);
        $this->assertStringContainsString('évaluation orale gratuite de '.config('platform.assessment.max_minutes').' minutes', $message);
        $this->assertStringContainsString(config('avatar.model.name'), $message);

        foreach (['garanti', 'gagner', 'argent', 'commission', 'IA', 'intelligence artificielle'] as $word) {
            $this->assertStringNotContainsString($word, $message);
        }
    }

    public function test_the_qr_code_is_downloaded_as_an_image(): void
    {
        $ambassador = $this->ambassador();

        $response = $this->actingAs($ambassador->user)->get(route('ambassador.qr'))->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->assertStringContainsString('code-qr-'.strtolower($ambassador->code).'.png', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith("\x89PNG", $response->getContent());
    }

    public function test_the_qr_svg_is_a_square_black_on_white_code(): void
    {
        $svg = QrCode::svg('https://talkami.net/a/KOFI27');

        $this->assertMatchesRegularExpression('/viewBox="0 0 (\d+) \1"/', $svg);
        $this->assertStringContainsString('fill="#fff"', $svg);
        $this->assertStringContainsString('<path fill="#000" d="M', $svg);
    }

    public function test_the_space_is_only_for_active_ambassadors(): void
    {
        $this->get(route('ambassador.show'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('ambassador.show'))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('ambassador.qr'))->assertNotFound();

        $ambassador = $this->ambassador();
        AmbassadorProgram::remove($ambassador);
        $this->actingAs($ambassador->user->fresh())->get(route('ambassador.show'))->assertNotFound();
    }

    public function test_the_menu_shows_the_ambassador_space_only_to_ambassadors(): void
    {
        $ambassador = $this->ambassador();

        $this->actingAs($ambassador->user)->get(route('dashboard'))
            ->assertSee(route('ambassador.show'), false)
            ->assertSee('Espace ambassadeur')
            ->assertSee('Mois gratuit d\'ambassadeur jusqu\'au', false);

        $this->actingAs(User::factory()->create())->get(route('account.edit'))->assertDontSee('Espace ambassadeur');
    }

    public function test_the_personal_link_leads_to_the_home_page_even_with_an_unknown_code(): void
    {
        $ambassador = $this->ambassador();

        $this->get('/a/'.$ambassador->code)->assertRedirect(route('home'));
        $this->get('/a/'.strtolower($ambassador->code))->assertRedirect(route('home'));
        $this->get('/a/INCONNU99')->assertRedirect(route('home'));
    }
}
