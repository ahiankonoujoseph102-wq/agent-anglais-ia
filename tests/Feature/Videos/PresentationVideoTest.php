<?php

namespace Tests\Feature\Videos;

use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Models\User;
use App\Support\Plan;
use App\Support\Videos\PresentationScripts;
use App\Support\Videos\PresentationVideos;
use App\Support\Videos\VideoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Vidéos de présentation du site : scripts fidèles à la configuration et sans promesse interdite, voix préparée par
 * Laravel, travaux de GitHub Actions (deux fichiers : TikTok et WhatsApp), page « Vidéos » de l'administration.
 */
class PresentationVideoTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'jeton-de-test-des-videos-assez-long-1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Sleep::fake();
        // Vidéos de Teacher Joe seulement : les versions de Teacher Rose sont vérifiées par PresentationRoseTest.
        config(['teachers.list.rose.ready' => false]);
        config([
            'filesystems.private_disk' => 'local',
            'services.gemini.key' => 'cle-gemini',
            'services.openai.key' => 'cle-openai',
            'videos.voice.providers' => ['gemini', 'openai'],
            'videos.token' => self::TOKEN,
        ]);
        VideoSettings::saveSite('talkami.laravel.cloud');

        Http::fake([
            '*/interactions' => fn (Request $request) => Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'audio', 'mime_type' => 'audio/l16;codec=pcm;rate=24000', 'data' => base64_encode(str_repeat("\x10\x00", 12000))]]]]]),
        ]);
    }

    /** @return list<string> tout ce que voit ou entend le public, pour chaque vidéo */
    private static function allTexts(): array
    {
        $texts = [];

        foreach (PresentationScripts::keys() as $key) {
            $script = PresentationScripts::script($key, 'talkami.laravel.cloud');
            $texts[] = $script['title'];
            $texts[] = $script['caption'];
            $texts[] = implode(' ', $script['hashtags']);

            foreach ($script['segments'] as $segment) {
                $texts[] = $segment['spoken'];
                $texts[] = $segment['subtitle'];
                $texts[] = (string) $segment['chapter'];
            }
        }

        return $texts;
    }

    public function test_there_is_one_long_video_and_six_short_ones_with_every_marker_filled(): void
    {
        $this->assertSame(['talkami', 'compte', 'evaluation', 'formules', 'paiement', 'seance', 'mes-cours', 'ce-soir', 'questions', 'pub-marche', 'pub-marche-court'], PresentationScripts::keys());

        foreach (self::allTexts() as $text) {
            $this->assertDoesNotMatchRegularExpression('/:[a-z_]{3,}/', $text, $text);
        }

        foreach (PresentationScripts::keys() as $key) {
            $script = PresentationScripts::script($key, 'talkami.laravel.cloud');
            $this->assertSame('full', $script['segments'][0]['shot'], "{$key} commence en plein écran");
            $this->assertSame('full', end($script['segments'])['shot'], "{$key} finit en plein écran");

            foreach ($script['segments'] as $segment) {
                $this->assertStringNotContainsString('*', $segment['spoken']);
                $this->assertStringNotContainsString('[en]', $segment['spoken']);
                $this->assertLessThanOrEqual(260, mb_strlen(strip_tags(str_replace(['[en]', '[/en]'], '', $segment['subtitle']))), $segment['subtitle']);
            }
        }

        // Sans adresse du site : aucun script.
        VideoSettings::saveSite(null);
        $this->assertNull(PresentationScripts::script('paiement'));
    }

    public function test_the_evening_video_and_the_questions_never_speak_of_artificial_intelligence(): void
    {
        foreach (['ce-soir', 'questions'] as $key) {
            $script = PresentationScripts::script($key, 'talkami.laravel.cloud');
            $text = implode("\n", array_merge(
                array_column($script['segments'], 'spoken'),
                array_column($script['segments'], 'subtitle'),
                array_merge(...array_column($script['segments'], 'card')),
            ));

            $this->assertDoesNotMatchRegularExpression('/intelligence artificielle|conçu|programmé|humain|robot/iu', $text, $key);
            $this->assertDoesNotMatchRegularExpression('/\bIA\b/u', $text, $key);
            $this->assertDoesNotMatchRegularExpression('/gagner plus|garanti|emploi assuré|places limitées|dernière chance/iu', $text, $key);
        }

        $evening = PresentationScripts::script('ce-soir', 'talkami.laravel.cloud');
        $this->assertSame(['CE SOIR · 19 HEURES'], $evening['segments'][0]['card']);
        $this->assertSame(['CE SOIR · 19 HEURES', 'Groupe WhatsApp Talkami'], $evening['segments'][1]['card']);
        // Dit en lettres, écrit en chiffres ; aucune adresse de site dans la vidéo, le lien du groupe dans la publication.
        $this->assertStringContainsString('dix-neuf heures', $evening['segments'][1]['spoken']);
        $this->assertStringContainsString('[en]19 heures[/en]', $evening['segments'][1]['subtitle']);
        $this->assertStringNotContainsString('talkami.laravel.cloud', implode(' ', array_column($evening['segments'], 'subtitle')));
        $this->assertStringContainsString('[collez ici le lien du groupe]', $evening['caption']);
        config(['platform.contact.whatsapp_group' => 'https://chat.whatsapp.com/exemple']);
        $this->assertStringContainsString('https://chat.whatsapp.com/exemple', PresentationScripts::script('ce-soir', 'talkami.laravel.cloud')['caption']);
        $this->assertStringContainsString('chaleureux et enthousiaste', $evening['voice_style']);

        // Pays et bureau lus dans la configuration.
        $questions = implode(' ', array_column(PresentationScripts::script('questions', 'talkami.laravel.cloud')['segments'], 'spoken'));
        $this->assertStringContainsString('au Togo, au Burkina Faso, au Bénin et en Côte d\'Ivoire', $questions);
        $this->assertStringContainsString((string) config('platform.contact.address'), $questions);
        $this->assertStringContainsString('Rien n\'est prélevé automatiquement', $questions);
    }

    public function test_the_market_ad_exists_in_20_and_10_seconds_without_ai_levels_or_urgency(): void
    {
        foreach (['pub-marche', 'pub-marche-court'] as $key) {
            $script = PresentationScripts::script($key, 'talkami.laravel.cloud');
            $this->assertSame('ad', $script['layout'], $key);
            $text = implode("\n", array_merge(
                array_column($script['segments'], 'spoken'),
                array_column($script['segments'], 'subtitle'),
                array_merge(...array_column($script['segments'], 'card')),
                [$script['caption']],
            ));

            $this->assertDoesNotMatchRegularExpression('/intelligence artificielle|conçu|programmé|humain|robot/iu', $text, $key);
            $this->assertDoesNotMatchRegularExpression('/\bIA\b|\b[ABC][12]\b/u', $text, $key);
            $this->assertDoesNotMatchRegularExpression('/gagner plus|garanti|emploi|places limitées|dernière chance|aujourd\'hui seulement/iu', $text, $key);
            // La phrase enseignée vient du programme officiel (débutant, unité « Au marché »).
            $this->assertStringContainsString("It's two thousand francs.", $text);
            $this->assertSame('pop', $script['segments'][0]['sfx']);
            $this->assertContains('ding', array_column($script['segments'], 'sfx'));
            // Bandeau : l'anglais en orange.
            $this->assertSame('[en]How much is it?[/en]', $script['segments'][0]['card'][0]);
        }

        $this->assertStringContainsString("It's two thousand francs.", (string) file_get_contents(resource_path('curriculum/a1.php')));
        $long = PresentationScripts::script('pub-marche', 'talkami.laravel.cloud');
        $this->assertSame('Réfléchissez…', $long['segments'][1]['spoken']);
        $this->assertSame(2000, $long['segments'][1]['pause_after']);
        $this->assertSame('ÉVALUATION GRATUITE', end($long['segments'])['card'][0]);
        $this->assertStringContainsString('cinq minutes', end($long['segments'])['spoken']);

        // Les autres vidéos ne changent ni de mise en page ni d'empreinte de voix.
        $payment = PresentationScripts::script('paiement', 'talkami.laravel.cloud');
        $this->assertNull($payment['layout']);
        $this->assertSame(hash('sha256', json_encode(array_column($payment['segments'], 'spoken'))), PresentationScripts::hash($payment));
        $this->assertNotSame(hash('sha256', json_encode(array_column($long['segments'], 'spoken'))), PresentationScripts::hash($long));
    }

    public function test_the_ad_voice_keeps_a_two_second_silence_and_its_job_raises_the_subtitles(): void
    {
        $videos = app(PresentationVideos::class);
        $this->assertSame(1, $videos->request('pub-marche'));
        $videos->prepare(microtime(true) + 600);
        $video = PresentationVideo::query()->where('key', 'pub-marche')->firstOrFail();
        $this->assertSame(LessonVideo::VOICED, $video->status);

        // Chaque partie de voix simulée dure 0,5 seconde ; « Réfléchissez… » est suivie de 2 secondes de silence.
        $think = $video->timings['segments'][1];
        $this->assertEqualsWithDelta(2.5, $think['end'] - $think['start'], 0.01);
        $this->assertEqualsWithDelta(0.5, $video->timings['segments'][0]['end'] - $video->timings['segments'][0]['start'], 0.01);

        $job = $videos->job($video);
        $this->assertSame('ad', $job['render']['layout']);
        $this->assertSame(1100, $job['render']['subtitles']['top']);
        $this->assertSame(300, $job['render']['card']['top']);
        $this->assertSame(380, $job['render']['medallion']['y']);
        $this->assertSame(['pop', null, 'ding', null, 'pop'], array_column($job['segments'], 'sfx'));
        $this->assertSame(16 * 1024 * 1024, $job['light_limit_bytes']);
        $this->assertTrue($video->isAd());

        // Une vidéo ordinaire garde les sous-titres en bas et aucun bruitage.
        $videos->request('paiement');
        $videos->prepare(microtime(true) + 600);
        $payment = $videos->job(PresentationVideo::query()->where('key', 'paiement')->firstOrFail());
        $this->assertSame(1408, $payment['render']['subtitles']['top']);
        $this->assertArrayNotHasKey('layout', $payment['render']);
        $this->assertArrayNotHasKey('sfx', $payment['render']);
        $this->assertSame([], array_filter(array_column($payment['segments'], 'sfx')));
    }

    public function test_every_scene_of_the_scripts_is_filmed_by_the_tool(): void
    {
        preg_match_all("/^    '?([a-z]+(?:\\.[a-z0-9-]+)*)'?: \\{/m", (string) file_get_contents(base_path('tools/presentation/scenes.mjs')), $matches);
        $known = $matches[1];
        $this->assertContains('pay.step3', $known);

        foreach (PresentationScripts::keys() as $key) {
            foreach (PresentationScripts::script($key, 'talkami.laravel.cloud')['segments'] as $segment) {
                $this->assertContains($segment['scene'], $known, "{$key} : scène {$segment['scene']}");
            }
        }

        // Le site de démonstration ne tourne jamais en production.
        $this->assertStringContainsString('isProduction()', (string) file_get_contents(database_path('seeders/PresentationDemoSeeder.php')));
    }

    public function test_prices_and_durations_come_from_the_configuration(): void
    {
        $formules = PresentationScripts::script('formules', 'talkami.laravel.cloud');
        $subtitles = implode(' ', array_column($formules['segments'], 'subtitle'));
        $spoken = implode(' ', array_column($formules['segments'], 'spoken'));

        foreach (Plan::all() as $plan) {
            $this->assertStringContainsString(number_format($plan->price, 0, ',', "\u{00A0}"), $subtitles, $plan->key);
            $this->assertStringContainsString($plan->name, $subtitles);
        }

        $this->assertStringContainsString('sept mille cinq cents francs CFA', $spoken);
        $this->assertSame('sept mille cinq cents', PresentationScripts::words(7500));
        $this->assertSame('quatre-vingts', PresentationScripts::words(80));
        $this->assertSame('soixante et onze', PresentationScripts::words(71));
        $this->assertSame('deux mille', PresentationScripts::words(2000));

        // Adresse dite « point », affichée telle quelle.
        $closing = end(PresentationScripts::script('paiement', 'talkami.laravel.cloud')['segments']);
        $this->assertTrue(collect(PresentationScripts::script('talkami', 'talkami.laravel.cloud')['segments'])->contains(fn ($s) => str_contains($s['spoken'], 'talkami point laravel point cloud')));
        $this->assertIsArray($closing);
    }

    public function test_no_forbidden_promise_or_other_country_is_said_or_shown(): void
    {
        $all = mb_strtolower(implode("\n", self::allTexts()));

        foreach (['accès immédiat', 'remboursement', 'remboursé', 'carte bancaire', 'rappel automatique', 'garanti', '100 %', 'sans engagement', 'sans risque', 'certificat', 'diplôme', 'ghana', 'accra', 'aflao', 't-money', 'gemini', 'openai', 'chatgpt', 'google', 'places limitées', 'dernière chance', 'opérateur'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $all, $forbidden);
        }

        // Le paiement dit honnêtement ce qui se passe après la capture.
        $payment = mb_strtolower(implode(' ', array_column(PresentationScripts::script('paiement', 'talkami.laravel.cloud')['segments'], 'spoken')));
        $this->assertStringContainsString('si votre capture est bien lue', $payment);
        $this->assertStringContainsString('notre équipe vérifie', $payment);
    }

    public function test_the_voice_is_prepared_then_github_takes_the_job_and_uploads_both_versions(): void
    {
        $videos = app(PresentationVideos::class);
        $this->assertCount(11, $videos->all());
        $this->assertSame('Pas encore préparée', PresentationVideo::query()->where('key', 'paiement')->first()->statusLabel());

        $this->assertSame(1, $videos->request('paiement'));
        $video = PresentationVideo::query()->where('key', 'paiement')->firstOrFail();
        $this->assertSame(LessonVideo::SCRIPTED, $video->status);

        for ($i = 0; $i < 3; $i++) {
            $videos->prepare(microtime(true) + 3600);
        }

        $video->refresh();
        $this->assertSame(LessonVideo::VOICED, $video->status);
        $this->assertCount(count($video->script['segments']), $video->timings['segments']);
        // Silence de présentation (300 millisecondes) entre deux parties.
        $gap = $video->timings['segments'][1]['start'] - $video->timings['segments'][0]['end'];
        $this->assertEqualsWithDelta(0.3, $gap, 0.01);
        // Ton de présentation demandé à la synthèse vocale.
        Http::assertSent(fn (Request $request) => str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), 'présente son site'));

        $headers = ['Authorization' => 'Bearer '.self::TOKEN];
        $this->getJson(route('api.presentations.jobs'))->assertUnauthorized();
        $this->getJson(route('api.presentations.jobs', ['apercu' => 1, 'cles' => 'paiement']), $headers)->assertOk()->assertJson(['waiting' => 1]);
        $this->getJson(route('api.presentations.jobs', ['apercu' => 1, 'cles' => 'compte']), $headers)->assertOk()->assertJson(['waiting' => 0]);

        $job = $this->getJson(route('api.presentations.jobs', ['cles' => 'paiement']), $headers)->assertOk()->json('jobs.0');
        $this->assertSame('paiement', $job['key']);
        $this->assertSame('talkami.laravel.cloud', $job['site']);
        $this->assertSame(30, $job['render']['fps']);
        $this->assertSame(16 * 1024 * 1024, $job['light_limit_bytes']);
        $this->assertSame('screen', $job['segments'][1]['shot']);
        $this->assertArrayHasKey('start', $job['segments'][1]);
        $this->assertArrayNotHasKey('spoken', $job['segments'][1]);
        $this->assertStringNotContainsString('cle-', json_encode($job));
        $this->assertSame(LessonVideo::RENDERING, $video->fresh()->status);

        $this->get($job['audio_url'], $headers)->assertOk();

        $put = fn (string $version, string $body) => $this->call('PUT', route('api.presentations.upload', ['presentation' => $video, 'version' => $version]), [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN, 'CONTENT_TYPE' => 'video/mp4', 'CONTENT_LENGTH' => (string) strlen($body)], $body);
        $put('tiktok', 'grande-video-1234')->assertOk();

        // Une seule version déposée : pas encore prête.
        $this->postJson($job['done_url'], ['duration' => 41.2, 'loudness_lufs' => -14.1, 'true_peak_db' => -1.2, 'lips_correlation' => 0.84], $headers)->assertStatus(422);

        $put('whatsapp', 'legere')->assertOk();
        $this->postJson($job['done_url'], ['duration' => 41.2, 'loudness_lufs' => -14.1, 'true_peak_db' => -1.2, 'lips_correlation' => 0.84], $headers)->assertOk();

        $video->refresh();
        $this->assertSame(LessonVideo::READY, $video->status);
        $this->assertSame(42, $video->duration_seconds);
        $this->assertSame(17, $video->size_bytes);
        $this->assertSame(6, $video->light_size_bytes);
        $this->assertSame(-14.1, $video->loudness_lufs);

        // Même texte : la voix est gardée, la vidéo repart directement en fabrication.
        $this->assertSame(1, $videos->request('paiement'));
        $this->assertSame(LessonVideo::VOICED, $video->fresh()->status);
    }

    public function test_a_changed_text_prepares_a_new_voice(): void
    {
        $videos = app(PresentationVideos::class);
        $videos->request('compte');

        for ($i = 0; $i < 3; $i++) {
            $videos->prepare(microtime(true) + 3600);
        }

        $video = PresentationVideo::query()->where('key', 'compte')->firstOrFail();
        $this->assertSame(LessonVideo::VOICED, $video->status);

        $video->update(['script_hash' => 'ancienne-empreinte']);
        $videos->request('compte');
        $video->refresh();
        $this->assertSame(LessonVideo::SCRIPTED, $video->status);
        $this->assertNull($video->audio_path);
    }

    public function test_nothing_is_requested_without_the_site_address(): void
    {
        VideoSettings::saveSite(null);
        $this->assertSame(0, app(PresentationVideos::class)->request());
        $this->assertSame(0, PresentationVideo::query()->where('status', LessonVideo::SCRIPTED)->count());
    }

    public function test_the_admin_page_shows_the_presentations_and_both_downloads(): void
    {
        $admin = User::factory()->admin()->create();
        app(PresentationVideos::class)->all();
        $video = PresentationVideo::query()->where('key', 'formules')->firstOrFail();
        $video->update([
            'status' => LessonVideo::READY, 'caption' => 'Trois formules, du prix le plus bas au plus haut. #anglais',
            'video_path' => 'presentations/formules/a.mp4', 'light_path' => 'presentations/formules/b.mp4',
            'duration_seconds' => 44, 'size_bytes' => 30_000_000, 'light_size_bytes' => 9_000_000,
        ]);
        Storage::disk('local')->put('presentations/formules/a.mp4', 'faux');
        Storage::disk('local')->put('presentations/formules/b.mp4', 'faux');

        $this->actingAs($admin)->get(route('admin.videos.index'))
            ->assertOk()
            ->assertSee('Vidéos de présentation')
            ->assertSee('Préparer les vidéos pas encore prêtes')
            ->assertSee('Télécharger pour TikTok')
            ->assertSee('Télécharger pour WhatsApp')
            ->assertSee('Contenu généré par IA')
            ->assertSee('Pas encore préparée')
            ->assertSee('Préparer cette vidéo seulement')
            ->assertSee('Publicité : au Grand Marché (20 secondes)')
            ->assertSee('Publicité : au Grand Marché (10 secondes)');

        $this->get(route('admin.videos.presentations.file', ['presentation' => $video, 'telecharger' => 1]))->assertRedirect();
        $this->get(route('admin.videos.presentations.file', ['presentation' => $video, 'version' => 'whatsapp', 'telecharger' => 1]))->assertRedirect();
        $this->get(route('admin.videos.presentations.file', PresentationVideo::query()->where('key', 'compte')->first()))->assertNotFound();

        $this->post(route('admin.videos.presentations.request'))->assertRedirect(route('admin.videos.index').'#presentations');
        // Toutes sauf « formules », déjà prête : le bouton commun ne refait jamais une vidéo prête.
        $this->assertSame(10, PresentationVideo::query()->whereIn('status', [LessonVideo::SCRIPTED, LessonVideo::VOICED])->count());
        $this->assertSame(LessonVideo::READY, $video->fresh()->status);
        $this->post(route('admin.videos.presentations.request'), ['key' => 'inconnue'])->assertNotFound();
    }

    public function test_a_learner_cannot_request_or_download_a_presentation(): void
    {
        $learner = User::factory()->create();
        app(PresentationVideos::class)->all();

        $this->actingAs($learner)->post(route('admin.videos.presentations.request'))->assertForbidden();
        $this->get(route('admin.videos.presentations.file', PresentationVideo::query()->first()))->assertForbidden();
    }
}
