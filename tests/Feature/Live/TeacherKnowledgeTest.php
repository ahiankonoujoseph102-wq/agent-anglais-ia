<?php

namespace Tests\Feature\Live;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Gemini\LearnerSituation;
use App\Support\Gemini\SiteGuide;
use App\Support\Plan;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Teacher Joe connaît le site (fiche resources/teacher/site-guide.md), sait où en
 * est l'apprenant, et parle avec une voix masculine figée dans le jeton.
 */
class TeacherKnowledgeTest extends TestCase
{
    use BuildsPrograms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton123'])]);
    }

    private function sentInstructions(): string
    {
        $text = '';

        Http::assertSent(function (Request $request) use (&$text) {
            $text = $request['bidiGenerateContentSetup']['systemInstruction']['parts'][0]['text'];

            return true;
        });

        return $text;
    }

    public function test_male_voice_by_default_is_locked_in_the_ephemeral_token(): void
    {
        $this->assertSame('Achird', config('services.gemini.voice'));
        $this->assertStringContainsString("env('GEMINI_VOICE_NAME')", file_get_contents(config_path('services.php')));

        $this->actingAs(User::factory()->create())->postJson('/evaluation/connexion')->assertOk();

        Http::assertSent(fn (Request $request) => $request['bidiGenerateContentSetup']['generationConfig']['speechConfig']
            === ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => 'Achird']]]);
    }

    public function test_the_voice_can_be_changed_by_configuration(): void
    {
        config(['services.gemini.voice' => 'Iapetus']);

        $this->actingAs(User::factory()->create())->postJson('/evaluation/connexion')->assertOk();

        Http::assertSent(fn (Request $request) => $request['bidiGenerateContentSetup']['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName'] === 'Iapetus');
    }

    public function test_site_guide_reads_plans_prices_and_operators_from_the_configuration(): void
    {
        config(['platform.plans.essentiel.price' => 4321]);

        $guide = SiteGuide::text();

        foreach (Plan::all() as $plan) {
            $this->assertStringContainsString("{$plan->name} : {$plan->formattedPrice()} {$plan->periodLabel()}", $guide);
        }

        $this->assertStringContainsString(Plan::find('essentiel')->formattedPrice(), $guide);
        // Valeurs par défaut : les sept opérateurs, sans aucun numéro dans la fiche.
        $this->assertStringContainsString('- Togo : Mixx by Yas, Flooz.', $guide);
        $this->assertStringContainsString('- Burkina Faso : Orange Money, Wave.', $guide);
        $this->assertStringContainsString('- Bénin : MTN, Moov, Celtiis.', $guide);
        $this->assertStringNotContainsString((string) config('payment.countries.TG.operators.flooz.number'), $guide);

        // Rien de configuré : le paiement n'est pas encore ouvert.
        foreach (config('payment.countries') as $code => $country) {
            foreach (array_keys($country['operators']) as $key) {
                config(["payment.countries.{$code}.operators.{$key}.number" => null]);
            }
        }
        $this->assertStringContainsString('le paiement n\'est pas encore ouvert', SiteGuide::text());

        // Seuls les opérateurs dont le numéro est saisi dans les Réglages (numéros fictifs).
        SiteSettings::save([
            SiteSettings::operatorNumberKey('TG', 'mixx') => '+228 90 00 00 11',
            SiteSettings::operatorNumberKey('TG', 'flooz') => '+228 90 00 00 22',
            SiteSettings::operatorNumberKey('BJ', 'celtiis') => '+229 01 00 00 00 33',
        ]);
        $guide = SiteGuide::text();
        $this->assertStringContainsString('- Togo : Mixx by Yas, Flooz.', $guide);
        $this->assertStringContainsString('- Bénin : Celtiis.', $guide);
        $this->assertStringNotContainsString('Burkina Faso :', $guide);
        $this->assertStringNotContainsString('90 00 00', $guide);
        $this->assertStringContainsString('ATTESTATION de niveau', $guide);
        $this->assertStringContainsString('« Envoyer ma capture »', $guide);
        $this->assertStringContainsString('ACCÈS PROVISOIRE de 24 heures', $guide);
        $this->assertStringNotContainsString('référence', $guide);
        $this->assertStringContainsString(config('platform.name'), $guide);
        // Marqueurs tous remplacés, notes de rédaction retirées.
        $this->assertDoesNotMatchRegularExpression('/:(platform|teacher|assessment|plans|operators)\b/', $guide);
        $this->assertStringNotContainsString('<!--', $guide);
    }

    public function test_site_guide_contains_no_phone_number_nor_hand_written_price(): void
    {
        $raw = file_get_contents(SiteGuide::path());

        $this->assertDoesNotMatchRegularExpression('/\+?\d[\d ]{6,}\d/', $raw, 'Numéro de téléphone dans la fiche');
        $this->assertDoesNotMatchRegularExpression('/FCFA|F CFA/i', $raw, 'Prix écrit à la main dans la fiche');

        // La fiche remplie ne contient pas non plus les numéros saisis dans les Réglages.
        SiteSettings::save([SiteSettings::operatorNumberKey('TG', 'flooz') => '+228 90 00 00 22']);
        $guide = SiteGuide::text();

        $this->assertStringContainsString('Flooz', $guide);
        $this->assertStringNotContainsString('90 00 00 22', $guide);
        $this->assertStringNotContainsString('90000022', $guide);
    }

    public function test_assessment_token_contains_site_rules_guide_and_learner_status(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Kossi']))->postJson('/evaluation/connexion')->assertOk();

        $text = $this->sentInstructions();

        $this->assertStringContainsString('The learner is ALREADY on the '.config('platform.name').' website', $text);
        $this->assertStringContainsString('Never say "go to our website" or "rendez-vous sur notre site"', $text);
        $this->assertStringContainsString('Never invent anything that is not in the SITE GUIDE', $text);
        $this->assertStringContainsString('the green WhatsApp button', $text);
        $this->assertStringContainsString('must not use up the assessment time', $text);
        $this->assertStringContainsString('SITE GUIDE (in French)', $text);
        $this->assertStringContainsString('« Choisir ma formule »', $text);
        $this->assertStringContainsString("LEARNER STATUS (in French)\nPrénom : Kossi", $text);
        $this->assertStringContainsString('Page actuelle : la page « Évaluation gratuite »', $text);
        $this->assertStringContainsString('Étape actuelle : évaluation gratuite en cours', $text);
    }

    public function test_learner_name_in_the_status_is_never_read_as_a_placeholder(): void
    {
        $this->actingAs(User::factory()->create(['name' => ':minutes :platform']))->postJson('/evaluation/connexion')->assertOk();

        // Le prénom est nettoyé (lettres seulement) : aucun marqueur ne peut y entrer.
        $this->assertStringContainsString('Prénom : Minutes', $this->sentInstructions());
        $this->assertStringNotContainsString(':platform', explode('LEARNER STATUS', $this->sentInstructions())[1] ?? '');
    }

    public function test_learner_status_follows_the_journey(): void
    {
        $user = User::factory()->level(Level::A2)->create();
        $user->assessment()->create([
            'status' => AssessmentStatus::Completed,
            'started_at' => now()->subDay(),
            'allowed_seconds' => 300,
            'level' => Level::A2,
        ]);

        // Niveau obtenu, rien payé.
        $text = LearnerSituation::describe($user, 'lesson');
        $this->assertStringContainsString('aucune formule active : prochaine étape, choisir une formule et payer', $text);
        $this->assertStringContainsString('Niveau : A2', $text);

        // Paiement déclaré, en attente.
        $plan = Plan::find('semaine');
        $payment = $user->payments()->create([
            'plan' => $plan->key, 'plan_name' => $plan->name, 'daily_minutes' => $plan->dailyMinutes,
            'duration_days' => $plan->durationDays, 'amount' => $plan->price, 'currency' => 'FCFA',
            'country' => 'TG', 'operator' => 'mixx', 'operator_name' => 'Mixx by Yas', 'recipient_number' => 'numero',
            'proof_path' => 'payment-proofs/x.png',
        ]);
        $this->assertStringContainsString('capture du paiement envoyée (formule Semaine), vérification par l\'équipe en cours', LearnerSituation::describe($user, 'lesson'));

        // Formule active, programme pas encore construit.
        $payment->delete();
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $text = LearnerSituation::describe($user, 'lesson');
        $this->assertStringContainsString('programme pas encore construit', $text);
        $this->assertStringContainsString('Formule active : Essentiel', $text);

        // Programme en cours.
        $this->buildProgram($user);
        $this->assertStringContainsString('programme A2 en cours (0 % fait)', LearnerSituation::describe($user, 'lesson'));
    }
}
