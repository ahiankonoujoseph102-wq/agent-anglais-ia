<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Support\Curriculum\Curriculum;
use App\Support\Gemini\TeacherRehearsal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Banc d'essai de Teacher Joe (« php artisan teacher:rehearse ») : scénarios inventés, joués avec les consignes
 * d'aujourd'hui et, avec --compare, celles d'avant ; vérifications automatiques. Aucun appel réel : le modèle
 * texte est simulé.
 */
class TeacherRehearsalTest extends TestCase
{
    use RefreshDatabase;

    private function fakeReplies(string ...$replies): void
    {
        config(['services.gemini.key' => 'cle-de-test', 'services.text.providers' => ['gemini']]);

        $sequence = Http::sequence();
        foreach ($replies as $reply) {
            $sequence->push(['steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $reply]]]], 'usage' => ['total_input_tokens' => 9000, 'total_output_tokens' => 60]]);
        }

        Http::fake(['*/interactions' => $sequence]);
    }

    public function test_the_scenarios_are_invented_and_well_formed(): void
    {
        $scenarios = TeacherRehearsal::scenarios();

        $this->assertGreaterThanOrEqual(15, count($scenarios));

        foreach ($scenarios as $key => $scenario) {
            $this->assertContains($scenario['kind'], ['assessment', 'lesson', 'course'], $key);
            $this->assertNotEmpty($scenario['title'], $key);
            $this->assertSame('learner', end($scenario['history'])[0], $key);

            $text = json_encode($scenario, JSON_UNESCAPED_UNICODE);
            // Aucune donnée réelle : ni numéro de téléphone, ni adresse e-mail, ni lien.
            $this->assertDoesNotMatchRegularExpression('/\d{2}[\s.]?\d{2}[\s.]?\d{2}[\s.]?\d{2}/', $text, $key);
            $this->assertDoesNotMatchRegularExpression('/@|https?:/', $text, $key);
        }

        // Les consignes d'avant ne remplacent que des clés existantes de config/teacher.php.
        foreach (array_keys(TeacherRehearsal::previous()) as $key) {
            $this->assertNotNull(config("teacher.{$key}"), $key);
        }
    }

    public function test_the_price_checks_accept_digits_and_words_read_from_the_configuration(): void
    {
        $scenario = ['must' => [['{price:semaine}']], 'never' => ['whatsapp'], 'max_words' => 20];

        $this->assertSame([true, true, true], array_column(TeacherRehearsal::check('La formule Semaine coûte deux mille francs CFA pour sept jours.', $scenario), 'ok'));
        $this->assertSame([true, true, true], array_column(TeacherRehearsal::check('Semaine : 2 000 FCFA.', $scenario), 'ok'));
        $this->assertSame([false, false, true], array_column(TeacherRehearsal::check('Touchez Aide puis écrivez sur WhatsApp.', $scenario), 'ok'));
    }

    public function test_compare_plays_the_same_scenario_with_the_old_and_the_new_instructions(): void
    {
        $this->fakeReplies(
            'Pour les prix, touchez « Aide », puis le bouton vert WhatsApp.',
            'La formule Semaine coûte deux mille francs CFA pour sept jours. Vous choisirez juste sous votre résultat. Now, what do you do in Lomé?',
        );

        $this->artisan('teacher:rehearse', ['--compare' => true, '--only' => 'prix-pendant-evaluation', '--pause' => 0])
            ->expectsOutputToContain('Un prospect demande le prix pendant l\'évaluation')
            ->expectsOutputToContain('AVANT (gemini) : Pour les prix, touchez « Aide »')
            ->expectsOutputToContain('✗ ne dit pas « whatsapp »')
            ->expectsOutputToContain('APRÈS (gemini) : La formule Semaine coûte deux mille francs CFA')
            ->expectsOutputToContain('✓ dit « 2 000 »')
            ->expectsOutputToContain('Vérifications réussies AVANT : 1 sur 3.')
            ->expectsOutputToContain('Vérifications réussies APRÈS : 3 sur 3.')
            ->assertSuccessful();

        // Mêmes scénario et modèle ; seules les consignes changent.
        $bodies = Http::recorded()->map(fn (array $pair) => json_encode($pair[0]->data(), JSON_UNESCAPED_UNICODE))->values();
        $this->assertCount(2, $bodies);
        // Avant : les consignes de main après la pull request #119 ; après : l'évaluation qui s'adapte tout de suite.
        $this->assertStringContainsString('ADAPTIVE QUESTIONS', $bodies[0]);
        $this->assertStringNotContainsString('ADAPT AT ONCE', $bodies[0]);
        $this->assertStringNotContainsString('ADAPTIVE QUESTIONS', $bodies[1]);
        $this->assertStringContainsString('ADAPT AT ONCE', $bodies[1]);
        $this->assertStringContainsString('WRITTEN REHEARSAL', $bodies[1]);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/interactions'));

        // Les vraies consignes ne sont pas modifiées par la comparaison.
        $this->assertStringContainsString('ADAPT AT ONCE', (string) config('teacher.assessment'));
    }

    public function test_an_unknown_scenario_is_refused_and_no_provider_is_reported_clearly(): void
    {
        $this->artisan('teacher:rehearse', ['--only' => 'inconnu'])->expectsOutputToContain('Scénario inconnu : inconnu.')->assertFailed();

        config(['services.gemini.key' => null, 'services.openai.key' => null]);
        Http::fake();
        $this->artisan('teacher:rehearse', ['--only' => 'vraie-personne', '--pause' => 0])
            ->expectsOutputToContain('aucun fournisseur n\'a répondu')
            ->assertSuccessful();
    }

    public function test_the_errors_seen_in_real_conversations_have_invented_scenarios(): void
    {
        $scenarios = TeacherRehearsal::scenarios();

        foreach (['reponse-incomprehensible', 'phrase-autre-langue-lecon', 'meme-erreur-deux-fois', 'expliquer-en-francais', 'une-question-a-la-fois',
            'frais-caches', 'combien-de-temps', 'disponible-24h', 'jour-manque', 'presentation-mal-entendue', 'question-en-anglais',
            'objection-connexion', 'vraie-personne', 'hors-sujet', 'eleve-fatigue', 'repetition-mecanique'] as $key) {
            $this->assertArrayHasKey($key, $scenarios);
        }
    }

    public function test_one_question_at_a_time_is_checked(): void
    {
        $scenario = ['must' => [], 'never' => [], 'max_questions' => 1];

        $this->assertSame([true], array_column(TeacherRehearsal::check('A teacher! What do you teach?', $scenario), 'ok'));
        $this->assertSame([false], array_column(TeacherRehearsal::check('A teacher! What do you teach? And where?', $scenario), 'ok'));
    }

    public function test_the_new_facts_for_future_learners_are_in_the_instructions(): void
    {
        $text = TeacherRehearsal::instructions(TeacherRehearsal::scenarios()['combien-de-temps']);

        // Faits tirés du programme officiel (12 unités, 3 leçons par unité) : jamais une promesse.
        $this->assertStringContainsString('each level of the programme has 12 units of 3 lessons, so 36 lessons', $text);
        $this->assertStringContainsString('about 7 to 8 weeks', $text);
        $this->assertStringContainsString('Never promise fluency, a level or a delay.', $text);
        $this->assertStringContainsString('at any hour of the day or night', $text);
        $this->assertStringContainsString('the unused time of a day is not carried over', $text);
        $this->assertStringContainsString('no registration fee and no other fee from Talkami', $text);
        $this->assertStringContainsString('UNCLEAR ANSWER', $text);
    }

    public function test_the_best_teaching_practices_have_scenarios_for_a1_a2_and_b1(): void
    {
        $scenarios = TeacherRehearsal::scenarios();
        $levels = [];

        foreach (['echauffement-question-ouverte', 'verifier-par-une-question', 'eleve-bloque-astuce', 'sens-et-difference',
            'debutant-veut-parler', 'conversation-sans-interrompre', 'retour-sur-la-langue'] as $key) {
            $this->assertArrayHasKey($key, $scenarios);
            $levels[] = $scenarios[$key]['level'];
        }

        $this->assertEqualsCanonicalizing(['A1', 'A2', 'B1'], array_values(array_unique($levels)));

        // Jamais « C'est clair ? » : une question qui fait utiliser.
        $check = TeacherRehearsal::check('Pas de souci. Alors : « j\'ai 30 ans », comment dit-on ?', $scenarios['verifier-par-une-question']);
        $this->assertSame([true, true, true, true, true, true, true, true], array_column($check, 'ok'));
        $this->assertFalse(TeacherRehearsal::check('Is it clear? C\'est clair ?', $scenarios['verifier-par-une-question'])[1]['ok']);
    }

    public function test_three_assessment_profiles_get_their_own_questions_and_their_own_mini_lesson(): void
    {
        $scenarios = TeacherRehearsal::scenarios();
        $questions = ['evaluation-debutant-question', 'evaluation-intermediaire-question', 'evaluation-bon-niveau-question'];
        $lessons = ['evaluation-debutant-lecon', 'evaluation-intermediaire-lecon', 'evaluation-bon-niveau-lecon'];

        foreach ([...$questions, ...$lessons] as $key) {
            $this->assertSame('assessment', $scenarios[$key]['kind'], $key);
        }

        // La mini-leçon porte sur l'erreur de CHAQUE profil ; après, elle finit par ce qu'il apprendra ensuite.
        $after = array_map(fn ($key) => TeacherRehearsal::transcript($scenarios[$key]), $lessons);
        $this->assertStringContainsString('they said "I live Lomé", right form "I live in Lomé."', $after[0]);
        $this->assertStringContainsString('they said "I am working at the hospital since two years"', $after[1]);
        $this->assertStringContainsString('a more natural phrase: "We\'d like to cut out the middleman."', $after[2]);
        $this->assertCount(3, array_unique($after));

        foreach ($lessons as $key) {
            $before = TeacherRehearsal::transcript($scenarios[$key], before: true);
            $this->assertStringContainsString('ONE sincere encouragement', $before, $key);
            $this->assertStringNotContainsString('vous apprendrez', $before, $key);
            $this->assertStringContainsString('vous apprendrez à', TeacherRehearsal::transcript($scenarios[$key]), $key);
        }

        // Les questions : la réponse attendue reprend ce que CHAQUE personne vient de dire ; jamais une question fixe.
        $this->assertCount(3, array_unique(array_map(fn ($key) => json_encode($scenarios[$key]['must']), $questions)));
        foreach ($questions as $key) {
            $this->assertContains('what is your name', $scenarios[$key]['never'], $key);
            $this->assertSame(1, $scenarios[$key]['max_questions'], $key);
        }
    }

    public function test_a_true_beginner_a_middle_level_and_an_advanced_learner_have_assessment_scenarios(): void
    {
        $scenarios = TeacherRehearsal::scenarios();

        foreach (['evaluation-vrai-debutant', 'evaluation-niveau-moyen', 'evaluation-niveau-avance', 'evaluation-bilan', 'evaluation-conclusion'] as $key) {
            $this->assertArrayHasKey($key, $scenarios);
            $this->assertSame('assessment', $scenarios[$key]['kind'], $key);
        }

        // Débutant : français rassurant au premier blocage ; avancé : jamais une question de débutant.
        $this->assertContains('normal', $scenarios['evaluation-vrai-debutant']['must'][0]);
        $this->assertContains('where do you live', $scenarios['evaluation-niveau-avance']['never']);
        // Bilan : la suite renvoyée par la fonction porte sur SON erreur et sur le vrai programme de son niveau (A1).
        $bilan = TeacherRehearsal::transcript($scenarios['evaluation-bilan']);
        $this->assertStringContainsString('they said "My son have 8 years"', $bilan);
        $this->assertStringContainsString('vous apprendrez à '.Curriculum::unit(Level::A1, 1)->objectives[0], $bilan);
        // Conclusion : ni intelligence artificielle, ni urgence, ni promesse.
        $this->assertContains('intelligence artificielle', $scenarios['evaluation-conclusion']['never']);
        $this->assertContains('places limitees', $scenarios['evaluation-conclusion']['never']);
    }
}
