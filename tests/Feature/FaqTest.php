<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Support\CountryPage;
use App\Support\Gemini\SiteGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Foire aux questions de l'accueil (et ses données structurées FAQPage) : courte, repliée, fidèle au fonctionnement
 * réel (séance en quatre temps, français en filet de sécurité, corrections, révision), sans promesse ni marque.
 */
class FaqTest extends TestCase
{
    use RefreshDatabase;

    private const QUESTIONS = [
        'Je suis débutant complet, est-ce pour moi ?',
        "L'évaluation est-elle vraiment gratuite ?",
        'Comment se passe une séance ?',
        'Puis-je poser mes questions en français ?',
        'Comment Teacher Joe corrige-t-il mes erreurs ?',
        "Vais-je oublier ce que j'ai appris ?",
        'Quel anglais vais-je apprendre ?',
        'Puis-je choisir mon professeur ?',
        "Puis-je travailler mes cours d'anglais, même en PDF ?",
        'Puis-je préparer un entretien ou un exposé ?',
        'Puis-je enregistrer ma séance en vidéo ?',
        'Où retrouver ma vidéo, et comment la partager ?',
        'Ma vidéo est-elle privée ?',
        'Comment payer mon abonnement ?',
        'De quoi ai-je besoin (matériel, internet) ?',
        "Qu'est-ce que l'attestation de niveau ?",
    ];

    private function faq(): string
    {
        $html = $this->get('/')->assertOk()->getContent();
        $start = strpos($html, '<div class="faq reveal">');

        return substr($html, $start, strpos($html, '</section>', $start) - $start);
    }

    public function test_the_home_faq_is_short_folded_and_in_the_journey_order(): void
    {
        $this->get('/')->assertOk()->assertSeeInOrder(self::QUESTIONS, false);

        $faq = $this->faq();
        $this->assertSame(count(self::QUESTIONS), substr_count($faq, '<details>'));
        // Toutes repliées.
        $this->assertStringNotContainsString('<details open', $faq);
    }

    public function test_answers_describe_how_teacher_joe_really_teaches(): void
    {
        $faq = html_entity_decode(strip_tags($this->faq()), ENT_QUOTES);

        // Séance en quatre temps avec le tableau, l'anglais d'abord et le français en filet de sécurité.
        $this->assertStringContainsString('écrits au tableau sur votre écran', $faq);
        $this->assertStringContainsString('le français sert de filet de sécurité', $faq);
        // Évaluation : mini-leçon et durée totale tirée de la configuration.
        $this->assertStringContainsString('une mini-leçon sur une ou deux de vos propres erreurs', $faq);
        $this->assertStringContainsString(Assessment::totalMinutes().' minutes au plus en tout', $faq);
        // Phrases utiles, corrections, révision.
        $this->assertStringContainsString('« Can you repeat, please? »', $faq);
        $this->assertStringContainsString('Il vous laisse chercher avec un indice, puis donne la bonne forme et vous fait redire la phrase entière.', $faq);
        $this->assertStringContainsString('reviennent aux séances suivantes', $faq);
        $this->assertStringContainsString('la phrase à retenir de la veille et votre devoir', $faq);
        $this->assertStringContainsString('des phrases des leçons passées', $faq);
        // PDF : la limite vient de la configuration.
        $this->assertStringContainsString('(jusqu\'à '.config('platform.courses.pdf_max_pages').' pages)', $faq);
    }

    public function test_no_promise_no_invented_figure_no_brand(): void
    {
        $faq = html_entity_decode(strip_tags($this->faq()), ENT_QUOTES);

        $this->assertDoesNotMatchRegularExpression('/garanti|en \d+ (jours|semaines|mois)|couramment|\d+ ?%|apprenants satisfaits|Google|Gemini|OpenAI|ChatGPT|certificat|diplôme/iu',
            str_replace('aucun résultat n\'est garanti', '', $faq));
    }

    public function test_the_structured_data_follows_the_new_questions(): void
    {
        $raw = $this->get('/')->assertOk()->rawContent();
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $raw, $scripts);
        $faq = collect($scripts[1])->map(fn ($json) => json_decode($json, true))->firstWhere('@type', 'FAQPage');

        $this->assertNotNull($faq);
        $names = array_map(fn ($q) => str_replace(["\u{202F}", "\u{00A0}"], ' ', $q['name']), $faq['mainEntity']);
        $this->assertSame(self::QUESTIONS, $names);
    }

    public function test_country_pages_and_the_site_guide_match_the_faq(): void
    {
        $this->assertStringContainsString('il vous laisse chercher la bonne forme avant de vous corriger', CountryPage::find('togo')->faq[2]['answer']);
        $this->assertStringContainsString(Assessment::totalMinutes().' minutes au plus en tout', CountryPage::find('cote-d-ivoire')->faq[0]['answer']);

        $guide = SiteGuide::text();
        $this->assertStringContainsString('## Comment on travaille avec', $guide);
        $this->assertStringContainsString('« Phrases utiles »', $guide);
        $this->assertStringContainsString('Ne promets jamais un résultat ni un délai.', $guide);
    }
}
