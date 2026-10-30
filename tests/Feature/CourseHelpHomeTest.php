<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\SiteGuide;
use App\Support\Gemini\TeacherSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

/**
 * L'accueil, les formules et la page de résultat présentent « Mes cours » comme une aide EN PLUS
 * de l'oral, uniquement pour les cours d'anglais ; aucune promesse que le site ne tient pas.
 */
class CourseHelpHomeTest extends TestCase
{
    use HasCourses;
    use RefreshDatabase;

    /** Formulations interdites partout où le site parle de « Mes cours ». */
    private const FORBIDDEN = ['fait les devoirs', 'tous vos cours', 'correction écrite', 'donne les réponses', 'donne les reponses'];

    public function test_the_home_page_presents_the_course_help_for_pupils_and_parents(): void
    {
        $html = html_entity_decode($this->get('/')->assertOk()->getContent(), ENT_QUOTES);

        foreach ([
            "Vos cours d'anglais enfin compris",
            "Ajoutez jusqu'à 4 photos de votre cahier ou de votre livre, ou votre cours en PDF (jusqu'à 50 pages)",
            'Puis-je travailler mes cours d\'anglais, même en PDF ?',
            'vous revenez un autre jour pour la suite, sans renvoyer le fichier',
            'en français quand il le faut',
            'et répond à vos questions',
            'Il vous guide pour faire votre exercice vous-même, puis corrige',
            "Uniquement pour les cours d'anglais.",
            'Aussi pour les étudiants : lycée, université, formation.',
            'Vous êtes parent ?',
            "Un professeur d'anglais patient pour votre enfant",
            'ne fait pas les devoirs à la place de votre enfant',
            "Il l'entraîne aussi à parler anglais à voix haute.",
            'Inscrire mon enfant',
            "vous aide aussi à comprendre vos cours d'anglais de l'école",
        ] as $text) {
            $this->assertStringContainsString($text, $html);
        }

        // L'oral reste le message principal : le titre du haut de page parle de parler anglais avec le professeur.
        $this->assertStringContainsString('Parlez anglais avec <span class="accent">Teacher Joe</span>', $html);
        $this->assertStringContainsString('vous fait parler sans gêne ni jugement', $html);
        $this->assertStringContainsString('Osez parler anglais', $html);
    }

    public function test_the_four_cards_share_one_compact_format_and_come_in_the_right_order(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Étudiants, puis Élèves, puis Parents, puis « Vous travaillez ? ».
        $positions = array_map(fn (string $id) => strpos($html, 'id="'.$id.'"'), ['etudiants', 'eleves', 'parents', 'pros']);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);

        // Même carte compacte : une photo, un titre, une phrase, trois points au plus (repliés), un bouton secondaire :
        // le bouton orange reste au premier écran, aux formules et à l'appel final.
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(4, $xpath->query('//div[contains(@class,"audiences")]/section[contains(@class,"students")]')->length);

        foreach (['etudiants', 'eleves', 'parents', 'pros'] as $id) {
            $card = '//section[@aria-labelledby="'.$id.'"][contains(@class,"audience")]';
            $this->assertSame(1, $xpath->query($card.'/figure[contains(@class,"photo-frame")]//img[@loading="lazy"][@width][@height]')->length, $id);
            $this->assertSame(1, $xpath->query($card.'//h2[@id="'.$id.'"]')->length, $id);
            $this->assertLessThanOrEqual(3, $xpath->query($card.'//ul[contains(@class,"audience-points")]/li')->length, $id);
            $this->assertGreaterThanOrEqual(1, $xpath->query($card.'//ul[contains(@class,"audience-points")]/li')->length, $id);
            $this->assertSame(1, $xpath->query($card.'//details[contains(@class,"audience-more")]/ul[contains(@class,"audience-points")]')->length, $id);
            $this->assertSame(1, $xpath->query($card.'//a[contains(@class,"btn-outline")]')->length, $id);
            $this->assertSame(0, $xpath->query($card.'//a[contains(@class,"btn-accent")]')->length, $id);
        }

        // Le bouton « parents » mène à l'inscription ; l'ancien bloc n'existe plus.
        $this->assertStringContainsString('href="'.route('register').'"', $html);
        $this->assertStringNotContainsString('class="parents', $html);
    }

    public function test_the_student_card_speaks_to_students_without_promising_a_result(): void
    {
        $html = html_entity_decode($this->get('/')->assertOk()->getContent(), ENT_QUOTES);

        foreach ([
            'Vous êtes étudiant ?',
            "Prenez la parole en anglais : exposés, entretiens, études ou stage à l'étranger.",
            'Oser parler, sans gêne ni jugement',
            "S'entraîner aux entretiens de bourse, d'admission ou de stage",
            "Vos cours d'anglais en PDF, avec « Mes cours »",
            // Les cartes existantes sont toujours là.
            'Vous êtes élève ?', 'Vous êtes parent ?', 'Vous travaillez ?', 'Inscrire mon enfant',
        ] as $text) {
            $this->assertStringContainsString($text, $html);
        }

        // La photo des étudiants : en deux tailles, avec sa licence.
        foreach ([480, 960] as $size) {
            $this->assertFileExists(public_path("images/photos/etudiants-bibliotheque-{$size}.webp"));
        }
        $this->assertStringContainsString('pexels.com/photo/people-on-the-table-reading-books-10744384', file_get_contents(base_path('LICENCES-TIERS.md')));

        // Aucune promesse : ni examen officiel, ni bourse, visa ou résultat garantis.
        foreach (['TOEFL', 'IELTS', 'bourse garantie', 'visa garanti', 'admission garantie', 'réussite garantie', 'garantit'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $html);
            $this->assertStringNotContainsStringIgnoringCase($forbidden, file_get_contents(resource_path('teacher/site-guide.md')));
        }
    }

    public function test_the_parent_photo_exists_in_both_sizes_and_is_credited(): void
    {
        foreach ([480, 960] as $size) {
            $this->assertFileExists(public_path("images/photos/parent-enfant-lecture-{$size}.webp"));
        }

        $this->assertSame('Chidy Young', config('photos.parent-enfant-lecture.author'));
        $this->assertStringContainsString('Nu5819t1K6c', file_get_contents(base_path('LICENCES-TIERS.md')));
    }

    public function test_the_course_help_button_follows_the_visitor(): void
    {
        // Visiteur : inscription. Aucun lien vers la page réservée aux comptes.
        $this->get('/')->assertDontSee('/mes-cours', false);

        // Compte sans formule active : jamais renvoyé vers une page qui lui refuse l'accès.
        $this->actingAs(User::factory()->create())->get('/')->assertDontSee('Ouvrir Mes cours');

        // Formule active : le bouton ouvre « Mes cours ».
        $this->actingAs($this->learner())->get('/')->assertSee('Ouvrir Mes cours')->assertSee(route('courses.index'), false);
    }

    public function test_no_forbidden_wording_on_the_pages_that_present_my_courses(): void
    {
        $pages = [
            $this->get('/')->getContent(),
            file_get_contents(resource_path('views/components/plan-card.blade.php')),
            file_get_contents(resource_path('views/assessment/result.blade.php')),
            file_get_contents(resource_path('teacher/site-guide.md')),
            file_get_contents(resource_path('views/courses/index.blade.php')),
        ];

        foreach ($pages as $page) {
            foreach (self::FORBIDDEN as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase($forbidden, $page);
            }
        }
    }

    public function test_every_plan_shares_its_daily_time_with_the_course_help(): void
    {
        $this->get('/')
            ->assertSee("Aide à vos cours d'anglais (Mes cours), comprise dans ce temps", false)
            ->assertSee('15 minutes par jour, à partager entre la séance orale et « Mes cours »', false)
            ->assertSee('30 minutes par jour, à partager entre la séance orale et « Mes cours »', false)
            ->assertDontSee('leçons par jour');
    }

    public function test_the_result_page_reminds_that_the_plans_include_the_course_help(): void
    {
        $user = $this->learner();
        $user->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 300, 'level' => 'A2', 'status' => 'completed']);
        $user->level = Level::A2;
        $user->save();

        $this->actingAs($user)->get('/evaluation')->assertOk()
            ->assertSee("Chaque formule comprend aussi l'aide à vos cours d'anglais", false);
    }

    public function test_the_home_page_stays_light(): void
    {
        $bytes = 0;

        // Poids transféré (texte compressé) : la page, les feuilles de style et le JavaScript commun.
        foreach ([$this->get('/')->getContent(), file_get_contents(public_path('css/tokens.css')), file_get_contents(public_path('css/app.css')), file_get_contents(public_path('js/app.js'))] as $file) {
            $bytes += strlen(gzencode($file, 9));
        }

        $this->assertLessThan(150 * 1024, $bytes, 'La page d\'accueil dépasse 150 Ko (hors image de Teacher Joe et police).');
    }

    public function test_the_teacher_instructions_cover_confusion_slowing_down_and_french_as_a_tool(): void
    {
        $user = new User(['name' => 'Yao']);

        $daily = TeacherSetup::instructions('lesson', $user, 15);
        $course = TeacherSetup::instructions('course', $user, 10, ['lesson' => 'x', 'warning_seconds' => 90]);

        foreach ([$daily, $course] as $text) {
            // Mal compris : le remarquer, anglais très simple, puis français, rassurer, et la phrase redonnée en anglais.
            $this->assertStringContainsString('« je ne comprends pas », « hein ? »', $text);
            $this->assertStringContainsString('say the same idea again in VERY simple English', $text);
            $this->assertStringContainsString('explain it in clear, calm French and reassure', $text);
            // N'entend pas / ne suit pas : ralentir nettement, et garder ce débit.
            $this->assertStringContainsString('slow down clearly', $text);
            $this->assertStringContainsString('KEEP this slower pace until the end', $text);
            // Ton, âge, encouragements précis ; une ou deux corrections par réponse.
            $this->assertStringContainsString('softer and simpler with a young pupil', $text);
            $this->assertStringContainsString('Never mocking', $text);
            $this->assertStringContainsString('precise encouragement', $text);
            $this->assertStringContainsString('At most ONE or TWO mistakes per answer', $text);
        }

        // Séance orale : faire parler en anglais, le français seulement pour débloquer.
        $this->assertStringContainsString('in the DAILY LESSON the goal is to make the learner SPEAK English', $daily);
        $this->assertStringContainsString('French is an explanation tool, never the language of the conversation', $daily);

        // « Mes cours » : le français est la langue principale ; détail complet de chaque point ; vérifications.
        $this->assertStringContainsString('French is the MAIN language of EXPLANATION', $course);
        $this->assertStringContainsString('go back to French as soon as an explanation gets difficult', $course);
        $this->assertStringContainsString('each one with its translation in French', $course);
        $this->assertStringContainsString('EVERY point of the lesson', $course);
        $this->assertStringContainsString('never skimming', $course);
        $this->assertStringContainsString('ask a short question to check they understood', $course);
    }

    public function test_every_presentation_of_my_courses_offers_photos_or_a_pdf_of_fifty_pages(): void
    {
        $offer = "Ajoutez jusqu'à 4 photos de votre cahier ou de votre livre, ou votre cours en PDF (jusqu'à 50 pages)";
        $user = $this->learner();

        foreach (['/', '/mes-cours'] as $url) {
            $this->assertStringContainsString($offer, html_entity_decode($this->actingAs($user)->get($url)->getContent(), ENT_QUOTES), $url);
        }

        $this->assertStringContainsString($offer, html_entity_decode($this->actingAs($user)->get('/espace')->getContent(), ENT_QUOTES));
        $this->assertStringContainsString('ou son cours en PDF (jusqu\'à 50 pages)', html_entity_decode($this->get('/apprendre-anglais/benin')->getContent(), ENT_QUOTES));
        $this->assertStringContainsString('ou son cours en PDF (20 mégaoctets et 50 pages au plus)', SiteGuide::text());

        // Plus aucun « photographiez » seul, nulle part où « Mes cours » est présenté (le message vocal de bienvenue
        // ne change pas : il n'est pas dans ces fichiers).
        $files = [...glob(resource_path('views/**/*.blade.php')), ...glob(resource_path('views/*.blade.php')), resource_path('content/pays.php'), resource_path('teacher/site-guide.md')];

        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression('/photographi/iu', (string) file_get_contents($file), $file);
        }
    }
}
