<?php

namespace Tests\Feature;

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
        // Avant : les consignes de main après la pull request #111 ; après : celles des meilleurs centres.
        $this->assertStringContainsString('How you teach (like a real teacher):', $bodies[0]);
        $this->assertStringNotContainsString('WHEN THE LEARNER STRUGGLES', $bodies[0]);
        $this->assertStringNotContainsString('How you teach (like a real teacher):', $bodies[1]);
        $this->assertStringContainsString('WHEN THE LEARNER STRUGGLES', $bodies[1]);
        $this->assertStringContainsString('WRITTEN REHEARSAL', $bodies[1]);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/interactions'));

        // Les vraies consignes ne sont pas modifiées par la comparaison.
        $this->assertStringContainsString('WHEN THE LEARNER STRUGGLES', (string) config('teacher.persona'));
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
}
