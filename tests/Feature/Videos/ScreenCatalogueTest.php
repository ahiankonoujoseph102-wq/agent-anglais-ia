<?php

namespace Tests\Feature\Videos;

use App\Enums\Role;
use App\Models\LessonVideo;
use App\Models\Payment;
use App\Models\PresentationVideo;
use App\Models\User;
use App\Support\Videos\ScreenCatalogue;
use Database\Seeders\CatalogueDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Catalogue des écrans pour un montage (tools/presentation/catalogue.mjs) : voix de Teacher Joe donnée à GitHub,
 * dépôt de la vidéo et de la voix, téléchargement dans la page « Vidéos », base de démonstration sans paiement.
 */
class ScreenCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'jeton-de-test-des-videos-assez-long-1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['filesystems.private_disk' => 'local', 'videos.token' => self::TOKEN]);
    }

    private function api(string $method, string $uri, array $data = [])
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.self::TOKEN, 'Accept' => 'application/json'])->json($method, $uri, $data);
    }

    private function readyVoice(): PresentationVideo
    {
        $video = PresentationVideo::query()->create([
            'key' => 'questions', 'title' => 'Vos questions, mes réponses', 'status' => LessonVideo::READY, 'audio_path' => 'presentations/questions/voice.wav',
            'script' => ['segments' => [['shot' => 'full', 'text' => 'Un.'], ['shot' => 'full', 'text' => 'Deux.'], ['shot' => 'screen', 'text' => 'Trois.']]],
            'timings' => ['duration' => 20, 'segments' => [['start' => 0, 'end' => 4.2], ['start' => 4.5, 'end' => 14.8], ['start' => 15.1, 'end' => 20]]],
        ]);
        Storage::disk('local')->put($video->audio_path, 'RIFF-voix');

        return $video;
    }

    private function passages(): array
    {
        return [
            ['number' => 1, 'key' => 'accueil-haut', 'title' => 'Accueil', 'start' => 0, 'end' => 12, 'moments' => [[5.6, 'doigt']]],
            ['number' => 2, 'key' => 'teacher-joe-parle', 'title' => 'Teacher Joe parle', 'start' => 13, 'end' => 33, 'moments' => []],
        ];
    }

    public function test_github_needs_the_token_and_a_ready_voice(): void
    {
        $this->getJson('/api/catalogue/travail')->assertUnauthorized();
        $this->api('GET', '/api/catalogue/travail')->assertStatus(409)->assertJsonPath('message', fn ($m) => str_contains($m, 'questions'));

        $video = $this->readyVoice();
        $job = $this->api('GET', '/api/catalogue/travail')->assertOk()->json();

        $this->assertSame(route('api.presentations.audio', $video), $job['voice']['audio_url']);
        $this->assertSame(['full', 'full', 'screen'], array_column($job['voice']['segments'], 'shot'));
        $this->assertSame(4.5, $job['voice']['segments'][1]['start']);
        $this->assertArrayHasKey('camera', $job['render']);
        $this->assertSame(['video', 'voix'], array_keys($job['upload']));
        $this->api('GET', $job['voice']['audio_url'])->assertOk();
    }

    public function test_both_files_are_stored_then_offered_in_the_videos_page(): void
    {
        $this->readyVoice();
        $this->api('POST', '/api/catalogue/pret', ['duration' => 33, 'voice_start' => 13, 'passages' => $this->passages()])->assertStatus(409);

        foreach (['video' => 'mp4-catalogue', 'voix' => 'RIFF-voix'] as $file => $body) {
            $this->call('PUT', "/api/catalogue/fichier/{$file}", [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN, 'CONTENT_LENGTH' => strlen($body), 'HTTP_ACCEPT' => 'application/json'], $body)->assertOk();
        }

        $this->api('POST', '/api/catalogue/pret', ['duration' => 33, 'voice_start' => 13, 'passages' => $this->passages()])->assertOk();
        $this->assertSame('mp4-catalogue', Storage::disk('local')->get(ScreenCatalogue::path('video')));

        $admin = User::factory()->create();
        $admin->role = Role::Admin;
        $admin->save();

        $this->actingAs($admin)->get('/admin/videos')->assertOk()
            ->assertSee('Catalogue des écrans (pour un montage)')
            ->assertSee('Télécharger la vidéo du catalogue')
            ->assertSee('Télécharger la voix de '.config('avatar.model.name'))
            ->assertSee('La voix se place à 0:13', false)
            ->assertSee('0:13 à 0:33', false);
        // Adresse signée de courte durée du bucket privé (téléchargement direct, aucune adresse publique).
        $this->actingAs($admin)->get('/admin/videos/catalogue/video')->assertRedirect();
        $this->actingAs($admin)->get('/admin/videos/catalogue/voix')->assertRedirect();
    }

    public function test_only_administrators_download_the_catalogue(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/videos/catalogue/video')->assertForbidden();
    }

    public function test_the_demonstration_base_has_no_payment_and_an_administrator(): void
    {
        $this->seed(CatalogueDemoSeeder::class);

        $this->assertSame(0, Payment::query()->count());
        $this->assertTrue(User::query()->where('phone', CatalogueDemoSeeder::ADMIN_PHONE)->first()->isAdmin());
        $this->assertSame(CatalogueDemoSeeder::LESSON_VIDEOS, LessonVideo::query()->where('status', LessonVideo::READY)->count());
        $this->assertSame(['evaluation', 'talkami', 'questions'], PresentationVideo::query()->whereNotNull('web_path')->orderBy('id')->pluck('key')->all());

        // Sans paiement en attente, aucun compteur dans l'administration ; aucun montant encaissé.
        $admin = User::query()->where('phone', CatalogueDemoSeeder::ADMIN_PHONE)->first();
        $this->actingAs($admin)->get('/admin/videos')->assertOk()->assertDontSee('FCFA');
    }
}
