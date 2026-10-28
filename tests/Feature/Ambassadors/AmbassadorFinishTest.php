<?php

namespace Tests\Feature\Ambassadors;

use App\Models\Setting;
use App\Models\User;
use App\Support\Ambassadors\AmbassadorProgram;
use App\Support\Gemini\SiteGuide;
use App\Support\Seo;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Programme d'ambassadeurs, étape 5 (finition) : règles écrites sur la page « Devenir ambassadeur », page indexée
 * et dans le plan du site, lien dans le pied de page, fiche du site de Teacher Joe. (Lien de l'ambassadeur dans ses
 * vidéos de séance : tests/Feature/Live/SessionRecordingTest.php.)
 */
class AmbassadorFinishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://talkami.net']);
        Setting::put(AmbassadorProgram::PLACES_SETTING, '10');
        SiteSettings::flush();
    }

    public function test_the_page_states_the_three_rules_without_promising_any_income(): void
    {
        $response = $this->get(route('ambassador.join'))->assertOk();

        $response->assertSee('Les règles')
            ->assertSee('Seulement le premier paiement.')
            ->assertSee('jamais sur ses paiements suivants', false)
            ->assertSee('Le code ambassadeur tapé à l\'inscription passe avant tout.', false)
            ->assertSee('c\'est le premier lien d\'ambassadeur utilisé qui compte (gardé 30 jours)', false)
            ->assertSee('Un seul niveau.')
            ->assertSee('Aucune commission sur les personnes inscrites par vos inscrits', false)
            ->assertSee('Aucun revenu n\'est promis', false);

        $text = mb_strtolower(strip_tags($response->getContent()));
        foreach (['revenu garanti', 'gagnez jusqu', 'par mois garanti', 'devenez riche', 'revenu passif'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $text);
        }
    }

    public function test_the_page_is_indexed_in_the_sitemap_with_its_own_title_and_description(): void
    {
        $this->assertContains('https://talkami.net/devenir-ambassadeur', Seo::sitemapUrls());
        $this->get('/sitemap.xml')->assertSee('<loc>https://talkami.net/devenir-ambassadeur</loc>', false);

        $html = $this->get(route('ambassador.join'))->assertOk()->assertHeaderMissing('X-Robots-Tag')->getContent();
        $this->assertStringContainsString('<title>Devenir ambassadeur, programme de parrainage · '.config('platform.name').'</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://talkami.net/devenir-ambassadeur">', $html);
        $this->assertMatchesRegularExpression('/<meta name="description" content="Programme d(&#039;|\')ambassadeurs de /', $html);

        // Les pages de la candidature restent hors de l'index.
        $this->get(route('ambassador.join.form'))->assertRedirect();
        $this->assertNotContains('https://talkami.net/devenir-ambassadeur/candidature', Seo::sitemapUrls());
    }

    public function test_the_footer_links_to_the_page_for_visitors_and_learners(): void
    {
        $link = '<a href="'.route('ambassador.join').'">Devenir ambassadeur</a>';

        $this->get('/')->assertSee($link, false);
        $this->get(route('plans'))->assertSee($link, false);
        $this->actingAs(User::factory()->create(['welcomed_at' => now()]))->get(route('dashboard'))->assertSee($link, false);
    }

    public function test_the_site_guide_explains_the_programme_with_values_from_the_configuration(): void
    {
        config([
            'platform.ambassadors.commission_percent' => 25,
            'platform.ambassadors.commission_days' => 60,
            'platform.ambassadors.link_days' => 45,
            'platform.ambassadors.max_months' => 4,
        ]);

        foreach (['assessment', 'lesson', 'course'] as $kind) {
            $guide = SiteGuide::text($kind);

            $this->assertStringContainsString('## Programme d\'ambassadeurs', $guide);
            $this->assertStringContainsString('talkami.net/devenir-ambassadeur', $guide);
            $this->assertStringContainsString('formule Essentiel', $guide);
            $this->assertStringContainsString('25 % du PREMIER paiement', $guide);
            $this->assertStringContainsString('dans les 60 jours', $guide);
            $this->assertStringContainsString('gardé 45 jours', $guide);
            $this->assertStringContainsString('jusqu\'à 4 mois', $guide);
            $this->assertStringContainsString('Un seul niveau', $guide);
            $this->assertStringContainsString('le code tapé à l\'inscription passe avant tout', $guide);
            $this->assertStringContainsString('Ne promets JAMAIS un revenu', $guide);
            $this->assertDoesNotMatchRegularExpression('/:ambassador_[a-z_]+/', $guide);
        }

        // Bilan de niveau : partie inutile, jamais refacturée.
        $this->assertStringNotContainsString('Programme d\'ambassadeurs', SiteGuide::text('level_test'));
    }
}
