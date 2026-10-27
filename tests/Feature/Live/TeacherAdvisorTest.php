<?php

namespace Tests\Feature\Live;

use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\PlanFacts;
use App\Support\Gemini\TeacherSetup;
use App\Support\Plan;
use Tests\TestCase;

/**
 * Teacher Joe, professeur attentif et conseiller honnête (octobre 2026) : il donne les vrais prix (lus dans la
 * configuration) au lieu de renvoyer vers WhatsApp, répond aux objections avec des faits vrais, ne renvoie vers
 * WhatsApp que pour un vrai problème, se dit professeur virtuel, et enseigne avec tact (répétition qui a un but,
 * correction expliquée, progrès réels félicités, humour ou très courte histoire quand l'élève fatigue).
 */
class TeacherAdvisorTest extends TestCase
{
    private function instructions(string $kind, ?Level $level = null): string
    {
        $user = new User(['name' => 'Afi']);
        $user->level = $level;

        return TeacherSetup::instructions($kind, $user, 15);
    }

    public function test_every_conversation_but_the_sound_check_knows_the_real_plans_and_prices(): void
    {
        foreach (['assessment', 'lesson', 'course', 'level_test'] as $kind) {
            $text = $this->instructions($kind, Level::A2);

            $this->assertStringContainsString('PRICES AND QUESTIONS BEFORE BUYING', $text, $kind);
            $this->assertStringContainsString('ANSWER DIRECTLY', $text, $kind);
            $this->assertStringContainsString('NEVER send the learner to WhatsApp, to « Aide » or to another page for a price question', $text, $kind);

            foreach (Plan::all() as $plan) {
                $this->assertStringContainsString(PlanFacts::line($plan), $text, $kind);
            }
        }

        $this->assertStringNotContainsString('PRICES AND QUESTIONS BEFORE BUYING (you are also an honest adviser)', $this->instructions('trial'));
    }

    public function test_the_prices_come_from_the_configuration_never_from_the_instructions(): void
    {
        $plans = config('platform.plans');
        $plans['semaine']['price'] = 2500;
        config(['platform.plans' => $plans]);

        $text = $this->instructions('assessment');

        $this->assertStringContainsString('Semaine (« Pour essayer ») : 2 500 francs CFA pour 7 jours, 15 minutes par jour, soit moins de 400 francs CFA par jour.', $text);
        $this->assertStringContainsString('The cheapest plan, Semaine, costs 2 500 francs CFA for 7 days', $text);

        // Aucun montant écrit à la main dans les consignes.
        $config = file_get_contents(config_path('teacher.php'));
        $this->assertDoesNotMatchRegularExpression('/\d[\d  ]*\s*(FCFA|francs)/u', $config);
    }

    public function test_the_price_per_day_is_exact_or_rounded_up_never_lower_than_the_truth(): void
    {
        $this->assertSame('soit 250 francs CFA par jour', PlanFacts::perDay(Plan::find('essentiel')));
        $this->assertSame('soit moins de 300 francs CFA par jour', PlanFacts::perDay(Plan::find('semaine')));
        $this->assertSame('soit moins de 500 francs CFA par jour', PlanFacts::perDay(Plan::find('intensif')));
    }

    public function test_the_old_contradiction_is_gone(): void
    {
        $text = $this->instructions('assessment');

        // Avant : « Never talk about … prices » et « if you do not know, … WhatsApp (green button on the « Aide » page) ».
        $this->assertStringNotContainsString('or about prices', $text);
        $this->assertStringNotContainsString('green button on the « Aide » page', $text);
        $this->assertStringNotContainsString('Never invent anything (prices', $text);
        $this->assertStringContainsString('If the learner asks about prices or plans, answer them directly', $text);
        // Où sont les formules : sous le résultat, à la fin de l'évaluation (rien à chercher ailleurs).
        $this->assertStringContainsString('« Les formules s\'afficheront juste sous votre résultat, à la fin de notre échange', $text);
        $this->assertStringContainsString('ending with « Les formules s\'affichent juste sous votre résultat. »', $text);
        // Pendant une séance, la barre du bas est cachée : jamais « touchez Aide » sans « après notre échange ».
        $this->assertStringContainsString('During a conversation with you the bottom bar is hidden', $text);
        $this->assertStringContainsString('« Après notre échange, touchez « Aide », puis le bouton vert WhatsApp. »', $text);
        $this->assertStringNotContainsString('touch « Aide » in the bottom bar, then the green WhatsApp button', $text);

        // Abonné : renouvellement par « Mon espace », jamais de vente pendant la leçon.
        $lesson = $this->instructions('lesson', Level::B1);
        $this->assertStringContainsString('« Dans « Mon espace », touchez « Choisir ma formule ». »', $lesson);
        $this->assertStringContainsString('never try to sell during the lesson', $lesson);
    }

    public function test_objections_get_true_facts_and_never_pressure(): void
    {
        $text = $this->instructions('assessment');

        $this->assertStringContainsString('"C\'est cher"', $text);
        $this->assertStringContainsString('Payment is never automatic', $text);
        $this->assertStringContainsString('The oral assessment is free (5 minutes, once per account)', $text);
        $this->assertStringContainsString('"Je n\'ai pas le temps": 15 minutes a day', $text);
        $this->assertStringContainsString('"Est-ce que ça marche vraiment ?": be honest', $text);
        $this->assertStringContainsString('Never promise a result, a level or a delay', $text);
        $this->assertStringContainsString((string) config('platform.live.disconnect_rule'), $text);
        $this->assertStringContainsString('Never give a figure for data use', $text);
        $this->assertStringContainsString('suggest kindly that they decide with a parent', $text);
        $this->assertStringContainsString('Never ask a minor for personal details', $text);
        $this->assertStringContainsString('NEVER PRESSURE: no urgency, no "limited places", no invented figure, discount, testimonial or number of learners', $text);
        $this->assertStringContainsString('WHATSAPP ONLY FOR A REAL PROBLEM', $text);
    }

    public function test_he_says_he_is_a_virtual_teacher_and_understands_badly_worded_questions(): void
    {
        $text = $this->instructions('lesson', Level::A1);

        $this->assertStringContainsString('a VIRTUAL teacher (« un professeur virtuel »)', $text);
        $this->assertStringContainsString('never claim to be a human', $text);
        $this->assertStringContainsString('Look for the INTENTION behind the words', $text);
        $this->assertStringContainsString('« Vous voulez dire … ? »', $text);
    }

    public function test_he_teaches_with_tact_purpose_and_humour(): void
    {
        foreach (['lesson', 'course'] as $kind) {
            $text = $this->instructions($kind, Level::A2);

            $this->assertStringContainsString('NOTICE DIFFICULTY KINDLY', $text, $kind);
            $this->assertStringContainsString('EVERY REPETITION HAS A PURPOSE', $text, $kind);
            $this->assertStringContainsString('Never make them repeat the same item more than twice in a row', $text, $kind);
            $this->assertStringContainsString('PRAISE REAL PROGRESS', $text, $kind);
            $this->assertStringContainsString('a very short story of daily life linked to the lesson (two sentences at most', $text, $kind);
            $this->assertStringContainsString('Then say WHY in one short sentence', $text, $kind);
        }

        $this->assertStringContainsString('answer briefly and kindly (one sentence; never medical, legal, financial or political advice), then bring them back gently', $this->instructions('lesson', Level::B1));
    }

    public function test_the_new_rules_name_no_provider_and_no_forbidden_wording(): void
    {
        $advisor = mb_strtolower((string) config('teacher.advisor'));

        foreach (['gemini', 'openai', 'chatgpt', 'google', 'garanti', '100 %', 'sans engagement', 'carte bancaire', 'certificat', 'diplôme'] as $word) {
            $this->assertStringNotContainsString($word, $advisor, $word);
        }
    }
}
