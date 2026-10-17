<?php

namespace Tests\Feature;

use Tests\TestCase;

class CreditsTest extends TestCase
{
    public function test_credits_page_lists_the_3d_teacher_and_libraries_with_their_licenses(): void
    {
        $this->get('/credits')
            ->assertOk()
            ->assertSee('Teacher Joe')
            ->assertSee('CC0 1.0 (domaine public)')
            ->assertSee('three.js')
            ->assertSee('HeadAudio')
            ->assertSee('Mika Suominen')
            ->assertSee('MIT');

        $this->get('/')->assertSee(route('credits'));
    }

    public function test_every_photo_is_credited_and_exists_in_both_sizes(): void
    {
        $photos = config('photos');
        $this->assertNotEmpty($photos);

        $response = $this->get('/credits')->assertOk()->assertSee('https://unsplash.com/license', false);

        foreach ($photos as $name => $photo) {
            $response->assertSee($photo['author'])->assertSee($photo['source_url'], false);
            $this->assertStringStartsWith('https://unsplash.com/', $photo['source_url']);

            foreach ([480, 960] as $width) {
                $file = public_path("images/photos/{$name}-{$width}.webp");
                $this->assertFileExists($file);
                $this->assertLessThan(45 * 1024, filesize($file), "{$name}-{$width} reste léger");
            }
        }
    }

    public function test_home_page_photos_are_lazy_loaded_and_backgrounds_respect_reduced_motion(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<source media="(min-width: 900px)"', false)
            ->assertSee('loading="lazy"', false);

        $css = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('@keyframes drift', $css);
        $this->assertMatchesRegularExpression('/prefers-reduced-motion[^}]*\{[^@]*\.hero::before[^}]*animation:\s*none/s', $css);
    }
}
