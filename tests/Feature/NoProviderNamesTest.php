<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CountryPage;
use App\Support\Gemini\SiteGuide;
use App\Support\Gemini\TeacherSetup;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPrograms;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

/**
 * Les fournisseurs d'intelligence artificielle ne sont jamais nommés aux visiteurs ni aux apprenants, page
 * « Confidentialité » comprise (elle parle de « prestataires techniques d'intelligence artificielle et
 * d'hébergement ») : seule l'administration les nomme.
 */
class NoProviderNamesTest extends TestCase
{
    use BuildsPrograms, HasCourses, RefreshDatabase;

    private const NAMES = ['Gemini', 'OpenAI', 'ChatGPT'];

    private function assertNoProviderName(string $html, string $where): void
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES);

        foreach (self::NAMES as $name) {
            // Le texte affiché (insensible à la casse) et le code de la page (balises meta, JSON-LD, attributs).
            $this->assertStringNotContainsStringIgnoringCase($name, $text, "« {$name} » visible sur {$where}");
            $this->assertStringNotContainsString($name, $html, "« {$name} » dans le code de {$where}");
        }
    }

    public function test_public_pages_never_name_an_ai_provider(): void
    {
        $urls = ['/', '/formules', '/a-propos', '/contact', '/confidentialite', '/inscription', '/connexion', '/mot-de-passe-oublie', '/hors-connexion', '/une-page-qui-n-existe-pas'];

        foreach (CountryPage::slugs() as $slug) {
            $urls[] = '/apprendre-anglais/'.$slug;
        }

        foreach ($urls as $url) {
            $this->assertNoProviderName($this->get($url)->getContent(), $url);
        }

        $this->assertNoProviderName(json_encode([Seo::organization(), Seo::website(), Seo::offers()], JSON_UNESCAPED_UNICODE), 'les données structurées');
    }

    public function test_learner_pages_and_teacher_site_guide_never_name_an_ai_provider(): void
    {
        $user = $this->learner();
        $this->buildProgram($user);
        $lesson = $this->storedLesson($user);
        $user->forceFill(['welcomed_at' => now()])->save();

        foreach (['/espace', '/evaluation', '/abonnement', '/abonnement/essentiel', '/programme', '/programme/unites/1', '/seance', '/mes-cours', '/mes-cours/nouvelle', '/mes-cours/'.$lesson->id, '/mes-seances', '/compte', '/bienvenue?reecouter=1'] as $url) {
            $response = $this->actingAs($user)->get($url);
            $this->assertContains($response->getStatusCode(), [200], $url);
            $this->assertNoProviderName($response->getContent(), $url);
        }

        $this->assertNoProviderName(SiteGuide::text(), 'la fiche du site de Teacher Joe');
    }

    public function test_the_privacy_page_speaks_of_technical_providers_without_any_brand(): void
    {
        $html = $this->get('/confidentialite')
            ->assertOk()
            ->assertSee('Talkami est responsable du traitement de vos données.')
            ->assertSee('prestataires techniques d\'intelligence artificielle et d\'hébergement, qui peuvent être situés hors du Togo', false)
            ->assertSeeText('Talkami n\'enregistre pas votre voix')
            ->getContent();

        foreach (['Google', 'Laravel Cloud'] as $brand) {
            $this->assertStringNotContainsString($brand, strip_tags($html));
        }

        $this->get('/a-propos')->assertSee('Teacher Joe a été conçu et programmé par Joseph Ahiankonou. Il s\'appuie sur une intelligence artificielle de pointe, encadrée par la méthode pédagogique de Talkami.', false);
    }

    public function test_teacher_joe_says_who_made_him_without_naming_any_brand(): void
    {
        $text = TeacherSetup::instructions('lesson', new User(['name' => 'Afi']), 15);

        $this->assertStringContainsString('you are Teacher Joe, the teacher of Talkami, designed and programmed by Joseph Ahiankonou, and you use artificial intelligence', $text);
        $this->assertStringContainsString('Never name any company, brand or model', $text);
        $this->assertStringContainsString('never claim to be a human', $text);
        $this->assertStringNotContainsString(':founder', $text);
    }
}
