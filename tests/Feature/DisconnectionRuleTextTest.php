<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Gemini\SiteGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Règle de la coupure de connexion, dite avec LA même phrase partout. L'ancienne affirmation (le temps continue ou
 * est compté pendant une coupure de connexion) ne doit jamais revenir. Le bouton « Pause » de l'élève, lui, garde
 * sa règle : le temps continue.
 */
class DisconnectionRuleTextTest extends TestCase
{
    use RefreshDatabase;

    private const RULE = 'Si la connexion est coupée, reprenez votre séance dès que possible : le temps de la coupure est mis en pause, vous ne perdez pas de minutes.';

    public function test_the_rule_is_the_same_sentence_everywhere(): void
    {
        $this->assertSame(self::RULE, config('platform.live.disconnect_rule'));

        // Écran de séance : conseils avant de commencer et message de connexion coupée.
        $html = $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()->getContent();
        $this->assertStringContainsString(self::RULE, $html);
        preg_match("/data-audio='([^']*)'/", $html, $match);
        $messages = json_decode(html_entity_decode($match[1]), true)['messages'];
        $this->assertStringContainsString(self::RULE, $messages['cut']);
        $this->assertStringContainsString('« Reprendre la séance »', $messages['cut']);
        $this->assertStringContainsString(self::RULE, $messages['cut_again']);

        // Question fréquente « Comment se passe une séance ? ».
        $this->assertMatchesRegularExpression('/Comment se passe une séance \?.*?'.preg_quote(self::RULE, '/').'.*?<\/details>/s', $this->get('/')->getContent());

        // Fiche du site de Teacher Joe et message vocal de bienvenue (la règle du bouton « Pause » est gardée).
        $this->assertStringContainsString(self::RULE, SiteGuide::text());
        $rules = config('teacher.welcome.paragraphs.lesson_rules');
        $this->assertStringContainsString(self::RULE, $rules);
        $this->assertStringContainsString('faire une pause, mais le temps continue de s\'écouler', $rules);

        $this->assertStringContainsString(self::RULE, file_get_contents(base_path('CLAUDE.md')));
        $this->assertStringContainsString(self::RULE, file_get_contents(base_path('docs/deploiement-laravel-cloud.md')));
    }

    public function test_the_old_claim_about_a_disconnection_never_comes_back(): void
    {
        $files = array_merge(
            glob(config_path('*.php')), glob(app_path('{,*/,*/*/,*/*/*/}*.php'), GLOB_BRACE),
            glob(resource_path('{views,views/*,views/*/*,teacher}/*.{php,md}'), GLOB_BRACE),
            glob(public_path('js/*.js')), glob(base_path('docs/*.md')), [base_path('CLAUDE.md')],
        );
        $forbidden = [
            '/temps déjà utilisé est (dé)?compté\. Touchez/iu',
            '/Connexion coupée »?,? le temps déjà utilisé est (dé)?compté/iu',
            '/Tout le temps de validité d.un jeton est décompté/iu',
            // Ancienne règle, qui promettait trop (une séance jamais reprise reste décomptée).
            '/votre temps de séance est mis en pause\s*:\s*vous ne perdez pas de minutes/iu',
            '/(?<!pas une )(?<!PAS une )coupure( de connexion)?\s*[:,]\s*(le|votre) temps (continue|s.écoule|est (dé)?compté)/u',
            '/(connexion|internet) (est )?coupée?[^.]{0,60}(le|votre) temps (continue|s.écoule|est (dé)?compté)/iu',
        ];

        foreach ($files as $file) {
            // Les commentaires sur plusieurs lignes sont lus d'un seul tenant.
            $text = (string) preg_replace('/\s*\n\s*(\/\/|\*|#)?\s*/', ' ', (string) file_get_contents($file));

            foreach ($forbidden as $pattern) {
                $this->assertDoesNotMatchRegularExpression($pattern, $text, $file);
            }
        }

        $this->assertGreaterThan(100, count($files));
    }
}
