<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accueil : blocs de persuasion « Vous vous reconnaissez ? », « Vous avez déjà essayé ? » et
 * « Qui est derrière … ? ». Des situations vécues, jamais des témoignages ; aucun chiffre ni urgence inventés.
 */
class PersuasionTest extends TestCase
{
    use RefreshDatabase;

    private const SITUATIONS = [
        "Je comprends un peu l'anglais, mais quand il faut parler, je bloque.",
        'Je cherche mes mots, et ça ne sort pas.',
        "J'ai peur de faire des fautes et qu'on se moque de moi.",
        "Je n'ai personne avec qui pratiquer.",
    ];

    /**
     * HTML du bloc « Vous vous reconnaissez ? » et « Vous avez déjà essayé ? ».
     */
    private function persuasionBlock(): string
    {
        $html = $this->get('/')->assertOk()->getContent();
        $start = strpos($html, '<div class="section band persuade">');
        $this->assertNotFalse($start);

        return substr($html, $start, strpos($html, 'aria-labelledby="etapes"', $start) - $start);
    }

    public function test_recognize_block_comes_right_after_the_top_of_the_page(): void
    {
        $this->get('/')->assertOk()->assertSeeInOrder([
            'id="promesse"',
            'Vous vous reconnaissez ?',
            ...self::SITUATIONS,
            "Ce n'est pas un manque d'intelligence : c'est un manque de pratique.",
            config('avatar.model.name').' est là pour ça.',
            'Passer mon évaluation gratuite',
            'Vous avez déjà essayé ?',
            'id="etapes"',
        ], false);

        // Bouton orange vers l'évaluation gratuite (inscription pour un visiteur).
        $this->assertMatchesRegularExpression('/<a class="btn btn-accent btn-block" href="'.preg_quote(route('register'), '/').'">/', $this->persuasionBlock());
    }

    public function test_tried_block_lists_three_no_and_one_yes_without_any_brand(): void
    {
        $block = $this->persuasionBlock();

        $this->assertSame(3, substr_count(substr($block, strpos($block, 'class="tried"')), '<li>'));
        $this->assertStringContainsString("Des applications où l'on clique sur des réponses, sans jamais parler.", $block);
        $this->assertStringContainsString('Des vidéos regardées… puis oubliées.', $block);
        $this->assertStringContainsString('Des cours de grammaire, sans personne pour vous écouter.', $block);
        $this->assertStringContainsString('Avec '.config('platform.name').', vous parlez chaque jour, et '.config('avatar.model.name').' vous écoute et vous corrige.', $block);

        foreach (['Duolingo', 'Babbel', 'Busuu', 'Memrise', 'Mondly', 'Rosetta', 'Cambly', 'Preply', 'italki', 'YouTube'] as $brand) {
            $this->assertStringNotContainsStringIgnoringCase($brand, $block);
        }
    }

    public function test_situations_are_never_testimonials(): void
    {
        $block = $this->persuasionBlock();

        // Ni photo, ni guillemets, ni citation, ni nom : des situations vécues, pas des témoignages.
        foreach (['<img', '<picture', '<blockquote', '<cite', '<q>', '«', '»', '“', '”', config('platform.founder.name')] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $block);
        }

        $this->assertDoesNotMatchRegularExpression('/[—–-]\s*[A-ZÀ-Ý][a-zà-ÿ]+,/u', strip_tags($block), 'aucune signature');
        // Icônes Lucide, aucun émoji.
        $this->assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $block));
        $this->assertGreaterThanOrEqual(8, substr_count($block, '<svg class="icon"'));
    }

    public function test_no_invented_urgency_on_the_home_page(): void
    {
        $text = mb_strtolower(strip_tags($this->get('/')->getContent()));

        foreach (['places limitées', 'compte à rebours', 'dernière chance', 'offre expire', 'plus que quelques'] as $urgency) {
            $this->assertStringNotContainsString($urgency, $text);
        }
    }
}
