<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Support\Gemini\WelcomeMessage;
use App\Support\Speech\SpeechLoudness;
use App\Support\Teachers\Teachers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Page de bienvenue rapide et chaleureuse : messages des deux professeurs préparés d'avance en peu de demandes
 * (plusieurs paragraphes par demande, recoupés au silence), à −14 LUFS ; la page ne fait jamais attendre une
 * synthèse (« pret=1 »), précharge une version légère, fait préparer la salutation au prénom après sa réponse ;
 * félicitations au prénom, fête légère, cartes des professeurs dont le nom ne disparaît jamais. Aucun appel réel.
 */
class WelcomeFastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['filesystems.private_disk' => 'local', 'services.gemini.key' => 'cle-de-test', 'app.timezone' => 'Africa/Lome']);
    }

    /**
     * Faux Gemini qui dit vraiment chaque paragraphe : une « phrase » (son de 1,2 seconde) par paragraphe de la
     * demande, séparées par une demi-seconde de silence, comme une vraie voix entre deux paragraphes.
     */
    private static function fakeSpokenSpeech(): void
    {
        Http::fake(['*/interactions' => function (Request $request) {
            $text = (string) ($request['input'][0]['content'][0]['text'] ?? '');
            $parts = count(preg_split('/\n\n/', trim($text)));
            $pcm = '';

            for ($part = 0; $part < $parts; $part++) {
                if ($part > 0) {
                    $pcm .= str_repeat("\0\0", 12000);
                }

                for ($i = 0; $i < 28800; $i++) {
                    $pcm .= pack('v', (int) round(4000 * sin(2 * M_PI * 220 * $i / 24000)) & 0xFFFF);
                }
            }

            return Http::response(['steps' => [[
                'type' => 'model_output',
                'content' => [['type' => 'audio', 'mime_type' => 'audio/l16;rate=24000', 'data' => base64_encode($pcm)]],
            ]]]);
        }]);
    }

    private function newcomer(string $name = 'Afi Mensah', ?string $teacher = null): User
    {
        $user = User::factory()->unwelcomed()->create(['name' => $name]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();

        if ($teacher !== null) {
            $user->forceFill(['teacher' => $teacher])->save();
        }

        return $user->fresh();
    }

    private function pieces(string $html): array
    {
        return self::currentPieces($html);
    }

    /** Morceaux du message du professeur affiché (data-teachers, data-current). */
    public static function currentPieces(string $html): array
    {
        return self::teachers($html)[preg_replace('/.*data-current="([^"]*)".*/s', '$1', $html)]['pieces'];
    }

    /** Ce que la page sait de chaque professeur proposé (data-teachers). */
    public static function teachers(string $html): array
    {
        return json_decode(html_entity_decode((string) preg_replace('/.*data-teachers=\'([^\']*)\'.*/s', '$1', $html)), true);
    }

    public function test_both_teachers_are_prepared_in_few_requests_at_the_loudness_of_the_videos(): void
    {
        config(['platform.speech.group_characters' => 1200]);
        self::fakeSpokenSpeech();
        Sleep::fake(syncWithCarbon: true);

        $this->artisan('app:prepare-voice', ['--budget' => 4000])->assertSuccessful();

        $paragraphs = count(WelcomeMessage::commonParagraphs(Teachers::default())) + count(WelcomeMessage::commonParagraphs(Teachers::find('rose')));
        $welcomeRequests = collect(Http::recorded())->filter(fn ($pair) => str_contains((string) json_encode($pair[0]->data()), 'Bienvenue')
            || str_contains((string) ($pair[0]['input'][0]['content'][0]['text'] ?? ''), 'Félicitations pour votre formule'))->count();

        // Tous les messages de bienvenue, des deux professeurs et de toutes les formules, sont prêts.
        foreach (WelcomeMessage::messages() as $message) {
            $this->assertNotNull($message->provider(), $message->label);
            $this->assertTrue($message->isCompleteAt($message->provider()), $message->label);
        }

        // Bien moins d'une demande par paragraphe.
        $this->assertGreaterThan(10, $paragraphs);
        $this->assertLessThan($paragraphs / 2, $welcomeRequests, "{$welcomeRequests} demandes pour {$paragraphs} paragraphes");

        // Chaque paragraphe recoupé est un vrai son à −14 LUFS (comme les vidéos).
        $text = WelcomeMessage::commonParagraphs(Teachers::default())[0]['text'];
        $message = WelcomeMessage::messages()[0];
        $wav = Storage::disk('local')->get($message->path($text, $message->provider()));
        $this->assertEqualsWithDelta(-14.0, SpeechLoudness::lufs($wav), 0.6);
        $this->assertEqualsWithDelta(1.2, (strlen($wav) - 44) / 48000, 0.35);
    }

    public function test_the_page_never_waits_for_a_synthesis_and_prepares_the_greeting_after_its_answer(): void
    {
        self::fakeSpokenSpeech();
        $user = $this->newcomer();

        // Page : la salutation n'est pas encore gardée, elle est « bientôt prête » et préparée après la réponse.
        $page = $this->actingAs($user)->get(route('welcome.show'))->assertOk();
        $pieces = $this->pieces($page->getContent());
        $this->assertFalse($pieces[0]['ready']);
        $this->assertTrue($pieces[0]['soon']);
        $this->assertStringContainsString('pret=1', $pieces[0]['url']);
        Http::assertSent(fn (Request $request) => str_contains((string) ($request['input'][0]['content'][0]['text'] ?? ''), 'Afi'));

        // Ensuite elle est là, en version complète et en version légère (µ-law, deux fois plus petite).
        $full = $this->get($pieces[0]['url'])->assertOk()->assertHeader('Content-Type', 'audio/wav')->getContent();
        $light = $this->get($pieces[0]['url'].'&leger=1')->assertOk()->getContent();
        $this->assertSame(7, unpack('v', substr($light, 20, 2))[1]);
        $this->assertEqualsWithDelta(strlen($full) / 2, strlen($light), 64);

        // « pret=1 » : un morceau pas prêt n'est jamais demandé au fournisseur pendant l'écoute.
        Http::fake();
        $other = $this->newcomer('Kossi Agbo');
        $this->actingAs($other)->get(route('welcome.audio', ['index' => 0, 'salut' => 'evening', 'pret' => 1]))->assertStatus(503);
        $this->get(route('welcome.audio', ['index' => 1, 'pret' => 1]))->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_once_the_greeting_is_kept_the_page_says_it_is_ready(): void
    {
        self::fakeSpokenSpeech();
        $user = $this->newcomer();
        $this->actingAs($user)->get(route('welcome.show'))->assertOk();

        $pieces = $this->pieces($this->get(route('welcome.show', ['reecouter' => 1]))->getContent());
        $this->assertTrue($pieces[0]['ready']);
        $this->assertFalse($pieces[0]['soon']);
    }

    public function test_congratulations_with_the_first_name_and_a_light_celebration(): void
    {
        self::fakeSpokenSpeech();
        $page = $this->actingAs($this->newcomer('Afi Mensah'))->get(route('welcome.show'))->assertOk();

        $page->assertSee('Félicitations, Afi !')
            ->assertSee('🎉', false)
            ->assertSee('Votre formule Essentiel est activée')
            ->assertSee('welcome-confetti', false);

        // Réécoute : pas de fête.
        $this->get(route('welcome.show', ['reecouter' => 1]))->assertOk()->assertDontSee('welcome-confetti', false);

        // Fête en CSS seulement, coupée avec « réduire les animations ».
        $css = (string) file_get_contents(public_path('css/app.css'));
        $this->assertMatchesRegularExpression('/prefers-reduced-motion: reduce\) \{\s*\.welcome-confetti \{ display: none; \}/', $css);
    }

    public function test_teacher_cards_show_only_photo_and_name_with_the_choice_in_green(): void
    {
        self::fakeSpokenSpeech();
        $page = $this->actingAs($this->newcomer())->get(route('welcome.show'))->assertOk();
        $html = $page->getContent();

        $page->assertSee('Avec qui voulez-vous apprendre ?')
            ->assertDontSee('Avec qui voulez-vous travailler')
            ->assertSee('class="welcome-card is-current"', false)
            ->assertSee('data-card="rose"', false)
            ->assertSee('Votre choix')
            ->assertSee('images/teacher-rose.webp', false)
            ->assertSee('images/teacher-joe.webp', false);

        // Plus de bouton « Sa voix » sur les cartes.
        $this->assertStringNotContainsString('Sa voix', $html);
        $this->assertStringNotContainsString('data-voice-sample', $html);

        // Le professeur choisi est encadré de vert (seul autre vert que WhatsApp).
        $css = (string) file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('.welcome-card.is-current { border-color: var(--c-choice);', $css);
        $this->assertStringContainsString('--c-choice: #34C77B;', (string) file_get_contents(public_path('css/tokens.css')));
    }

    public function test_the_page_knows_both_teachers_to_switch_instantly(): void
    {
        self::fakeSpokenSpeech();
        $user = $this->newcomer();
        $html = $this->actingAs($user)->get(route('welcome.show'))->assertOk()->getContent();
        $teachers = self::teachers($html);

        $this->assertSame(['joe', 'rose'], array_keys($teachers));
        $this->assertSame('Toucher pour écouter Teacher Rose', $teachers['rose']['play']);
        $this->assertSame('Un instant, Teacher Rose arrive…', $teachers['rose']['arriving']);
        $this->assertSame('Écoutez bien : elle vous explique comment tout fonctionne.', $teachers['rose']['hint']);
        $this->assertStringContainsString('Je suis Teacher Rose', $teachers['rose']['pieces'][2]['text']);
        // Chaque morceau dit de quel professeur il est : la voix de Teacher Rose est préchargée avant qu'elle soit choisie.
        $this->assertStringContainsString('professeur=rose', $teachers['rose']['pieces'][1]['url']);
        $this->assertNotNull($teachers['rose']['teacher3d']);

        // Sa salutation au prénom est préparée aussi, après la réponse de la page.
        Http::assertSent(fn (Request $request) => str_contains((string) json_encode($request->data()), 'female teacher')
            && str_contains((string) ($request['input'][0]['content'][0]['text'] ?? ''), 'Afi'));

        // Le morceau de Teacher Rose est bien servi avec SA voix, même si l'élève n'a pas encore choisi.
        $this->get($teachers['rose']['pieces'][0]['url'])->assertOk()->assertHeader('Content-Type', 'audio/wav');

        // Le choix est enregistré en arrière-plan (réponse JSON, aucun rechargement).
        $this->postJson(route('teacher.choose'), ['teacher' => 'rose', 'retour' => '/bienvenue'])
            ->assertOk()->assertJson(['teacher' => 'rose', 'name' => 'Teacher Rose']);
        $this->assertSame('rose', $user->fresh()->teacher);

        $js = (string) file_get_contents(public_path('js/welcome.js'));
        $this->assertStringContainsString("card.addEventListener('submit'", $js);
        $this->assertStringContainsString('event.preventDefault();', $js);
    }

    public function test_every_teacher_choice_says_the_teacher_arrives_never_sending(): void
    {
        self::fakeSpokenSpeech();
        $user = $this->newcomer();
        $user->forceFill(['welcomed_at' => now()])->save();

        // « Mon espace » (carte « Votre professeur », où mène aussi « Changer de professeur ») et l'évaluation.
        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('data-busy-label="Teacher Rose arrive…"', false);
        $this->get(route('assessment.show'))->assertOk()
            ->assertSee('data-busy-label="Teacher Rose arrive…">Préférer Teacher Rose', false);

        // Annonce « Teacher Rose est arrivée ! » : même message d'attente.
        $news = (string) file_get_contents(resource_path('views/components/teacher-news.blade.php'));
        $this->assertStringContainsString('data-busy-label="{{ $teacher->name() }} arrive…"', $news);

        // Aucun choix de professeur ne garde le texte générique.
        foreach (['teacher-choice', 'teacher-news', 'assessment-teacher'] as $component) {
            $this->assertStringNotContainsString('Envoi en cours', (string) file_get_contents(resource_path("views/components/{$component}.blade.php")));
        }
    }

    public function test_the_page_preloads_light_audio_and_plays_without_waiting(): void
    {
        $js = (string) file_get_contents(public_path('js/welcome.js'));

        // Préchargement en version légère dès l'ouverture, version complète si le navigateur ne la lit pas.
        $this->assertStringContainsString("light.searchParams.set('leger', '1')", $js);
        $this->assertStringContainsString('await preload(currentKey, PRELOAD);', $js);
        $this->assertStringContainsString('await preload(key, PRELOAD_OTHER);', $js);
        // Au toucher : la salutation encore en préparation est sautée, le reste part tout de suite.
        $this->assertStringContainsString('if (!slotsFor(key)[0].buffer && pieces[0].soon)', $js);
        // Volume de la page : les fichiers sont déjà à −14 LUFS.
        $this->assertSame(1.0, (float) config('platform.speech.page_gain'));
        $this->assertSame(-14.0, (float) config('platform.speech.target_lufs'));
    }

    public function test_the_welcome_tone_is_joyful_for_both_teachers(): void
    {
        $joe = WelcomeMessage::style(Teachers::default());
        $rose = WelcomeMessage::style(Teachers::find('rose'));

        foreach ([$joe, $rose] as $style) {
            $this->assertStringContainsString('enthusiastic, joyful and warm', $style);
            $this->assertStringContainsString('never fast', $style);
        }

        $this->assertStringContainsString('female teacher', $rose);
    }
}
