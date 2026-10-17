<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_home_page_shows_the_plans_from_configuration(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(config('platform.name'))
            ->assertSee(config('avatar.model.name'))
            ->assertSee('Essentiel')
            ->assertSee("5\u{202F}000\u{00A0}FCFA", false)
            ->assertSee('Intensif')
            ->assertSee("9\u{202F}000\u{00A0}FCFA", false)
            ->assertSee('Semaine')
            ->assertSee("1\u{202F}500\u{00A0}FCFA", false)
            ->assertSee('par semaine');
    }

    public function test_brand_name_and_plans_come_from_configuration(): void
    {
        config([
            'platform.name' => 'Professeur Ama',
            'platform.brand.short_name' => 'Ama',
            'platform.plans' => [
                'decouverte' => [
                    'name' => 'Découverte',
                    'daily_minutes' => 10,
                    'price' => 750,
                    'period' => 'week',
                    'duration_days' => 7,
                ],
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Professeur Ama')
            ->assertDontSee('Talkami')
            ->assertSee('Découverte')
            ->assertSee('10 minutes de séance par jour')
            ->assertDontSee('Essentiel');
    }

    public function test_guests_are_invited_to_register(): void
    {
        $this->get('/')
            ->assertSee(route('register'))
            ->assertSeeText('Commencer l\'évaluation gratuite');
    }
}
