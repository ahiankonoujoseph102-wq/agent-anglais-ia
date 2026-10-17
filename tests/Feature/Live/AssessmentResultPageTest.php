<?php

namespace Tests\Feature\Live;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\User;
use App\Support\Gemini\LevelMessage;
use App\Support\PrivateStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * Page de résultat de l'évaluation : niveau, message vocal de Teacher Joe (à la demande),
 * formules « Je commence maintenant », choix d'un autre niveau. Aucun appel réel à Gemini.
 */
class AssessmentResultPageTest extends TestCase
{
    use BuildsPrograms;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['filesystems.private_disk' => 'local', 'services.gemini.key' => 'cle-de-test']);
    }

    private function assessed(?Level $level = Level::A2): User
    {
        $user = User::factory()->create();
        $user->forceFill(['level' => $level])->save();
        $user->assessment()->create([
            'status' => $level ? AssessmentStatus::Completed : AssessmentStatus::Abandoned,
            'started_at' => now()->subHour(),
            'ended_at' => now()->subHour()->addMinutes(5),
            'allowed_seconds' => 300,
            'used_seconds' => 300,
            'level' => $level,
            'feedback' => 'Bonne base, quelques hésitations.',
        ]);

        return $user;
    }

    private static function fakeSpeech(): void
    {
        Http::fake(['*/interactions' => Http::response(['steps' => [[
            'type' => 'model_output',
            'content' => [['type' => 'audio', 'data' => base64_encode('RIFF-son-de-test')]],
        ]]])]);
    }

    public function test_returning_learner_sees_a_positive_result_page_with_plans(): void
    {
        Http::fake();

        $this->actingAs($this->assessed())->get('/evaluation')
            ->assertOk()
            ->assertSee('Votre niveau : <span class="result-level">A2</span>', false)
            ->assertSee('Élémentaire')
            ->assertSee('Bonne base, quelques hésitations.')
            ->assertSee('Écouter Teacher Joe')
            ->assertSee('Je commence maintenant')
            ->assertSee(route('payment.create', 'essentiel'))
            ->assertSee('Choisir un autre niveau')
            ->assertSee('Recommandé')
            ->assertDontSee('alert-error', false);

        // Rien n'est demandé à Google à l'affichage : le message n'est généré qu'au toucher.
        Http::assertNothingSent();
    }

    public function test_voice_message_is_generated_once_with_the_chosen_voice_then_cached(): void
    {
        self::fakeSpeech();
        $user = $this->assessed(Level::B1);

        $this->actingAs($user)->get('/evaluation/message-vocal')
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/wav');
        $this->actingAs($user)->get('/evaluation/message-vocal')->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['generation_config']['speech_config'][0]['voice'] === config('services.gemini.voice')
            && str_contains($request['input'][0]['content'][0]['text'], 'Votre niveau est B1')
            && $request['store'] === false);
        $this->assertTrue(PrivateStorage::disk()->exists(LevelMessage::path(Level::B1, config('services.gemini.voice'))));
    }

    public function test_page_still_works_when_the_voice_message_fails(): void
    {
        Http::fake(['*/interactions' => Http::response(['error' => 'panne'], 500)]);
        $user = $this->assessed();

        $this->actingAs($user)->get('/evaluation/message-vocal')->assertStatus(503);
        // Pas de nouvel essai juste après un échec.
        $this->actingAs($user)->get('/evaluation/message-vocal')->assertStatus(503);
        Http::assertSentCount(1);

        $this->actingAs($user)->get('/evaluation')->assertOk()->assertSee('Lire le message de Teacher Joe');
    }

    public function test_no_voice_message_without_a_level(): void
    {
        Http::fake();
        $user = $this->assessed(null);

        $this->actingAs($user)->get('/evaluation')->assertOk()
            ->assertSee('Nous n\'avons pas pu mesurer votre niveau', false)
            ->assertDontSee('Écouter Teacher Joe')
            ->assertSee('Choisir un autre niveau');
        $this->actingAs($user)->get('/evaluation/message-vocal')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_learner_can_choose_another_level_used_for_the_programme(): void
    {
        $user = $this->assessed(Level::A2);

        $this->actingAs($user)->put('/evaluation/niveau', ['level' => 'B1'])
            ->assertRedirect('/evaluation')
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'Niveau choisi : B1') && str_contains($status, 'plus élevé'));

        $this->assertSame(Level::B1, $user->fresh()->level);
        // Le niveau trouvé reste celui de l'évaluation (toujours « Recommandé »).
        $this->assertSame(Level::A2, $user->assessment()->first()->level);

        // Le programme est construit au niveau choisi.
        $this->assertSame(Level::B1, $this->buildProgram($user->fresh())->level);
    }

    public function test_higher_levels_carry_the_warning_marker(): void
    {
        $html = $this->actingAs($this->assessed(Level::B1))->get('/evaluation')->getContent();

        $this->assertMatchesRegularExpression('/value="B2"\s+data-higher/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="A2"\s+data-higher/', $html);
        $this->assertStringContainsString('ce niveau est plus élevé que celui de votre évaluation', $html);
    }

    public function test_level_cannot_change_once_the_programme_exists(): void
    {
        $user = $this->assessed(Level::A2);
        $this->buildProgram($user);

        $this->actingAs($user)->get('/evaluation')->assertDontSee('Choisir un autre niveau');
        $this->actingAs($user)->put('/evaluation/niveau', ['level' => 'C1'])->assertRedirect('/evaluation');
        $this->assertSame(Level::A2, $user->fresh()->level);
    }

    public function test_invalid_level_is_refused(): void
    {
        $user = $this->assessed();

        $this->actingAs($user)->from('/evaluation')->put('/evaluation/niveau', ['level' => 'D4'])->assertSessionHasErrors('level');
        $this->assertSame(Level::A2, $user->fresh()->level);
    }

    public function test_every_level_has_a_french_voice_message(): void
    {
        foreach (Level::cases() as $level) {
            $text = LevelMessage::text($level);
            $this->assertStringContainsString("Votre niveau est {$level->value}", $text);
            $this->assertDoesNotMatchRegularExpression('/certificat|diplôme|garanti/iu', $text);
        }
    }
}
