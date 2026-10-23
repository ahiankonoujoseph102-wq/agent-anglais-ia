<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Images : Teacher Joe sur les pages d'erreur, hors connexion et états vides ; aperçu de partage léger ; chaque photo
 * affichée par le site a sa ligne dans docs/credits-images.md.
 */
class ImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_joe_greets_on_error_offline_and_empty_pages(): void
    {
        $face = '#<span class="hero-teacher-face teacher-face" style="--face: \d+px">\s*<img src="[^"]*images/teacher-joe\.webp[^"]*" width="\d+" height="\d+" alt="" onerror="this\.parentNode\.remove\(\)">#';

        $this->assertMatchesRegularExpression($face, $this->get('/page-qui-n-existe-pas')->getContent());
        $this->assertMatchesRegularExpression($face, $this->get('/hors-connexion')->getContent());

        $this->actingAs(User::factory()->create(['welcomed_at' => now()]));
        $this->assertMatchesRegularExpression($face, $this->get('/mes-seances')->getContent());
    }

    public function test_an_empty_history_shows_its_button_first(): void
    {
        $html = $this->actingAs(User::factory()->create(['welcomed_at' => now()]))->get('/mes-seances')->assertOk()->getContent();
        $this->assertStringNotContainsString('Mes erreurs à revoir', $html);
        $this->assertStringContainsString('Aucune séance pour le moment.', $html);
    }

    public function test_share_image_is_light_and_every_photo_is_credited(): void
    {
        [$width, $height] = getimagesize(public_path('images/partage-talkami.jpg'));
        $this->assertSame([1200, 630], [$width, $height]);
        $this->assertLessThan(120 * 1024, filesize(public_path('images/partage-talkami.jpg')));

        $credits = file_get_contents(base_path('docs/credits-images.md'));
        foreach (config('photos') as $name => $photo) {
            $this->assertStringContainsString('public/images/photos/'.$name.'-', $credits, $name);
            $this->assertStringContainsString($photo['source_url'], $credits, $name);
            foreach ([480, 960] as $size) {
                $this->assertLessThan(60 * 1024, filesize(public_path("images/photos/{$name}-{$size}.webp")), "{$name}-{$size}");
            }
        }
        $this->assertStringContainsString('fondateur-joseph-ahiankonou.webp', $credits);
    }
}
