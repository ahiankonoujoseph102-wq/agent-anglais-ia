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
            ->assertSee("7\u{202F}500\u{00A0}FCFA", false)
            ->assertSee('Intensif')
            ->assertSee("14\u{202F}000\u{00A0}FCFA", false)
            ->assertSee('Semaine')
            ->assertSee("2\u{202F}000\u{00A0}FCFA", false)
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
            ->assertSee('10 minutes par jour, à partager entre la séance orale et « Mes cours »', false)
            ->assertDontSee('Essentiel');
    }

    public function test_guests_are_invited_to_register(): void
    {
        $this->get('/')
            ->assertSee(route('register'))
            ->assertSeeText('Passer mon évaluation gratuite');
    }

    public function test_home_page_tells_how_it_works_and_answers_common_questions(): void
    {
        $html = $this->get('/')->assertOk()
            // Promesse et bouton orange en haut de page, Teacher Joe dès l'ouverture.
            ->assertSee('Parlez anglais avec un professeur')
            ->assertSee('disponible chaque jour')
            ->assertSee('class="btn btn-accent btn-lg btn-block" href="'.route('register').'"', false)
            ->assertSee('class="hero-teacher"', false)
            // Sections.
            ->assertSee('Quatre étapes simples')
            ->assertSee('Ce qui rend Talkami différent')
            ->assertSee('Grand Marché de Lomé')
            ->assertSee('Vous êtes élève ?')
            ->assertSee('Questions fréquentes')
            ->assertSee('Qu\'est-ce que l\'accès provisoire ?', false)
            ->assertSee('De quel matériel ai-je besoin ?')
            ->assertSee('Combien de données internet faut-il ?')
            ->assertSee('Qu\'est-ce que l\'attestation de niveau ?', false)
            ->assertSee('Mixx by Yas')
            // Bouton WhatsApp (seul bouton vert).
            ->assertSee('class="btn btn-whatsapp btn-lg"', false)
            ->getContent();

        // Vocabulaire imposé et aucune promesse d'aide aux cours (pas encore disponible).
        $this->assertDoesNotMatchRegularExpression('/certificat|diplôme|conversation vidéo|devoirs de classe|aide aux cours/iu', strip_tags($html));
        $this->assertStringNotContainsString('T-Money', $html);
    }
}
