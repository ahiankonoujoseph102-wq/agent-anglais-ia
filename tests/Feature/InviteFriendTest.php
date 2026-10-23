<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\User;
use App\Support\FriendInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Inviter un ami sur WhatsApp » : page de résultat de l'évaluation et « Mon espace ». Lien de partage sans numéro,
 * message court lu dans la configuration (adresse du site : APP_URL), ni nom ni niveau de l'apprenant, rien envoyé
 * à la plateforme.
 */
class InviteFriendTest extends TestCase
{
    use RefreshDatabase;

    private function assessed(): User
    {
        $user = User::factory()->level(Level::B1)->create(['name' => 'Akossiwa Mensah']);
        $user->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 280, 'level' => Level::B1, 'status' => AssessmentStatus::Completed, 'feedback' => 'Très bien.']);

        return $user;
    }

    public function test_the_message_comes_from_the_configuration_and_promises_only_what_the_site_does(): void
    {
        config(['app.url' => 'https://talkami.example', 'avatar.model.name' => 'Professeur Kofi', 'platform.name' => 'Parlons', 'platform.assessment.max_minutes' => 5]);

        $this->assertSame(
            'Bonjour ! J\'apprends à parler anglais avec Professeur Kofi, le professeur de Parlons. Toi aussi, passe l\'évaluation orale gratuite : 5 minutes à l\'oral pour connaître ton niveau d\'anglais, depuis ton téléphone. C\'est ici : https://talkami.example/',
            FriendInvite::message(),
        );

        // Lien de partage SANS numéro : l'apprenant choisit le contact.
        $this->assertStringStartsWith('https://wa.me/?text=', FriendInvite::url());
        $this->assertSame(FriendInvite::message(), rawurldecode(substr(FriendInvite::url(), strlen('https://wa.me/?text='))));

        foreach (['garanti', '100 %', 'carte bancaire', 'sans engagement', 'Gemini', 'OpenAI'] as $word) {
            $this->assertStringNotContainsString($word, FriendInvite::message());
        }
    }

    public function test_the_result_page_and_the_dashboard_offer_a_discreet_invitation_without_personal_data(): void
    {
        $user = $this->assessed();

        foreach (['/evaluation', '/espace'] as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()
                ->assertSee('Inviter un ami sur WhatsApp')
                ->assertSee('Vous choisissez le contact dans WhatsApp.')
                ->getContent();

            preg_match('#<a class="btn btn-outline invite-friend-btn" href="([^"]+)" target="_blank" rel="noopener">#', $html, $link);
            $this->assertNotEmpty($link, $url);
            $text = rawurldecode(html_entity_decode($link[1]));

            // Discret : jamais un bouton orange ni le vert du bouton WhatsApp principal.
            $this->assertStringStartsWith('https://wa.me/?text=', $text, $url);
            // Ni le nom ni le niveau de l'apprenant.
            $this->assertStringNotContainsString('Akossiwa', $text, $url);
            $this->assertStringNotContainsString('B1', $text, $url);
            $this->assertStringNotContainsString('Intermédiaire', $text, $url);
        }
    }

    public function test_no_route_receives_anything(): void
    {
        $this->assertStringNotContainsString('invit', strtolower(implode(' ', array_map(fn ($route) => $route->uri(), app('router')->getRoutes()->getRoutes()))));
    }
}
