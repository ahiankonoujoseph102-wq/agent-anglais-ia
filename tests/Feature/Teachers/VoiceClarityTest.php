<?php

namespace Tests\Feature\Teachers;

use App\Models\Subscription;
use App\Models\User;
use App\Support\Gemini\VoiceSampler;
use App\Support\Gemini\WelcomeMessage;
use App\Support\Speech\VoiceClarity;
use App\Support\Teachers\Teachers;
use App\Support\Videos\VideoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Clarté des voix de « Essai des voix » : chaque extrait du test en français et en anglais est retranscrit (Gemini,
 * sinon OpenAI) puis comparé mot à mot à la phrase ; la voix la plus claire en français est signalée. Nombres des
 * messages de Teacher Rose dits en lettres ; Teacher Joe inchangé. Aucun appel réel.
 */
class VoiceClarityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'filesystems.private_disk' => 'local',
            'services.gemini.key' => 'cle-principale',
            'services.gemini.key_2' => 'cle-deuxieme',
            'services.openai.key' => 'cle-openai',
        ]);
        VideoSettings::saveSite('talkami.net');
    }

    public function test_the_score_counts_well_heard_words_in_french_and_in_english(): void
    {
        $expected = 'Il y a trois formules. Votre accès a expiré. Maintenant, en anglais : How much is it?';

        $this->assertSame(['french' => 100, 'english' => 100, 'total' => 100], VoiceClarity::score($expected, $expected));

        // « expiré » déformé, « trois » avalé : deux mots français sur douze ; l'anglais reste juste.
        $heard = 'Il y a formules. Votre accès a expirer. Maintenant en anglais. How much is it?';
        $score = VoiceClarity::score($expected, $heard);

        $this->assertSame(83, $score['french']);
        $this->assertSame(100, $score['english']);
    }

    public function test_measuring_transcribes_each_ready_sample_and_shows_the_clearest_voice(): void
    {
        $rose = Teachers::find('rose');
        $text = VoiceSampler::bilingualScript($rose)['text'];
        $disk = Storage::disk('local');
        $disk->put(VoiceSampler::bilingualPath('gemini', 'Kore', $rose), 'RIFF-kore');
        $disk->put(VoiceSampler::bilingualPath('openai', 'marin', $rose), 'RIFF-marin');

        // Gemini entend Kore avec des mots déformés ; il ne répond pas pour marin : OpenAI prend le relais.
        Http::fake(function (Request $request) use ($text) {
            if (str_contains($request->url(), '/interactions')) {
                $audio = base64_decode((string) collect($request['input'])->firstWhere('type', 'audio')['data']);

                return $audio === 'RIFF-kore'
                    ? Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => str_replace(['trois', 'expiré'], ['troie', 'expirer'], $text)]]]]])
                    : Http::response(['error' => ['message' => 'indisponible']], 503);
            }

            return Http::response(['text' => $text]);
        });

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.voices.speech.measure'), ['teacher' => 'rose'])
            ->assertRedirect(route('admin.voices.index', ['professeur' => 'rose']).'#voix-francais')
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'Clarté mesurée : 2 extraits.'));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/audio/transcriptions'));

        $kore = VoiceClarity::result('gemini', 'Kore', $rose);
        $marin = VoiceClarity::result('openai', 'marin', $rose);
        $this->assertLessThan(100, $kore['french']);
        $this->assertSame(100, $marin['french']);
        $this->assertSame('openai', $marin['by']);

        $this->actingAs($admin)->get(route('admin.voices.index', ['professeur' => 'rose']))->assertOk()
            ->assertSee('Voix la plus claire mesurée en français : <strong>marin</strong> (OpenAI)', false)
            ->assertSee('Clarté : français '.$kore['french'].' %');

        // Déjà mesurés : aucune nouvelle demande.
        Http::fake();
        $this->actingAs($admin)->post(route('admin.voices.speech.measure'), ['teacher' => 'rose']);
        Http::assertNothingSent();
    }

    public function test_only_the_main_administrator_can_measure(): void
    {
        $this->actingAs(User::factory()->create())->post(route('admin.voices.speech.measure'), ['teacher' => 'rose'])->assertForbidden();
    }

    public function test_rose_says_numbers_in_words_in_her_messages_and_joe_keeps_his_texts(): void
    {
        $user = User::factory()->create(['name' => 'Afi Mensah']);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();

        $rose = implode(' ', WelcomeMessage::texts($user, null, Teachers::find('rose'))['paragraphs']);
        $joe = implode(' ', WelcomeMessage::texts($user, null, Teachers::default())['paragraphs']);

        $this->assertDoesNotMatchRegularExpression('/\d/', $rose);
        $this->assertStringContainsString('quinze minutes', $rose);
        $this->assertStringContainsString('15 minutes', $joe);
    }

    public function test_her_presentation_tone_is_feminine_even_for_the_evening_video(): void
    {
        $rose = Teachers::find('rose');

        $this->assertStringContainsString('comme une professeure très chaleureuse et enthousiaste', $rose->speechStyle('Lis en français comme un professeur très chaleureux et enthousiaste qui invite ses amis'));
        $this->assertSame('Lis en français comme un professeur très chaleureux et enthousiaste', Teachers::default()->speechStyle('Lis en français comme un professeur très chaleureux et enthousiaste'));
    }
}
