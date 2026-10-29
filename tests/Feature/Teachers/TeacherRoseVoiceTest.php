<?php

namespace Tests\Feature\Teachers;

use App\Enums\Level;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Gemini\LevelMessage;
use App\Support\Gemini\WelcomeMessage;
use App\Support\PrivateStorage;
use App\Support\Speech\GeminiSpeech;
use App\Support\Speech\OpenAiSpeech;
use App\Support\Teachers\Teachers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Teacher Rose dans les messages vocaux et la page de bienvenue : « Avec qui voulez-vous travailler ? » après
 * l'ouverture d'un accès (jamais sur la page de paiement), messages de bienvenue et du résultat préparés aussi avec
 * sa voix (Sulafat chez Gemini, marin chez OpenAI) et ses accords, fichiers de Teacher Joe inchangés. Aucun appel réel.
 */
class TeacherRoseVoiceTest extends TestCase
{
    use RefreshDatabase;

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

    private function newcomer(?string $teacher = null): User
    {
        $user = User::factory()->unwelcomed()->create(['name' => 'Afi Mensah']);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();

        if ($teacher !== null) {
            $user->forceFill(['teacher' => $teacher])->save();
        }

        return $user->fresh();
    }

    private function prepareEverything(): void
    {
        Sleep::fake(syncWithCarbon: true);
        $this->artisan('app:prepare-voice', ['--budget' => 2000])->assertSuccessful();
    }

    public function test_teacher_joe_keeps_exactly_the_same_files(): void
    {
        $gemini = app(GeminiSpeech::class);
        $text = 'Bonjour';

        // Formule d'avant : modèle, voix, texte, ton de la configuration, réglages.
        $old = 'welcome-messages/gemini-'.$gemini->voice().'-'.substr(sha1(implode('|', array_filter([
            $gemini->model(), $gemini->voice(), $text, config('teacher.welcome.style'), $gemini->settings(),
        ], fn ($part) => $part !== ''))), 0, 20).'.wav';

        $this->assertSame($old, WelcomeMessage::path($text, $gemini));
        $this->assertSame($old, WelcomeMessage::path($text, $gemini->forTeacher(Teachers::default())));
        $this->assertSame(LevelMessage::path(Level::A2, $gemini), LevelMessage::path(Level::A2, $gemini->forTeacher(Teachers::default())));
        $this->assertSame('welcome:essentiel', WelcomeMessage::messagesFor(Teachers::default())[0]->key);
    }

    public function test_rose_speaks_with_her_voice_her_tone_and_feminine_agreements(): void
    {
        $rose = Teachers::find('rose');

        $this->assertSame('Sulafat', app(GeminiSpeech::class)->forTeacher($rose)->voice());
        $this->assertSame('marin', app(OpenAiSpeech::class)->forTeacher($rose)->voice());
        $this->assertSame(config('services.openai.tts_voice'), app(OpenAiSpeech::class)->voice());

        $texts = WelcomeMessage::texts($this->newcomer('rose'));
        $this->assertSame('rose', $texts['teacher']->key);
        $this->assertStringContainsString('Je suis Teacher Rose, votre professeure', $texts['introduction']);
        $this->assertStringContainsString('je suis heureuse', $texts['introduction']);
        $this->assertStringContainsString('female teacher', WelcomeMessage::style($rose));
        $this->assertStringNotContainsString('male teacher', str_replace('female teacher', '', WelcomeMessage::style($rose)));
        $this->assertStringContainsString('female teacher', LevelMessage::style($rose));

        // Teacher Joe : textes et ton d'avant.
        $joe = WelcomeMessage::texts($this->newcomer());
        $this->assertStringContainsString('Je suis Teacher Joe, votre professeur d\'anglais', $joe['introduction']);
        $this->assertSame(config('teacher.welcome.style'), WelcomeMessage::style(Teachers::default()));
    }

    public function test_the_preparation_makes_every_message_for_both_teachers(): void
    {
        self::fakeSpeech();
        $this->prepareEverything();

        Http::assertSent(fn (Request $request) => ($request['generation_config']['speech_config'][0]['voice'] ?? null) === 'Sulafat'
            && str_contains($request['input'][0]['content'][0]['annotations'][0]['style'], 'female teacher'));
        Http::assertSent(fn (Request $request) => ($request['generation_config']['speech_config'][0]['voice'] ?? null) === config('services.gemini.voice'));

        $rose = Teachers::find('rose');
        $files = PrivateStorage::disk();

        foreach ([...WelcomeMessage::messagesFor($rose), LevelMessage::message(Level::B1, $rose)] as $message) {
            $this->assertTrue($message->isCompleteAt(app(GeminiSpeech::class)), $message->label);
        }

        $this->assertTrue($files->exists(LevelMessage::path(Level::B1, app(GeminiSpeech::class)->forTeacher($rose))));
        $this->assertTrue($files->exists(LevelMessage::path(Level::B1, app(GeminiSpeech::class))));

        // Rien n'est effacé ensuite : les fichiers des deux professeurs restent valables.
        $this->assertSame(0, WelcomeMessage::purgeOutdated());
    }

    public function test_the_welcome_page_asks_who_to_work_with_and_plays_each_voice(): void
    {
        self::fakeSpeech();
        $this->prepareEverything();
        $user = $this->newcomer();

        $this->actingAs($user)->get('/bienvenue')->assertOk()
            ->assertSee('Avec qui voulez-vous travailler ?')
            ->assertSee('data-voice-sample="'.route('welcome.voice', 'joe').'"', false)
            ->assertSee('data-voice-sample="'.route('welcome.voice', 'rose').'"', false)
            ->assertSee('Toucher pour écouter Teacher Joe')
            ->assertSee('name="retour" value="/bienvenue"', false);

        Http::fake();
        $this->actingAs($user)->get(route('welcome.voice', 'rose'))->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $this->actingAs($user)->get(route('welcome.voice', 'personne'))->assertNotFound();
        Http::assertNothingSent();

        // Choisir Teacher Rose : la page se réécoute avec elle (sa voix, son visage, ses mots).
        $this->actingAs($user)->post('/espace/professeur', ['teacher' => 'rose', 'retour' => '/bienvenue'])
            ->assertRedirect(url('/bienvenue?reecouter=1'));

        $page = $this->actingAs($user->fresh())->get('/bienvenue?reecouter=1')->assertOk();
        $page->assertSee('Toucher pour écouter Teacher Rose')
            ->assertSee('elle vous explique comment tout fonctionne')
            ->assertSee('Je suis Teacher Rose, votre professeure d\'anglais')
            ->assertSee('face-rose', false)
            ->assertSee('id="welcome-mouth"', false);
    }

    public function test_the_greeting_with_the_first_name_is_said_by_the_chosen_teacher(): void
    {
        self::fakeSpeech();
        $this->prepareEverything();
        $user = $this->newcomer('rose');

        $this->actingAs($user)->get(route('welcome.audio', ['index' => 0, 'salut' => 'evening']))->assertOk();

        Http::assertSent(fn (Request $request) => ($request['generation_config']['speech_config'][0]['voice'] ?? null) === 'Sulafat'
            && str_contains(json_encode($request['input']), 'Afi'));
    }

    public function test_no_choice_without_a_second_teacher_and_never_on_the_payment_page(): void
    {
        $user = $this->newcomer();

        $this->actingAs($user)->get(route('payment.create', 'essentiel'))->assertDontSee('Avec qui voulez-vous travailler');

        config(['teachers.list.rose.ready' => false]);
        $this->actingAs($user)->get('/bienvenue')->assertOk()->assertDontSee('Avec qui voulez-vous travailler');
    }
}
