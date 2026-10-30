<?php

namespace Tests\Feature\Teachers;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\IgnoresSignalSilence;
use Tests\TestCase;

/**
 * Évaluation gratuite avec le choix du professeur (étape 5 de « Teacher Rose partout ») : Teacher Joe choisi d'avance,
 * « Préférer Teacher Rose » recharge la page avec elle ; le professeur est fixé quand l'évaluation commence et ne
 * change jamais en route. Rien d'autre ne change dans l'évaluation ; Teacher Joe y reste identique (garde-fou
 * TeacherJoeUnchangedTest).
 */
class TeacherRoseAssessmentTest extends TestCase
{
    use IgnoresSignalSilence, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'cle-secrete-du-serveur', 'services.gemini.voice' => 'Achird']);
        Http::fake(['*/auth_tokens' => Http::response(['name' => 'auth_tokens/jeton123'])]);
        $this->freezeSecond();
    }

    public function test_teacher_joe_is_chosen_by_default_and_teacher_rose_is_offered(): void
    {
        $this->actingAs(User::factory()->create())->get('/evaluation')->assertOk()
            ->assertSee('Avec <strong>Teacher Joe</strong>', false)
            ->assertSee('href="'.route('assessment.show', ['professeur' => 'rose']).'" data-busy-label="Teacher Rose arrive…">Préférer Teacher Rose</a>', false)
            ->assertSee('5 minutes à l\'oral avec Teacher Joe, pour trouver votre niveau.')
            ->assertSee('data-teacher-name="Teacher Joe"', false)
            ->assertSee('"connect":"'.str_replace('/', '\/', route('assessment.connect')).'"', false);
    }

    public function test_choosing_teacher_rose_shows_her_and_connects_with_her_voice(): void
    {
        $user = User::factory()->create(['name' => 'Afi']);

        $this->actingAs($user)->get('/evaluation?professeur=rose')->assertOk()
            ->assertSee('Avec <strong>Teacher Rose</strong>', false)
            ->assertSee('Préférer Teacher Joe')
            ->assertSee('5 minutes à l\'oral avec Teacher Rose, pour trouver votre niveau.')
            ->assertSee('data-teacher-name="Teacher Rose"', false)
            ->assertSee('teacher-rose.glb', false)
            ->assertSee('connexion?professeur=rose', false);

        $setup = $this->actingAs($user)->postJson('/evaluation/connexion?professeur=rose')->assertOk()->json('setup');

        $this->assertSame('Sulafat', $setup['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName']);
        $this->assertStringContainsString('Teacher Rose', $setup['systemInstruction']['parts'][0]['text']);
        $this->assertSame('rose', $user->assessment()->first()->getAttributes()['teacher']);

        // Reconnexion (même sans le paramètre) : toujours avec elle ; la page n'offre plus de choix.
        $again = $this->actingAs($user)->postJson('/evaluation/connexion', ['handle' => 'reprise-abc'])->assertOk()->json('setup');
        $this->assertSame('Sulafat', $again['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName']);
        $this->actingAs($user)->get('/evaluation')->assertOk()->assertDontSee('Préférer Teacher Joe')->assertSee('data-teacher-name="Teacher Rose"', false);
    }

    public function test_without_choice_the_assessment_is_with_teacher_joe_as_before(): void
    {
        $user = User::factory()->create();

        $setup = $this->actingAs($user)->postJson('/evaluation/connexion')->assertOk()->json('setup');
        $this->assertSame('Achird', $setup['generationConfig']['speechConfig']['voiceConfig']['prebuiltVoiceConfig']['voiceName']);
        $this->assertSame('joe', $user->assessment()->first()->getAttributes()['teacher']);

        // Professeur inconnu : Teacher Joe.
        $other = User::factory()->create();
        $this->actingAs($other)->postJson('/evaluation/connexion?professeur=inconnu')->assertOk();
        $this->assertSame('joe', $other->assessment()->first()->getAttributes()['teacher']);
    }

    public function test_back_to_teacher_joe_alone_hides_the_choice_and_refuses_teacher_rose(): void
    {
        config(['teachers.list.rose.ready' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/evaluation?professeur=rose')->assertOk()
            ->assertDontSee('Préférer')
            ->assertSee('data-teacher-name="Teacher Joe"', false);

        $this->actingAs($user)->postJson('/evaluation/connexion?professeur=rose')->assertOk();
        $this->assertSame('joe', $user->assessment()->first()->getAttributes()['teacher']);
    }

    public function test_the_result_page_shows_the_teacher_of_the_assessment(): void
    {
        $user = User::factory()->create();
        $assessment = $user->assessment()->create(['status' => AssessmentStatus::Completed, 'started_at' => now()->subMinutes(10), 'ended_at' => now(), 'allowed_seconds' => 300, 'used_seconds' => 300, 'level' => Level::A2, 'feedback' => 'Vous vous exprimez avec clarté.']);
        $assessment->forceFill(['teacher' => 'rose'])->save();
        // Message vocal du résultat préparé après la réponse : aucune vraie demande.
        Http::fake(['*/interactions' => Http::response(['steps' => []])]);

        $this->actingAs($user)->get('/evaluation')->assertOk()
            ->assertSee('images/teacher-rose.webp', false)
            ->assertSee('Teacher Rose, votre professeure d\'anglais', false)
            ->assertSee('L\'avis de Teacher Rose', false)
            ->assertSee('commencer avec Teacher Rose.')
            ->assertSee('elle vous fait parler');
    }
}
