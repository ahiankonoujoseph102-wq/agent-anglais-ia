<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Plus de page « Crédits » : l'ancienne adresse renvoie vers l'accueil, les licences
 * sont gardées dans le dépôt (LICENCES-TIERS.md et fichiers de licence), sans lien sur le site.
 */
class LicensesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_old_credits_page_redirects_to_home_and_is_linked_nowhere(): void
    {
        $this->get('/credits')->assertRedirect('/')->assertStatus(301);

        foreach (['/', '/connexion', '/contact'] as $path) {
            $this->get($path)->assertOk()
                ->assertDontSee('/credits', false)
                ->assertDontSee('Crédits')
                ->assertDontSee('LICENCES-TIERS');
        }
        $this->actingAs(User::factory()->create())->get('/espace')->assertDontSee('Crédits');
    }

    public function test_third_party_licenses_are_kept_in_the_repository(): void
    {
        $list = File::get(base_path('LICENCES-TIERS.md'));

        foreach (['Teacher Joe', 'CC0', 'Sora', 'Open Font License', 'three.js', 'HeadAudio', 'Mika Suominen', 'Lucide', 'ISC', 'Feather'] as $text) {
            $this->assertStringContainsString($text, $list);
        }
        foreach (config('photos') as $photo) {
            $this->assertStringContainsString($photo['author'], $list);
            $this->assertStringContainsString($photo['source_url'], $list);
        }

        $this->assertFileExists(public_path('fonts/OFL-Sora.txt'));
        $this->assertStringContainsString('Mika Suominen', File::get(public_path('teacher3d/lib/LICENSES.txt')));
        foreach (['headaudio.min.js', 'headworklet.min.js', 'three-teacher.min.js'] as $file) {
            $this->assertStringStartsWith('/*', File::get(public_path('teacher3d/lib/'.$file)), "En-tête de licence : {$file}");
        }
    }

    public function test_footer_is_signed_by_the_developer(): void
    {
        $this->get('/')->assertSee('<p class="footer-signature">Développé par <strong>Joseph Ahiankonou</strong></p>', false);
    }
}
