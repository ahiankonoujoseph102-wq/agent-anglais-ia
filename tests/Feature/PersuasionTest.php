<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accueil : blocs de persuasion « Êtes-vous vraiment fier de votre niveau en anglais ? », « Vous avez déjà essayé ? » et
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
     * HTML du bloc « Êtes-vous vraiment fier de votre niveau en anglais ? » et « Vous avez déjà essayé ? ».
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
            'Êtes-vous vraiment fier de votre niveau en anglais ?',
            'Si vous vous reconnaissez dans l\'une de ces phrases, '.config('avatar.model.name').' est fait pour vous.',
            ...self::SITUATIONS,
            "Ce n'est pas un manque d'intelligence : c'est un manque de pratique.",
            config('avatar.model.name').' est là pour ça.',
            'Vous avez déjà essayé ?',
            'id="etapes"',
        ], false);

        // Plus de bouton orange ici : il est au premier écran, juste au-dessus, et à l'appel final.
        $this->assertStringNotContainsString('btn-accent', $this->persuasionBlock());
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

        // Seule image permise : Teacher Joe (le professeur, jamais un apprenant), dans l'encadré de la réponse, pas
        // dans la liste des situations.
        $situations = substr($block, strpos($block, 'class="situations"'));
        $situations = substr($situations, 0, strpos($situations, '</ul>'));
        $this->assertStringNotContainsString('<img', $situations);
        $this->assertMatchesRegularExpression('#<p class="persuade-answer"><img class="persuade-face" src="[^"]*teacher-joe-pensif\.webp#', $block);
        $block = preg_replace('#<img class="persuade-face"[^>]*>#', '', $block);

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

    private const BIO = "J'ai créé Talkami pour que chaque francophone puisse enfin oser parler anglais, depuis son téléphone, sans gêne.";

    public function test_home_shows_who_is_behind_the_platform_before_the_questions(): void
    {
        $html = $this->get('/')->assertOk()
            ->assertSeeInOrder(['id="pays"', 'Qui est derrière Talkami ?', 'Joseph Ahiankonou', 'coach et formateur à Lomé', self::BIO, 'Lire l\'histoire de Talkami', 'id="questions"'], false)
            ->getContent();

        // Photo ronde, chargée à la demande, avec son texte alternatif ; lien vers « À propos ».
        $this->assertMatchesRegularExpression('/<img class="founder-photo" src="[^"]*images\/fondateur-joseph-ahiankonou\.webp\?v=[^"]+" width="320" height="320" alt="Joseph Ahiankonou, fondateur de Talkami" loading="lazy" decoding="async">/', $html);
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('about'), '/').'" class="founder-more">Lire l\'histoire de Talkami/', $html);
    }

    public function test_about_page_shows_the_founder_bio_and_photo(): void
    {
        $this->get(route('about'))->assertOk()
            ->assertSeeInOrder(['alt="Joseph Ahiankonou, fondateur de Talkami"', 'Joseph Ahiankonou, fondateur', self::BIO], false);

        // Sans présentation ni photo : rien d'inventé, aucune image cassée.
        config(['platform.founder.bio' => null, 'platform.founder.photo' => null]);
        $this->get(route('about'))->assertOk()->assertDontSee('founder-photo', false)->assertDontSee('founder-bio', false);
        $this->get('/')->assertOk()->assertDontSee('Qui est derrière');
    }

    public function test_founder_photo_is_a_small_square_webp(): void
    {
        $path = public_path(config('platform.founder.photo'));

        $this->assertFileExists($path);
        $this->assertLessThanOrEqual(15 * 1024, filesize($path));
        [$width, $height, $type] = getimagesize($path);
        $this->assertSame($width, $height);
        $this->assertSame(IMAGETYPE_WEBP, $type);
    }

    public function test_the_platform_name_in_the_bio_comes_from_the_configuration(): void
    {
        config(['platform.name' => 'Professeur Ama']);

        $this->get(route('about'))->assertSeeText("J'ai créé Professeur Ama pour que chaque francophone")->assertDontSeeText("J'ai créé Talkami");
    }
}
