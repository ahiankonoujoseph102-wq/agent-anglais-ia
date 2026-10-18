<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AccessGrant;
use App\Support\Gemini\LevelMessage;
use App\Support\Gemini\SpeechLog;
use App\Support\Gemini\WelcomeMessage;
use App\Support\Greeting;
use App\Support\Plan;
use App\Support\PrivateStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Bienvenue de Teacher Joe : page montrée une seule fois à la première ouverture d'un accès (accès
 * provisoire, paiement validé, accès offert), jamais après la seule inscription ; salutation selon l'heure
 * du pays ; message vocal généré une fois par morceau puis gardé. Aucun appel réel à Gemini.
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

    private static function speech(): array
    {
        return ['steps' => [[
            'type' => 'model_output',
            'content' => [['type' => 'audio', 'data' => base64_encode('RIFF-son-de-test')]],
        ]]];
    }

    private static function fakeSpeech(): void
    {
        Http::fake(['*/interactions' => Http::response(self::speech())]);
    }

    /** Apprenant dont l'accès vient de s'ouvrir : message de bienvenue pas encore vu. */
    private function newcomer(string $name = 'Koffi Mensah', string $phone = '+22890123456', string $plan = 'essentiel'): User
    {
        $user = User::factory()->unwelcomed()->create(['name' => $name, 'phone' => $phone]);
        Subscription::factory()->for($user)->forPlan($plan)->create();

        return $user;
    }

    private function texts(User $user, ?string $period = null): array
    {
        return WelcomeMessage::texts($user, $period);
    }

    private function pendingPayment(User $user, string $plan = 'essentiel'): Payment
    {
        $config = Plan::find($plan);

        return Payment::query()->forceCreate([
            'user_id' => $user->id, 'plan' => $config->key, 'plan_name' => $config->name, 'daily_minutes' => $config->dailyMinutes,
            'duration_days' => $config->durationDays, 'amount' => $config->price, 'currency' => 'XOF', 'country' => 'TG',
            'operator' => 'flooz', 'operator_name' => 'Flooz', 'recipient_number' => '+22890000000',
            'proof_path' => 'proofs/test.jpg', 'status' => PaymentStatus::Pending,
        ]);
    }

    private function listenToEveryPiece(User $user, string $period = 'evening'): int
    {
        $count = WelcomeMessage::count($this->texts($user));

        for ($index = 0; $index < $count; $index++) {
            $this->actingAs($user)->get(route('welcome.audio', ['index' => $index, 'salut' => $period]))
                ->assertOk()
                ->assertHeader('Content-Type', 'audio/wav');
        }

        return $count;
    }

    /* ---------- Après l'inscription : le parcours d'avant, sans page de bienvenue ---------- */

    public function test_registration_keeps_the_previous_path_without_welcome_page(): void
    {
        $this->post('/inscription', ['name' => 'Koffi Mensah', 'phone' => '+22890123456', 'password' => 'motdepasse', 'password_confirmation' => 'motdepasse'])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Passez votre évaluation gratuite')
            ->assertDontSee('Réécouter le message de bienvenue');

        // Sans accès : aucune page de bienvenue, ni automatique ni à la demande.
        $this->get(route('welcome.show'))->assertRedirect(route('dashboard'));
        $this->get(route('welcome.show', ['reecouter' => 1]))->assertRedirect(route('dashboard'));
        $this->get(route('welcome.audio', ['index' => 0]))->assertNotFound();
        $this->assertNull(User::firstOrFail()->welcomed_at);
    }

    /* ---------- À la première ouverture d'un accès, une seule fois ---------- */

    public function test_a_provisional_access_leads_straight_to_the_welcome_page_once(): void
    {
        $user = User::factory()->unwelcomed()->create(['name' => 'Ama Kodjo']);
        Subscription::factory()->for($user)->create(['status' => SubscriptionStatus::Provisional, 'provisional_until' => now()->addHours(20)]);
        $payment = $this->pendingPayment($user);

        // Après l'envoi de la capture, l'apprenant est mené à la page de son paiement : la bienvenue d'abord.
        $this->actingAs($user)->withSession(['status' => 'Accès provisoire ouvert.'])
            ->get(route('payment.show', $payment))->assertRedirect(route('welcome.show'));

        $this->get(route('welcome.show'))->assertOk()
            ->assertSee('Bienvenue, Ama !')
            ->assertSee('Félicitations pour votre formule Essentiel !')
            ->assertSee('Toucher pour écouter Teacher Joe')
            ->assertSee('Écoutez bien : il vous explique comment tout fonctionne.')
            ->assertSee('Pause')
            ->assertSee('Passer')
            ->assertSee('Construire mon programme')
            ->assertSee(route('program.questionnaire', 1), false)
            // Le message de la page du paiement n'est pas perdu.
            ->assertSee('Accès provisoire ouvert.');

        $this->assertNotNull($user->fresh()->welcomed_at);

        // Une seule fois : ensuite les pages habituelles, et la réécoute dans « Mon espace ».
        $this->get(route('payment.show', $payment))->assertOk();
        $this->get(route('welcome.show'))->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Réécouter le message de bienvenue')
            ->assertSee(route('welcome.show', ['reecouter' => 1]), false);
    }

    public function test_a_payment_validated_by_the_administrator_opens_the_welcome_at_the_next_visit(): void
    {
        $user = User::factory()->unwelcomed()->create(['name' => 'Ama Kodjo']);
        $payment = $this->pendingPayment($user, 'intensif');

        // En attente de vérification : rien à montrer encore.
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('Réécouter le message de bienvenue');
        $this->get(route('payment.show', $payment))->assertOk();

        $payment->approve(User::factory()->admin()->create());

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('welcome.show'));
        $this->get(route('welcome.show'))->assertOk()->assertSee('Félicitations pour votre formule Intensif !');
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_a_granted_access_opens_the_welcome_too(): void
    {
        $user = User::factory()->unwelcomed()->create(['name' => 'Yao Agbeko']);
        AccessGrant::offer($user, User::factory()->admin()->create(), Plan::find('semaine'), 3, null);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('welcome.show'));
        $this->get(route('welcome.show'))->assertOk()->assertSee('Bienvenue, Yao !')->assertSee('Félicitations pour votre formule Semaine !');
    }

    public function test_administrators_and_accounts_already_welcomed_are_never_redirected(): void
    {
        $admin = User::factory()->admin()->unwelcomed()->create();
        Subscription::factory()->for($admin)->create();
        $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        $welcomed = User::factory()->create();
        Subscription::factory()->for($welcomed)->create();
        $this->actingAs($welcomed)->get(route('dashboard'))->assertOk();

        // Mot de passe provisoire : le changement passe avant tout.
        $temporary = $this->newcomer();
        $temporary->forceFill(['must_change_password' => true])->save();
        $this->actingAs($temporary)->get(route('dashboard'))->assertRedirect(route('password.change'));
    }

    public function test_the_welcome_can_be_listened_again_only_after_an_access(): void
    {
        $user = User::factory()->create(['name' => 'Ama Kodjo']);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('Réécouter le message de bienvenue');

        // Un accès, même terminé, permet de réécouter ; rien n'est marqué de nouveau.
        Subscription::factory()->for($user)->expired()->create();
        $before = $user->fresh()->welcomed_at;

        $this->get(route('dashboard'))->assertOk()->assertSee('Réécouter le message de bienvenue');
        $this->get(route('welcome.show', ['reecouter' => 1]))->assertOk()->assertSee('Bienvenue, Ama !');
        $this->assertTrue($user->fresh()->welcomed_at->equalTo($before));
        $this->get(route('welcome.show'))->assertRedirect(route('dashboard'));
    }

    public function test_with_a_programme_already_built_the_end_button_opens_the_daily_lesson(): void
    {
        $user = $this->newcomer('Ama Kodjo');
        $this->buildProgram($user);

        $this->actingAs($user)->get(route('welcome.show'))->assertOk()
            ->assertSee('Parler avec Teacher Joe')
            ->assertSee(route('lesson.show'), false)
            ->assertDontSee('Construire mon programme');

        $paragraphs = implode(' ', $this->texts($user)['paragraphs']);
        $this->assertStringContainsString('votre programme personnel. Il est déjà prêt', $paragraphs);
        $this->assertStringNotContainsString('questionnaire', $paragraphs);
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
        $this->assertSame('Bonjour Koffi ! Good morning, Koffi !', $this->texts($user, 'morning')['opening']);
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

    /* ---------- Contenu : ordre imposé, valeurs lues dans la configuration, formulations interdites absentes ---------- */

    public function test_the_message_follows_the_planned_order_and_reads_the_configuration(): void
    {
        config(['platform.plans.intensif.name' => 'Progrès', 'platform.courses.session_minutes' => 12, 'platform.courses.sessions_per_day' => 3]);
        $texts = $this->texts($this->newcomer(plan: 'intensif'));
        $all = implode("\n", [$texts['opening'], ...$texts['paragraphs']]);

        $steps = [
            // a) salutation, en anglais aussi, félicitations pour la formule, bienvenue
            'Koffi !', 'Good ', 'Félicitations pour votre formule Progrès, et bienvenue sur Talkami !',
            // b) présentation
            'Je suis Teacher Joe, votre professeur d\'anglais.', 'très bon choix pour parler anglais avec confiance',
            // c) écouter attentivement
            'Écoutez-moi bien', 'profiter pleinement de votre formule',
            // d) étape 1 : le questionnaire
            'Première étape : un court questionnaire', 'programme personnel, adapté à votre objectif et à votre rythme',
            // e) étape 2 : la séance orale et ses règles (durée lue dans la formule de l'apprenant)
            'Deuxième étape : chaque jour, votre séance orale', 'vous donne 30 minutes de séance par jour',
            'endroit calme', 'téléphone près de la bouche', 'parlez à voix haute', 'N\'ayez pas peur de vous tromper',
            'la séance s\'arrête toute seule', '« Interrompre le professeur »', 'faire une pause', 'couper votre micro',
            // f) « Mes cours », limites de la configuration
            '« Mes cours »', 'leçon ou votre exercice d\'anglais', 'je vous l\'explique', 'je réponds à vos questions',
            'je vous guide pour faire vous-même l\'exercice', 'jusqu\'à 3 leçons par jour, de 12 minutes chacune', 'uniquement pour l\'anglais',
            // g) conclusion motivante, en anglais traduit
            'Let\'s start !', 'Cela veut dire : c\'est parti !',
        ];
        $position = -1;

        foreach ($steps as $step) {
            $found = mb_strpos($all, $step);
            $this->assertNotFalse($found, "Manque : $step");
            $this->assertGreaterThan($position, $found, "Hors d'ordre : $step");
            $position = $found;
        }

        // Le nom de la formule et ses minutes suivent l'accès de l'apprenant.
        $this->assertStringContainsString('vous donne 15 minutes de séance par jour', implode(' ', $this->texts($this->newcomer('Ama', '+22890000001'))['paragraphs']));

        // Aucun chiffre écrit à la main : tout vient de la configuration.
        $raw = implode("\n", [...(array) config('teacher.welcome.paragraphs'), config('teacher.welcome.program_ready'), config('teacher.welcome.opening')]);
        $this->assertDoesNotMatchRegularExpression('/\d/', $raw);
    }

    public function test_the_message_lasts_two_to_three_minutes(): void
    {
        // Voix calme : environ 130 mots par minute.
        $words = count(preg_split('/\s+/u', trim(implode(' ', $this->texts($this->newcomer())['paragraphs']))));

        $this->assertGreaterThan(200, $words);
        $this->assertLessThan(390, $words);
    }

    public function test_no_forbidden_wording_in_the_message_or_the_page(): void
    {
        $user = $this->newcomer();
        $texts = [
            implode("\n", (array) config('teacher.welcome.paragraphs')),
            (string) config('teacher.welcome.program_ready'),
            $this->actingAs($user)->get(route('welcome.show'))->getContent(),
        ];

        // Vocabulaire imposé : jamais « certificat » ni « diplôme » dans le message (la fiche du site les cite pour les interdire).
        foreach ($texts as $text) {
            $this->assertStringNotContainsStringIgnoringCase('certificat', $text);
            $this->assertStringNotContainsStringIgnoringCase('diplôme', $text);
        }

        $texts[] = file_get_contents(resource_path('teacher/site-guide.md'));

        foreach ($texts as $text) {
            foreach (self::FORBIDDEN as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase($forbidden, $text);
            }
        }

        // Plus aucun message « après l'inscription ».
        $this->assertNull(config('teacher.welcome.signup'));
        $this->assertNull(config('teacher.welcome.plan'));
    }

    /* ---------- Message vocal : généré une fois par morceau, gardé, coût réduit ---------- */

    private function prepareCommonPart(): int
    {
        Sleep::fake(syncWithCarbon: true);
        $this->artisan('app:prepare-voice')->assertSuccessful();

        return count(WelcomeMessage::commonParagraphs()) + count(LevelMessage::all());
    }

    public function test_listening_generates_only_the_greeting_once_the_common_part_is_ready(): void
    {
        self::fakeSpeech();
        $prepared = $this->prepareCommonPart();
        Http::assertSentCount($prepared);

        $user = $this->newcomer();
        $this->listenToEveryPiece($user);

        // Seule la salutation avec le prénom est demandée pendant l'écoute.
        Http::assertSentCount($prepared + 1);
        Http::assertSent(fn (Request $request) => $request['generation_config']['speech_config'][0]['voice'] === config('services.gemini.voice')
            && $request['model'] === config('services.gemini.tts_model')
            && str_contains($request['input'][0]['content'][0]['annotations'][0]['style'], 'welcoming a new learner'));
        $this->assertTrue(PrivateStorage::disk()->exists(WelcomeMessage::path($this->texts($user, 'evening')['opening'], (string) config('services.gemini.voice'), true)));

        // Réécoute : plus aucun appel à Google.
        Http::fake();
        $this->listenToEveryPiece($user);
        Http::assertNothingSent();
    }

    public function test_only_the_opening_is_generated_for_each_new_learner(): void
    {
        self::fakeSpeech();
        $prepared = $this->prepareCommonPart();

        $this->listenToEveryPiece($this->newcomer('Koffi Mensah', '+22890000001'));
        Http::assertSentCount($prepared + 1);

        // Un autre prénom, même formule : seule l'ouverture est nouvelle.
        $this->listenToEveryPiece($this->newcomer('Adjoa Kodjo', '+22890000002'));
        Http::assertSentCount($prepared + 2);
        Http::assertSent(fn (Request $request) => str_contains($request['input'][0]['content'][0]['text'], 'Bonsoir Adjoa ! Good evening, Adjoa !'));

        // Le même prénom que le premier, le même soir : rien de nouveau du tout.
        $this->listenToEveryPiece($this->newcomer('Koffi Agbeko', '+22890000003'));
        Http::assertSentCount($prepared + 2);
    }

    public function test_the_salutation_asked_by_the_page_is_the_one_spoken(): void
    {
        self::fakeSpeech();

        $this->actingAs($this->newcomer())->get(route('welcome.audio', ['index' => 0, 'salut' => 'morning']))->assertOk();

        Http::assertSent(fn (Request $request) => str_contains($request['input'][0]['content'][0]['text'], 'Bonjour Koffi ! Good morning, Koffi !'));
    }

    public function test_outdated_pieces_are_deleted_and_corrected_sentences_prepared_again(): void
    {
        self::fakeSpeech();
        $disk = PrivateStorage::disk();
        $voice = (string) config('services.gemini.voice');

        // Restes de l'ancien message « après l'inscription » et ouverture d'un prénom déjà gardée.
        $disk->put(WelcomeMessage::DIRECTORY.'/'.$voice.'-ancienmessage0000001.wav', 'ancien');
        $disk->put(WelcomeMessage::DIRECTORY.'/Puck-ancienne0voix00002.wav', 'ancien');
        $opening = WelcomeMessage::DIRECTORY.'/'.WelcomeMessage::OPENING_PREFIX.$voice.'-ouverture00000000001.wav';
        $disk->put($opening, 'ouverture');

        $prepared = $this->prepareCommonPart();

        $this->assertFalse($disk->exists(WelcomeMessage::DIRECTORY.'/'.$voice.'-ancienmessage0000001.wav'));
        $this->assertFalse($disk->exists(WelcomeMessage::DIRECTORY.'/Puck-ancienne0voix00002.wav'));
        $this->assertTrue($disk->exists($opening));

        // Une phrase corrigée : seul ce paragraphe est régénéré, son ancienne version disparaît.
        $user = $this->newcomer();
        $old = WelcomeMessage::path($this->texts($user)['paragraphs'][3], $voice);
        config(['teacher.welcome.paragraphs.questionnaire' => 'Première étape : un court questionnaire, corrigé.']);
        $new = WelcomeMessage::path($this->texts($user)['paragraphs'][3], $voice);

        $this->artisan('app:prepare-voice')->assertSuccessful();

        Http::assertSentCount($prepared + 1);
        $this->assertTrue($disk->exists($new));
        $this->assertFalse($disk->exists($old));
        $this->assertTrue($disk->exists(WelcomeMessage::path($this->texts($user)['paragraphs'][0], $voice)));
    }

    public function test_a_paragraph_not_yet_prepared_is_never_generated_while_listening(): void
    {
        Http::fake(['*/interactions' => Http::response('erreur', 500)]);
        $user = $this->newcomer();

        $this->get(route('welcome.audio', ['index' => 0]))->assertRedirect(route('login'));

        $this->actingAs($user);
        $this->get(route('welcome.audio', ['index' => 99]))->assertNotFound();

        // Paragraphe pas encore préparé : aucune demande à Google, la page l'affiche en texte.
        $this->get(route('welcome.audio', ['index' => 1]))->assertStatus(503);
        Http::assertNothingSent();

        // Salutation en échec : une seule demande, puis aucune pendant quelques minutes.
        $this->get(route('welcome.audio', ['index' => 0]))->assertStatus(503);
        $this->get(route('welcome.audio', ['index' => 0]))->assertStatus(503);
        Http::assertSentCount(1);

        // La page reste utilisable : le texte est affiché, chaque morceau dit s'il est prêt.
        $page = $this->get(route('welcome.show', ['reecouter' => 1]))->assertOk()->assertSee('Je suis Teacher Joe, votre professeur d&#039;anglais.', false);
        $pieces = json_decode(html_entity_decode((string) preg_replace('/.*data-pieces=\'([^\']*)\'.*/s', '$1', $page->getContent())), true);
        $this->assertTrue($pieces[0]['ready']);
        $this->assertFalse($pieces[1]['ready']);
    }

    public function test_a_failed_greeting_still_lets_the_ready_common_part_play(): void
    {
        // La préparation réussit, puis Google refuse la salutation (erreur 429).
        $sequence = Http::sequence();

        foreach (range(1, count(WelcomeMessage::commonParagraphs()) + count(LevelMessage::all())) as $ignored) {
            $sequence->push(self::speech());
        }

        Http::fake(['*/interactions' => $sequence->push(['error' => ['code' => 429, 'message' => 'Resource exhausted.', 'status' => 'RESOURCE_EXHAUSTED']], 429)]);
        $this->prepareCommonPart();
        $user = $this->newcomer();
        $this->actingAs($user);

        $this->get(route('welcome.audio', ['index' => 0]))->assertStatus(503);

        // Toute la partie commune reste jouable ; la salutation est affichée en texte.
        for ($index = 1; $index < WelcomeMessage::count($this->texts($user)); $index++) {
            $this->get(route('welcome.audio', ['index' => $index]))->assertOk()->assertHeader('Content-Type', 'audio/wav');
        }

        $this->get(route('welcome.show', ['reecouter' => 1]))->assertSee($this->texts($user)['opening']);
        $this->assertSame('Bienvenue, salutation', SpeechLog::recent()[0]['label']);
        $this->assertSame(429, SpeechLog::recent()[0]['status']);
    }

    public function test_the_welcome_message_uses_neither_the_assessment_nor_the_oral_minutes(): void
    {
        self::fakeSpeech();
        $user = $this->newcomer();
        $remaining = $user->remainingSecondsToday();

        $this->actingAs($user)->get(route('welcome.show'))->assertOk();
        $this->get(route('welcome.audio', ['index' => 0]))->assertOk();

        $this->assertSame(0, $user->assessment()->count());
        $this->assertSame(0, $user->lessons()->count());
        $this->assertSame(0, $user->courseSessions()->count());
        $this->assertSame($remaining, $user->fresh()->remainingSecondsToday());
    }

    public function test_the_page_is_light_and_loads_nothing_before_the_touch(): void
    {
        $html = $this->actingAs($this->newcomer())->get(route('welcome.show'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringNotContainsString('<audio', $html);
        $this->assertStringContainsString('js/welcome.js', $html);
        // Les morceaux sont des adresses, demandées seulement après le toucher.
        $this->assertStringContainsString('data-pieces', $html);
        $this->assertLessThan(60 * 1024, strlen($html));
    }
}
