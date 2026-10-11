<?php

namespace Tests\Feature;

use Tests\TestCase;

class ContactTest extends TestCase
{
    public function test_contact_page_and_footer_show_the_centre_details_from_configuration(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertSee('Adidogomé, Lycée moderne 2, Lomé, Togo')
            ->assertSee('Du lundi au samedi, de 8 h à 20 h')
            ->assertSee('+228 72 24 02 31')
            ->assertSee('+228 79 74 21 27')
            ->assertSee('+228 79 16 23 90')
            ->assertSee('ahiankonoujoseph102@gmail.com')
            ->assertSee('https://wa.me/22872240231', false);

        config(['platform.contact.address' => 'Nouvelle adresse, Kara']);

        $this->get('/')->assertSee('Nouvelle adresse, Kara')->assertSee(route('contact'));
    }
}
