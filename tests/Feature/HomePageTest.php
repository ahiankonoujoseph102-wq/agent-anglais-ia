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
            ->assertSee('Comment se passe votre cours avec Teacher Joe ?')
            ->assertSee('Grand Marché de Lomé')
            ->assertSee('Vous êtes élève ?')
            ->assertSee('Questions fréquentes')
            ->assertSee('accès provisoire de 24 heures')
            ->assertSee('De quoi ai-je besoin (matériel, internet) ?')
            ->assertSee('environ 50 mégaoctets pour une séance de 15 minutes')
            ->assertSee('Qu\'est-ce que l\'attestation de niveau ?', false)
            ->assertSee('Mixx by Yas')
            // Bouton WhatsApp (seul bouton vert).
            ->assertSee('class="btn btn-whatsapp btn-lg"', false)
            ->getContent();

        // Vocabulaire imposé et aucune promesse d'aide aux cours (pas encore disponible).
        $this->assertDoesNotMatchRegularExpression('/certificat|diplôme|conversation vidéo|devoirs de classe|aide aux cours/iu', strip_tags($html));
        $this->assertStringNotContainsString('T-Money', $html);
    }

    public function test_home_page_shows_how_a_lesson_goes_in_four_steps_with_a_board_preview(): void
    {
        config(['avatar.model.name' => 'Professeur Kofi']);

        $html = $this->get('/')->assertOk()
            ->assertSee('Comment se passe votre cours avec Professeur Kofi ?')
            ->assertSee('La leçon :')
            ->assertSee('affichés au tableau, en anglais simple, avec une explication en français si vous ne comprenez pas')
            ->assertSee('La pratique :')
            ->assertSee('La conversation :')
            ->assertSee('Le bilan :')
            ->assertSee('une phrase à retenir')
            // Les deux idées fortes de l'ancienne section, en une ligne chacune.
            ->assertSee('Des situations de chez nous :')
            ->assertSee('À votre rythme :')
            // Teacher Joe prend soin de l'apprenant, la progression est rattachée à l'échelle des niveaux.
            ->assertSee('Patient, sans jugement, à votre rythme')
            ->assertSee('vos erreurs revues aux séances suivantes')
            // Après le paiement : accès provisoire, accueil, 4 questions, première séance le jour même.
            ->assertSee('Souvent, votre accès s\'ouvre tout de suite pour 24 heures', false)
            ->assertSee('première séance possible le jour même')
            ->assertSee('Comment se passe une séance ?')
            ->assertSee('Comment payer mon abonnement ?')
            ->assertDontSee('Ce qui rend')
            ->getContent();

        // Aperçu du Tableau : HTML seulement (aucune image), vraies phrases du niveau A1, une phrase allumée.
        $this->assertMatchesRegularExpression('/<figure class="board board-preview[^"]*"[^>]*>/', $html);
        $preview = substr($html, strpos($html, 'board-preview'), 2000);
        $this->assertStringNotContainsString('<img', $preview);
        $this->assertStringContainsString('My name is Afi.', $preview);
        $this->assertSame(1, substr_count($preview, 'is-lit'));
        $this->assertStringContainsString('My name is Afi.', file_get_contents(resource_path('curriculum/a1.php')));
    }
}
