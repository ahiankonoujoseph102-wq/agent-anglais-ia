<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AccessGrant;
use App\Support\Gemini\WelcomeMessage;
use App\Support\Greeting;
use App\Support\Plan;
use App\Support\PrivateStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Bienvenue de Teacher Joe : page montrée une seule fois après l'inscription, court message à la première
 * ouverture d'un accès, salutation selon l'heure du pays, messages vocaux générés une fois puis gardés.
 * Aucun appel réel à Gemini.
 */
class WelcomeTest extends TestCase
{
    use BuildsPrograms;
    use RefreshDatabase;

    /** Formulations jamais prononcées ni écrites par Teacher Joe. */
    private const FORBIDDEN = ['fait les devoirs', 'donne les réponses', 'donne les reponses', 'correction écrite', 'tous vos cours'];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['filesystems.private_disk' => 'local', 'services.gemini.key' => 'cle-de-test', 'app.timezone' => 'Africa/Lome']);
    }

    private static function fakeSpeech(): void
    {
        Http::fake(['*/interactions' => Http::response(['steps' => [[
            'type' => 'model_output',
            'content' => [['type' => 'audio', 'data' => base64_encode('RIFF-son-de-test')]],
        ]]])]);
    }

    private function newcomer(string $name = 'Koffi Mensah', string $phone = '+22890123456'): User
    {
        return User::factory()->unwelcomed()->create(['name' => $name, 'phone' => $phone]);
    }

    private function withPlan(User $user, string $plan = 'essentiel'): Subscription
    {
        return Subscription::factory()->for($user)->forPlan($plan)->create();
    }

    private function texts(User $user, string $kind = WelcomeMessage::SIGNUP, ?string $period = null): array
    {
        return WelcomeMessage::texts($user, $kind, $period);
    }

    /* ---------- Page montrée une seule fois après l'inscription ---------- */

    public function test_registration_leads_to_the_welcome_page_which_is_shown_once(): void
    {
        $this->post('/inscription', ['name' => 'Koffi Mensah', 'phone' => '+22890123456', 'password' => 'motdepasse', 'password_confirmation' => 'motdepasse'])
            ->assertRedirect(route('welcome.show'));

        $user = User::firstOrFail();
        $this->assertNull($user->welcomed_at);

        $this->get(route('welcome.show'))->assertOk()
            ->assertSee('Bienvenue, Koffi !')
            ->assertSee('Félicitations pour votre inscription sur Talkami.')
            ->assertSee('Toucher pour écouter Teacher Joe')
            ->assertSee('Écoutez bien : il vous explique comment tout fonctionne.')
            ->assertSee('Pause')
            ->assertSee('Passer')
            ->assertDontSee('<video', false);

        $this->assertNotNull($user->fresh()->welcomed_at);

        // Une seule fois : ensuite « Mon espace », où le message se réécoute.
        $this->get(route('welcome.show'))->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Réécouter le message de bienvenue')
            ->assertSee(route('welcome.show', ['message' => 'signup']), false);
    }

    public function test_a_newcomer_who_opens_my_space_is_sent_to_the_welcome_page_first(): void
    {
        $this->actingAs($this->newcomer())->get(route('dashboard'))->assertRedirect(route('welcome.show'));

        // Les administrateurs et les comptes déjà accueillis ne sont jamais redirigés.
        $this->actingAs(User::factory()->admin()->unwelcomed()->create())->get(route('dashboard'))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();
    }

    public function test_the_welcome_message_can_be_listened_again_without_changing_anything(): void
    {
        $user = User::factory()->create(['name' => 'Ama Kodjo']);
        $before = $user->welcomed_at;

        $this->actingAs($user)->get(route('welcome.show', ['message' => 'signup']))->assertOk()->assertSee('Bienvenue, Ama !');
        $this->assertTrue($user->fresh()->welcomed_at->equalTo($before));

        // Le message de la formule demande un accès actif.
        $this->get(route('welcome.show', ['message' => 'plan']))->assertRedirect(route('dashboard'));
        $this->get(route('welcome.show'))->assertRedirect(route('dashboard'));
    }

    public function test_the_end_button_follows_the_state_of_the_account(): void
    {
        $url = route('assessment.show');
        $this->actingAs($this->newcomer())->get(route('welcome.show'))
            ->assertSee('Passer mon évaluation gratuite')->assertSee('href="'.$url.'"', false);

        $done = User::factory()->create();
        $done->assessment()->create(['allowed_seconds' => 300, 'used_seconds' => 300, 'level' => 'A2', 'status' => 'completed']);
        $this->actingAs($done)->get(route('welcome.show', ['message' => 'signup']))
            ->assertSee('Aller à mon espace')->assertDontSee('Passer mon évaluation gratuite');
    }

    /* ---------- Salutation et prénom ---------- */

    public function test_the_greeting_follows_the_hour_and_the_country_of_the_learner(): void
    {
        $togo = $this->newcomer('Koffi', '+22890123456');
        $benin = $this->newcomer('Adjoa', '+22997000000');
        $cases = [
            // heure UTC, Togo (UTC+0), Bénin (UTC+1)
            ['2026-10-17 04:59:00', 'evening', 'morning'],
            ['2026-10-17 05:00:00', 'morning', 'morning'],
            ['2026-10-17 13:59:00', 'morning', 'morning'],
            ['2026-10-17 14:00:00', 'morning', 'evening'],
            ['2026-10-17 14:59:00', 'morning', 'evening'],
            ['2026-10-17 15:00:00', 'evening', 'evening'],
            ['2026-10-17 23:30:00', 'evening', 'evening'],
            ['2026-10-17 03:59:00', 'evening', 'evening'],
            ['2026-10-17 04:00:00', 'evening', 'morning'],
        ];

        foreach ($cases as [$utc, $togoPeriod, $beninPeriod]) {
            $now = Carbon::parse($utc, 'UTC');
            $this->assertSame($togoPeriod, Greeting::period($togo, $now), "Togo à $utc");
            $this->assertSame($beninPeriod, Greeting::period($benin, $now), "Bénin à $utc");
        }

        // Burkina Faso et Côte d'Ivoire : UTC+0 comme le Togo.
        $at = Carbon::parse('2026-10-17 14:30:00', 'UTC');
        $this->assertSame('morning', Greeting::period($this->newcomer('A', '+22670000000'), $at));
        $this->assertSame('morning', Greeting::period($this->newcomer('B', '+2250700000000'), $at));
    }

    public function test_the_switch_hours_come_from_the_configuration(): void
    {
        $user = $this->newcomer();
        $now = Carbon::parse('2026-10-17 12:00:00', 'UTC');

        $this->assertSame('morning', Greeting::period($user, $now));

        config(['platform.welcome.evening_from' => 12]);
        $this->assertSame('evening', Greeting::period($user, $now));
    }

    public function test_the_opening_names_the_learner_in_french_then_in_english(): void
    {
        $this->travelTo(Carbon::parse('2026-10-17 18:00:00', 'UTC'));
        $user = $this->newcomer('koffi MENSAH');

        $this->assertSame('Bonsoir Koffi ! Good evening, Koffi !', $this->texts($user)['opening']);
        $this->assertSame('Bonjour Koffi ! Good morning, Koffi !', $this->texts($user, WelcomeMessage::SIGNUP, 'morning')['opening']);

        $this->actingAs($user)->get(route('welcome.show'))->assertSee('Bonsoir Koffi ! Good evening, Koffi !');
    }

    public function test_the_first_name_is_the_first_word_cleaned(): void
    {
        $this->assertSame('Koffi', Greeting::firstName('Koffi Mensah'));
        $this->assertSame('Adjoa', Greeting::firstName("   adjoa \n  kodjo "));
        $this->assertSame('Jean-Marc', Greeting::firstName('jean-marc Dupont'));
        $this->assertSame('Koffi', Greeting::firstName('Koffi123 <b>x</b>'));
        $this->assertSame(30, mb_strlen(Greeting::firstName(str_repeat('a', 80))));
    }

    /* ---------- Contenu : durées et limites lues dans la configuration, formulations interdites absentes ---------- */

    public function test_the_signup_message_follows_the_planned_order_and_reads_the_configuration(): void
    {
        config(['platform.assessment.max_minutes' => 7, 'platform.courses.session_minutes' => 12, 'platform.courses.sessions_per_day' => 3]);
        $texts = $this->texts($this->newcomer());
        $all = implode("\n", [$texts['opening'], ...$texts['paragraphs']]);

        $steps = [
            'Félicitations pour votre inscription sur Talkami, et bienvenue !',
            'Je suis Teacher Joe, votre professeur d\'anglais.',
            'Écoutez-moi bien',
            'évaluation orale gratuite',
            'une seule par compte',
            'dure 7 minutes au plus',
            'téléphone près de la bouche',
            'N\'ayez pas peur de vous tromper',
            'votre niveau s\'affiche',
            'Mobile Money',
            'court questionnaire',
            'programme personnel',
            'chaque jour, vous avez votre séance orale',
            '« Mes cours »',
            'jusqu\'à 3 leçons par jour, de 12 minutes chacune',
            'uniquement pour l\'anglais',
            'Let\'s start',
        ];
        $position = -1;

        foreach ($steps as $step) {
            $found = mb_stripos($all, $step);
            $this->assertNotFalse($found, "Manque : $step");
            $this->assertGreaterThan($position, $found, "Hors d'ordre : $step");
            $position = $found;
        }

        // Aucun chiffre écrit à la main : tout vient de la configuration.
        $raw = implode("\n", (array) config('teacher.welcome.signup'));
        $this->assertDoesNotMatchRegularExpression('/\d/', str_replace(['A1', 'C2'], '', $raw));
    }

    public function test_no_forbidden_wording_in_the_messages_or_the_page(): void
    {
        $user = $this->withPlanUser();
        $texts = [];

        foreach (['signup', 'plan', 'plan_ready'] as $key) {
            $texts[] = implode("\n", (array) config("teacher.welcome.{$key}"));
        }

        $texts[] = $this->actingAs($user)->get(route('welcome.show', ['message' => 'signup']))->getContent();
        $texts[] = file_get_contents(resource_path('teacher/site-guide.md'));

        // Vocabulaire imposé : jamais « certificat » ni « diplôme » dans les messages (la fiche du site les cite pour les interdire).
        foreach (array_slice($texts, 0, 4) as $text) {
            $this->assertStringNotContainsStringIgnoringCase('certificat', $text);
            $this->assertStringNotContainsStringIgnoringCase('diplôme', $text);
        }

        foreach ($texts as $text) {
            foreach (self::FORBIDDEN as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase($forbidden, $text);
            }
        }
    }

    private function withPlanUser(): User
    {
        $user = User::factory()->create(['name' => 'Ama Kodjo']);
        $this->withPlan($user);

        return $user;
    }

    /* ---------- Messages vocaux : générés une fois, gardés, coût réduit ---------- */

    public function test_the_audio_pieces_are_generated_with_the_teacher_voice_then_kept(): void
    {
        self::fakeSpeech();
        $user = $this->newcomer();
        $this->actingAs($user);
        $count = WelcomeMessage::count($this->texts($user));

        for ($index = 0; $index < $count; $index++) {
            $this->get(route('welcome.audio', ['kind' => 'signup', 'index' => $index, 'salut' => 'evening']))
                ->assertOk()
                ->assertHeader('Content-Type', 'audio/wav');
        }

        Http::assertSentCount($count);
        Http::assertSent(fn (Request $request) => $request['generation_config']['speech_config'][0]['voice'] === config('services.gemini.voice')
            && $request['model'] === config('services.gemini.tts_model')
            && str_contains($request['input'][0]['content'][0]['annotations'][0]['style'], 'welcoming a new learner'));
        $this->assertTrue(PrivateStorage::disk()->exists(WelcomeMessage::path($this->texts($user, 'signup', 'evening')['opening'], (string) config('services.gemini.voice'))));

        // Réécoute : plus aucun appel à Google.
        Http::fake();

        for ($index = 0; $index < $count; $index++) {
            $this->get(route('welcome.audio', ['kind' => 'signup', 'index' => $index, 'salut' => 'evening']))->assertOk();
        }

        Http::assertNothingSent();
    }

    public function test_only_the_opening_is_generated_for_each_new_learner(): void
    {
        self::fakeSpeech();
        $first = $this->newcomer('Koffi Mensah', '+22890000001');
        $count = WelcomeMessage::count($this->texts($first));

        for ($index = 0; $index < $count; $index++) {
            $this->actingAs($first)->get(route('welcome.audio', ['kind' => 'signup', 'index' => $index, 'salut' => 'evening']))->assertOk();
        }

        Http::assertSentCount($count);

        // Un autre prénom : seule l'ouverture est nouvelle, les paragraphes sont déjà gardés.
        $second = $this->newcomer('Adjoa Kodjo', '+22890000002');

        for ($index = 0; $index < $count; $index++) {
            $this->actingAs($second)->get(route('welcome.audio', ['kind' => 'signup', 'index' => $index, 'salut' => 'evening']))->assertOk();
        }

        Http::assertSentCount($count + 1);
        Http::assertSent(fn (Request $request) => str_contains($request['input'][0]['content'][0]['text'], 'Bonsoir Adjoa ! Good evening, Adjoa !'));

        // Le même prénom que le premier, le même soir : rien de nouveau du tout.
        $third = $this->newcomer('Koffi Agbeko', '+22890000003');

        for ($index = 0; $index < $count; $index++) {
            $this->actingAs($third)->get(route('welcome.audio', ['kind' => 'signup', 'index' => $index, 'salut' => 'evening']))->assertOk();
        }

        Http::assertSentCount($count + 1);
    }

    public function test_the_salutation_asked_by_the_page_is_the_one_spoken(): void
    {
        self::fakeSpeech();
        $user = $this->newcomer();

        $this->actingAs($user)->get(route('welcome.audio', ['kind' => 'signup', 'index' => 0, 'salut' => 'morning']))->assertOk();

        Http::assertSent(fn (Request $request) => str_contains($request['input'][0]['content'][0]['text'], 'Bonjour Koffi ! Good morning, Koffi !'));
    }

    public function test_unknown_pieces_and_guests_are_refused_and_a_failure_is_not_retried_at_once(): void
    {
        Http::fake(['*/interactions' => Http::response('erreur', 500)]);
        $user = $this->newcomer();

        $this->get(route('welcome.audio', ['kind' => 'signup', 'index' => 0]))->assertRedirect(route('login'));

        $this->actingAs($user);
        $this->get(route('welcome.audio', ['kind' => 'inconnu', 'index' => 0]))->assertNotFound();
        $this->get(route('welcome.audio', ['kind' => 'signup', 'index' => 99]))->assertNotFound();
        // Le message de la formule n'existe que pour un compte avec un accès.
        $this->get(route('welcome.audio', ['kind' => 'plan', 'index' => 0]))->assertNotFound();

        $this->get(route('welcome.audio', ['kind' => 'signup', 'index' => 1]))->assertStatus(503);
        $this->get(route('welcome.audio', ['kind' => 'signup', 'index' => 1]))->assertStatus(503);
        Http::assertSentCount(1);

        // La page, elle, reste utilisable : le texte est affiché.
        $this->get(route('welcome.show'))->assertOk()->assertSee('Je suis Teacher Joe, votre professeur d&#039;anglais.', false);
    }

    public function test_the_welcome_message_uses_neither_the_assessment_nor_the_oral_minutes(): void
    {
        self::fakeSpeech();
        $user = $this->newcomer();
        $this->withPlan($user);
        $remaining = $user->remainingSecondsToday();

        $this->actingAs($user)->get(route('welcome.show'))->assertOk();
        $this->get(route('welcome.audio', ['kind' => 'signup', 'index' => 0]))->assertOk();

        $this->assertSame(0, $user->assessment()->count());
        $this->assertSame(0, $user->lessons()->count());
        $this->assertSame(0, $user->courseSessions()->count());
        $this->assertSame($remaining, $user->fresh()->remainingSecondsToday());
    }

    /* ---------- Message court à l'ouverture d'un accès ---------- */

    public function test_a_provisional_access_opens_the_plan_message_once(): void
    {
        $user = User::factory()->create(['name' => 'Ama Kodjo', 'access_welcomed_at' => null]);
        Subscription::factory()->for($user)->create(['status' => SubscriptionStatus::Provisional, 'provisional_until' => now()->addHours(20)]);
        $payment = Payment::query()->forceCreate([
            'user_id' => $user->id, 'plan' => 'essentiel', 'plan_name' => 'Essentiel', 'daily_minutes' => 15, 'duration_days' => 30,
            'amount' => 5000, 'currency' => 'XOF', 'country' => 'TG', 'operator' => 'flooz', 'operator_name' => 'Flooz',
            'recipient_number' => '+22890000000', 'proof_path' => 'proofs/test.jpg', 'status' => PaymentStatus::Pending,
        ]);

        // L'apprenant arrive sur la page de son paiement : bienvenue de la formule d'abord.
        $this->actingAs($user)->get(route('payment.show', $payment))->assertRedirect(route('welcome.show'));

        $this->get(route('welcome.show'))->assertOk()
            ->assertSee('Bravo, Ama !')
            ->assertSee('Votre formule Essentiel est active.')
            ->assertSee('Toucher pour écouter Teacher Joe')
            ->assertSee('Construire mon programme')
            ->assertSee(route('program.questionnaire', 1), false);

        $this->assertNotNull($user->fresh()->access_welcomed_at);
        $this->get(route('payment.show', $payment))->assertOk();
        $this->get(route('welcome.show'))->assertRedirect(route('dashboard'));
    }

    public function test_a_payment_validated_by_the_administrator_opens_the_plan_message_at_the_next_visit(): void
    {
        $user = User::factory()->create(['name' => 'Ama Kodjo', 'access_welcomed_at' => null]);
        $payment = Payment::query()->forceCreate([
            'user_id' => $user->id, 'plan' => 'intensif', 'plan_name' => 'Intensif', 'daily_minutes' => 30, 'duration_days' => 30,
            'amount' => 9000, 'currency' => 'XOF', 'country' => 'TG', 'operator' => 'flooz', 'operator_name' => 'Flooz',
            'recipient_number' => '+22890000000', 'proof_path' => 'proofs/test.jpg', 'status' => PaymentStatus::Pending,
        ]);

        // En attente : rien à montrer encore.
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $payment->approve(User::factory()->admin()->create());

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('welcome.show'));
        $this->get(route('welcome.show'))->assertOk()->assertSee('Votre formule Intensif est active.');
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_a_granted_access_opens_the_plan_message_too(): void
    {
        $user = User::factory()->create(['name' => 'Yao Agbeko', 'access_welcomed_at' => null]);
        AccessGrant::offer($user, User::factory()->admin()->create(), Plan::find('semaine'), 3, null);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('welcome.show'));
        $this->get(route('welcome.show'))->assertOk()->assertSee('Votre formule Semaine est active.');
    }

    public function test_the_plan_message_reads_the_plan_name_and_the_programme_state(): void
    {
        config(['platform.plans.essentiel.name' => 'Découverte']);
        $user = User::factory()->create(['name' => 'Ama Kodjo', 'access_welcomed_at' => null]);
        $this->withPlan($user);

        $texts = $this->texts($user, WelcomeMessage::PLAN);
        $this->assertSame('Félicitations ! Votre formule Découverte est maintenant active.', $texts['paragraphs'][0]);
        $this->assertStringContainsString('court questionnaire', implode(' ', $texts['paragraphs']));
        $this->assertLessThan(5, count($texts['paragraphs']));

        // Questionnaire déjà rempli : « Parler avec Teacher Joe », et un message qui ne parle plus du questionnaire.
        $this->buildProgram($user);
        $this->actingAs($user)->get(route('welcome.show', ['message' => 'plan']))->assertOk()
            ->assertSee('Parler avec Teacher Joe')
            ->assertSee(route('lesson.show'), false)
            ->assertDontSee('Construire mon programme');
        $this->assertStringNotContainsString('questionnaire', implode(' ', $this->texts($user, WelcomeMessage::PLAN)['paragraphs']));
    }

    public function test_the_plan_message_is_short(): void
    {
        $words = fn (array $paragraphs) => str_word_count(implode(' ', $paragraphs), 0, 'àâäéèêëîïôöùûüç');

        $user = User::factory()->create();
        $this->withPlan($user);

        $this->assertLessThan(90, $words($this->texts($user, WelcomeMessage::PLAN)['paragraphs']));
        $this->assertGreaterThan(200, $words($this->texts($user)['paragraphs']));
        $this->assertLessThan(450, $words($this->texts($user)['paragraphs']));
    }

    public function test_my_space_offers_the_plan_message_again_once_it_was_seen(): void
    {
        $user = User::factory()->create();
        $this->withPlan($user);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Réécouter le message de bienvenue')
            ->assertSee('Réécouter le message de ma formule')
            ->assertSee(route('welcome.show', ['message' => 'plan']), false);

        $this->get(route('welcome.show', ['message' => 'plan']))->assertOk()->assertSee('Bravo,');
    }

    public function test_the_page_is_light_and_loads_nothing_before_the_touch(): void
    {
        $page = $this->actingAs($this->newcomer())->get(route('welcome.show'))->assertOk();
        $html = $page->getContent();

        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringNotContainsString('<audio', $html);
        $this->assertStringContainsString('js/welcome.js', $html);
        // Les morceaux sont des adresses, demandées seulement après le toucher.
        $this->assertStringContainsString('data-pieces', $html);
        $this->assertLessThan(60 * 1024, strlen($html));
    }
}
