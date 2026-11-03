<?php

namespace Tests\Feature\Program;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\Lesson;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Curriculum\LessonBoards;
use App\Support\Practice\PracticeAudio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsPrograms;
use Tests\TestCase;

/**
 * « Réviser à voix haute » : phrases, mots et verbes du tableau d'une séance du jour, à écouter, répéter, enregistrer
 * (dans le navigateur) et comparer. Voix du professeur gardée et partagée, préparée en peu de demandes.
 */
class PracticeTest extends TestCase
{
    use BuildsPrograms;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'filesystems.private_disk' => 'local',
            'services.gemini.key' => null,
            'services.openai.key' => null,
            'services.tts.providers' => ['gemini'],
        ]);
    }

    private function learnerWithLesson(): array
    {
        $user = User::factory()->level(Level::A1)->create(['name' => 'Afi', 'welcomed_at' => now()]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();
        $this->buildProgram($user, Level::A1);
        $user = $user->fresh();
        $programLesson = $user->activeProgram->programLessons()->orderBy('position')->first();

        $lesson = $user->lessons()->create([
            'program_id' => $user->activeProgram->id, 'program_lesson_id' => $programLesson->id,
            'date' => today()->subDay(), 'allowed_seconds' => 900, 'used_seconds' => 900,
            'started_at' => now()->subDay(), 'status' => LessonStatus::Completed,
        ]);
        $lesson->forceFill(['summary' => 'Vous savez vous présenter.'])->save();

        return [$user, $lesson];
    }

    /** Un WAV PCM 16 bits de « $count » sons séparés par des silences (le recoupage au silence les retrouve). */
    private function wav(int $count): string
    {
        $rate = 24000;
        $data = '';

        for ($i = 0; $i < $count; $i++) {
            for ($n = 0; $n < (int) ($rate * 0.6); $n++) {
                $data .= pack('v', (int) (8000 * sin(2 * M_PI * 220 * $n / $rate)) & 0xFFFF);
            }
            $data .= str_repeat("\0\0", (int) ($rate * 0.5));
        }

        return 'RIFF'.pack('V', 36 + strlen($data)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16).'data'.pack('V', strlen($data)).$data;
    }

    private function items(Lesson $lesson): array
    {
        return PracticeAudio::items(LessonBoards::for($lesson->programLesson, $lesson->programLesson->program));
    }

    public function test_the_page_shows_each_item_of_the_board_with_listen_record_and_compare(): void
    {
        [$user, $lesson] = $this->learnerWithLesson();
        $items = $this->items($lesson);
        $this->assertNotEmpty($items);

        $response = $this->actingAs($user)->get(route('practice.show', $lesson))->assertOk();

        $response->assertSee('Réviser à voix haute');
        $response->assertSee($items[0]['en']);
        $response->assertSee('Écouter');
        $response->assertSee('Lentement');
        $response->assertSee('M\'enregistrer', false);
        $response->assertSee('Comparer');
        $response->assertSee('il n\'est jamais envoyé', false);
        $response->assertSee('ne prend rien sur votre temps du jour');
        $response->assertSee('js/practice.js');
        $response->assertHeader('X-Robots-Tag');
    }

    public function test_only_the_owner_can_open_it(): void
    {
        [, $lesson] = $this->learnerWithLesson();
        $other = User::factory()->create();
        $this->get(route('practice.show', $lesson))->assertRedirect(route('login'));

        $this->actingAs($other)->get(route('practice.show', $lesson))->assertNotFound();
        $this->actingAs($other)->get(route('practice.audio', [$lesson, 0]))->assertNotFound();
    }

    public function test_it_can_be_turned_off(): void
    {
        [$user, $lesson] = $this->learnerWithLesson();
        config(['platform.practice.enabled' => false]);

        $this->actingAs($user)->get(route('practice.show', $lesson))->assertNotFound();
        $this->actingAs($user)->get(route('lessons.history'))->assertDontSee('Réviser à voix haute');
    }

    public function test_history_dashboard_and_latest_link_lead_to_it(): void
    {
        [$user, $lesson] = $this->learnerWithLesson();

        $this->actingAs($user)->get(route('lessons.history'))->assertSee(route('practice.show', $lesson), false);
        $this->actingAs($user)->get(route('dashboard'))->assertSee(route('practice.show', $lesson), false);
        $this->actingAs($user)->get(route('practice.latest'))->assertRedirect(route('practice.show', $lesson));
    }

    public function test_the_voice_is_prepared_in_one_request_then_served_and_shared(): void
    {
        [$user, $lesson] = $this->learnerWithLesson();
        $items = $this->items($lesson);
        config(['services.gemini.key' => 'cle-gemini-de-test']);
        Http::fake([
            '*/interactions' => Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'audio', 'mime_type' => 'audio/wav', 'data' => base64_encode($this->wav(count($items)))]]]]]),
        ]);

        // Rien de prêt avant l'ouverture : voix du téléphone en attendant.
        $this->actingAs($user)->get(route('practice.audio', [$lesson, 0]))->assertStatus(503);

        // L'ouverture de la page fait préparer la voix après la réponse, en UNE demande pour tout le tableau.
        $this->actingAs($user)->get(route('practice.show', $lesson))->assertOk();
        $this->assertSame(1, Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/interactions'))->count());

        $ready = $this->actingAs($user)->getJson(route('practice.status', $lesson))->assertOk()->json('ready');
        $this->assertSame(array_fill(0, count($items), true), $ready);

        $this->actingAs($user)->get(route('practice.audio', [$lesson, 0]))->assertOk()->assertHeader('Content-Type', 'audio/wav');

        // Un autre élève avec la même leçon : aucune nouvelle demande (fichiers partagés).
        [$other, $otherLesson] = $this->learnerWithLesson();
        $this->actingAs($other)->get(route('practice.show', $otherLesson))->assertOk();
        $this->assertSame(1, Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/interactions'))->count());
    }

    public function test_a_failure_pauses_new_requests(): void
    {
        [$user, $lesson] = $this->learnerWithLesson();
        config(['services.gemini.key' => 'cle-gemini-de-test']);
        Http::fake(['*/interactions' => Http::response(['error' => ['message' => 'indisponible']], 500)]);

        $this->actingAs($user)->get(route('practice.show', $lesson))->assertOk();
        $calls = Http::recorded()->count();
        $this->actingAs($user)->getJson(route('practice.status', $lesson))->assertOk();

        $this->assertSame($calls, Http::recorded()->count());
    }

    public function test_items_are_phrases_then_words_then_verbs_said_naturally(): void
    {
        $items = PracticeAudio::items([
            'phrases' => [['en' => 'My name is Afi.', 'fr' => 'Je m\'appelle Afi.']],
            'vocabulary' => [['en' => 'name', 'fr' => 'nom'], ['en' => 'My name is Afi.', 'fr' => null]],
            'verbs' => [['base' => 'go', 'forms' => 'go / went / gone', 'fr' => 'aller']],
        ]);

        $this->assertSame(['phrase', 'word', 'verb'], array_column($items, 'kind'));
        $this->assertSame('go, went, gone', $items[2]['say']);
        $this->assertSame('go / went / gone', $items[2]['en']);
    }
}
