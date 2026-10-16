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
}
