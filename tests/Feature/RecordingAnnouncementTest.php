<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Gemini\SiteGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Annonce de « Enregistrer ma séance » : accueil (cours, étapes, questions fréquentes), « Mon espace » (rappel d'une
 * ligne et « Comment partager ma vidéo »), pages de séance (conseil, aide) et fiche du site. Textes honnêtes : la vidéo
 * reste sur le téléphone, pensée pour Chrome sur Android, sans promesse sur l'iPhone.
 */
class RecordingAnnouncementTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    private const HINT = 'Pensez à toucher « Enregistrer ma séance » avant « Commencer »';

    private const SHARE = 'Comment partager ma vidéo';

    private function subscriber(): User
    {
        $user = User::factory()->level(Level::A1)->create(['welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A1);

        return $user;
    }

    public function test_the_home_page_announces_the_recording(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Enregistrez votre séance avec Teacher Joe :', false)
            ->assertSee('la vidéo montre le professeur, le tableau, et on y entend vos deux voix. Elle reste sur votre téléphone, Talkami ne la reçoit jamais.', false)
            ->assertSee('partagez-la avec vos proches, sur WhatsApp ou sur vos réseaux, si vous le souhaitez.', false)
            ->assertSee('Vous pouvez même l\'enregistrer en vidéo.', false);
    }

    public function test_the_faq_explains_recording_honestly_and_feeds_the_structured_data(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach ([
            'Puis-je enregistrer ma séance en vidéo ?',
            'Où retrouver ma vidéo, et comment la partager ?',
            'Ma vidéo est-elle privée ?',
            'Cette fonction est pensée pour Chrome sur Android. Sur iPhone, elle peut ne pas fonctionner ou se comporter autrement.',
            'si vous la quittez ou la fermez avant, la vidéo est perdue',
            'dossier « Téléchargements » : son nom commence par « talkami »',
            'Sur un téléphone lent, l\'image peut être un peu moins nette ou moins fluide',
            'elle n\'est jamais envoyée à Talkami, et notre équipe ne la voit pas',
        ] as $text) {
            $this->assertStringContainsString($text, $this->plain($html), $text);
        }

        // Données structurées FAQPage : les nouvelles questions y sont.
        $this->assertStringContainsString('"name":"Puis-je enregistrer ma séance en vidéo ?"', $this->plain($html));
    }

    public function test_the_dashboard_reminds_to_record_and_explains_how_to_share(): void
    {
        // Nouvel inscrit : la prochaine étape est l'évaluation gratuite.
        $this->actingAs(User::factory()->create())->get('/espace')->assertOk()
            ->assertSeeInOrder(['Commencer mon évaluation gratuite', self::HINT, self::SHARE], false)
            ->assertSee('href="#partager-ma-video"', false)
            ->assertSee('id="partager-ma-video"', false)
            ->assertSee('Ouvrez l\'application Fichiers de votre téléphone, puis le dossier « Téléchargements »', false);

        // Abonné avec programme : la séance du jour.
        $this->actingAs($this->subscriber())->get('/espace')->assertOk()
            ->assertSeeInOrder(['Parler avec Teacher Joe', self::HINT, self::SHARE], false);
    }

    public function test_the_dashboard_says_nothing_when_recording_is_off_or_no_session_is_proposed(): void
    {
        config(['platform.recording.enabled' => false]);
        $this->actingAs(User::factory()->create())->get('/espace')->assertOk()
            ->assertDontSee(self::HINT, false)->assertDontSee(self::SHARE, false);

        // Évaluation passée, sans formule : la prochaine étape est le choix d'une formule, aucun rappel.
        config(['platform.recording.enabled' => true]);
        $user = User::factory()->level(Level::A2)->create();
        $user->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 200, 'level' => Level::A2, 'status' => AssessmentStatus::Completed]);
        $this->actingAs($user)->get('/espace')->assertOk()->assertDontSee(self::HINT, false)->assertDontSee(self::SHARE, false);
    }

    public function test_session_pages_carry_the_recording_sentence_once_and_the_share_help(): void
    {
        foreach (['/evaluation' => User::factory()->create(), '/seance' => $this->subscriber()] as $url => $user) {
            $html = $this->actingAs($user)->get($url)->assertOk()
                ->assertSee('La vidéo montre Teacher Joe, le tableau, et on y entend vos deux voix. Elle reste sur votre téléphone', false)
                ->assertSee(self::SHARE)
                ->getContent();
            // Plus de conseil qui répète le bouton : sa phrase, juste dessous, suffit.
            $this->assertStringNotContainsString('Touchez « Enregistrer ma séance » pour garder la vidéo', $html);
            $this->actingAs($user)->get($url)
                ->assertSee('Ouvrez-la, touchez l\'icône de partage, puis choisissez WhatsApp pour l\'envoyer à vos proches, ou TikTok pour la publier.', false);
        }

        config(['platform.recording.enabled' => false]);
        $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()
            ->assertDontSee('Touchez « Enregistrer ma séance »', false)->assertDontSee(self::SHARE, false);
    }

    public function test_one_button_name_everywhere_and_no_technical_word(): void
    {
        $files = [
            ...glob(resource_path('views/**/*.blade.php')),
            ...glob(resource_path('views/*.blade.php')),
            resource_path('teacher/site-guide.md'),
        ];

        foreach ($files as $file) {
            preg_match_all('/« Enregistrer (?:ma|la|votre|une) [^»]*»/u', file_get_contents($file), $found);

            foreach ($found[0] as $name) {
                $this->assertSame('« Enregistrer ma séance »', $name, $file);
            }
        }

        foreach (['recording-hint', 'recording-share'] as $component) {
            $text = file_get_contents(resource_path("views/components/{$component}.blade.php"));
            $text = preg_replace('/\{\{--.*?--\}\}/s', '', $text);

            foreach (['3D', 'MediaRecorder', 'navigateur', 'serveur'] as $word) {
                $this->assertStringNotContainsString($word, $text, $component);
            }
        }
    }

    public function test_teacher_joe_knows_how_recording_works(): void
    {
        $guide = SiteGuide::text();

        $this->assertStringContainsString('« Enregistrer ma séance » (évaluation gratuite et séance du jour', $guide);
        $this->assertStringContainsString('Talkami ne la reçoit jamais', $guide);
        $this->assertStringContainsString('Pensé pour Chrome sur Android ; sur iPhone, cela peut ne pas fonctionner.', $guide);
    }

    /** Espaces insécables de la typographie française remis en espaces simples. */
    private function plain(string $html): string
    {
        return str_replace(["\u{202F}", "\u{00A0}"], ' ', html_entity_decode($html, ENT_QUOTES));
    }
}
