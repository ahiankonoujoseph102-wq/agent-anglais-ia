<?php

namespace Tests\Feature\Videos;

use App\Enums\Role;
use App\Models\LearnerError;
use App\Models\Lesson;
use App\Models\LessonVideo;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Videos\ClubCatalog;
use App\Support\Videos\ClubScriptWriter;
use App\Support\Videos\ClubVideos;
use App\Support\Videos\LessonVideos;
use App\Support\Videos\VideoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Vidéo du jour du Club des abonnés : thème selon le jour, abonnés payants seulement, erreurs gardées seulement si
 * au moins trois personnes les ont faites (jamais une phrase dite ni un prénom), repli honnête du lundi, chiffres
 * globaux du samedi écrits par le code, aucune répétition, fabrication en priorité, bloc de la page « Vidéos » visible
 * par un assistant.
 */
class ClubVideoTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'jeton-de-test-des-videos-assez-long-1234567890';

    /** Réponse du script (modèle texte). */
    private array $script;

    /** Réponse du regroupement des erreurs. */
    private array $groups = ['groups' => []];

    /** Durée de chaque partie de la voix simulée, en secondes (une demande regroupe plusieurs parties). */
    private int $pieceSeconds = 16;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Sleep::fake();
        // Teacher Joe chaque jour : l'alternance avec Teacher Rose est vérifiée par tests/Feature/Videos/VideoTeachersTest.php.
        config(['teachers.list.rose.ready' => false]);
        config([
            'filesystems.private_disk' => 'local',
            'services.gemini.key' => 'cle-gemini',
            'services.openai.key' => null,
            'services.text.providers' => ['gemini'],
            'videos.voice.providers' => ['gemini'],
            'videos.token' => self::TOKEN,
        ]);
        VideoSettings::saveSite('talkami.net');
        $this->script = $this->script();

        Http::fake([
            '*/interactions' => function (Request $request) {
                if (($request['response_format']['type'] ?? null) === 'audio') {
                    return Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'audio', 'mime_type' => 'audio/l16;codec=pcm;rate=24000', 'data' => base64_encode(str_repeat("\x10\x00", 24000 * $this->pieceSeconds * (substr_count((string) ($request['input'][0]['content'][0]['text'] ?? ''), "\n\n") + 1)))]]]]]);
                }

                $answer = str_contains(json_encode($request->data()), 'You group the English mistakes') ? $this->groups : $this->script;

                return Http::response(['steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => json_encode($answer)]]]], 'usage' => ['total_input_tokens' => 900, 'total_output_tokens' => 700]]);
            },
        ]);
    }

    /** Un script d'environ 340 mots (environ 3 minutes avec les silences) : chaque phrase anglaise traduite et expliquée. */
    private function script(string $extra = '', array $override = []): array
    {
        $segment = fn (string $i) => $override + [
            'say' => "Partie {$i} : on apprend ensemble une phrase utile pour la vie de tous les jours. {$extra}",
            'english' => "How's it going?",
            'translation' => 'Comment ça va ?',
            'explain' => 'On l\'utilise avec des amis ou des collègues, le matin ou l\'après-midi, jamais dans une lettre officielle.',
            'repeat' => true,
        ];
        $numbers = ['une', 'deux', 'trois', 'quatre', 'cinq', 'six'];

        return ['title' => 'Une expression pour le Club', 'segments' => [['say' => 'Bonjour à tous, bienvenue dans la vidéo du Club ! Aujourd\'hui, une petite leçon pour parler tout de suite.', 'english' => '', 'translation' => '', 'explain' => '', 'repeat' => false], ['say' => 'Écoutez bien, puis répétez à voix haute avec moi, sans peur.', 'english' => '', 'translation' => '', 'explain' => '', 'repeat' => false], ...array_map($segment, $numbers)], 'caption' => 'Voici votre vidéo du jour. Répondez-moi en message vocal dans le groupe !'];
    }

    private function words(): int
    {
        return (int) round(config('videos.club.duration_seconds') * config('videos.words_per_second'));
    }

    private function member(string $name = 'Kossi Mensah'): User
    {
        $user = User::factory()->create(['name' => $name]);
        Subscription::factory()->for($user)->forPlan('essentiel')->create();

        return $user;
    }

    private function prepareAll(): void
    {
        for ($i = 0; $i < 3; $i++) {
            app(LessonVideos::class)->prepare(3600);
        }
    }

    public function test_one_club_video_a_day_only_with_paying_subscribers_and_the_theme_of_the_day(): void
    {
        Carbon::setTestNow('2026-10-29 08:00'); // jeudi
        $club = app(ClubVideos::class);

        // Accès offert seulement : pas d'abonné payant, pas de vidéo.
        $offered = User::factory()->create();
        Subscription::factory()->for($offered)->forPlan('essentiel')->create(['granted_days' => 7, 'price' => 0]);
        $this->assertNull($club->ensureToday());

        $this->member();
        $video = $club->ensureToday();
        $this->assertNotNull($video);
        $this->assertSame(LessonVideo::CLUB, $video->kind);
        $this->assertSame('dialogue', $video->theme);
        $this->assertSame('Mini-dialogue de situation', $video->themeLabel());
        $this->assertNull($club->ensureToday());

        // Les vidéos de promotion ne comptent pas la vidéo du Club.
        $this->assertSame(2, app(LessonVideos::class)->planToday());
        $this->assertSame(3, LessonVideo::query()->whereDate('day', today())->count());

        $themes = [];

        foreach (range(26, 31) as $day) {
            $themes[] = ClubVideos::theme(Carbon::parse("2026-10-{$day}"))['key'];
        }

        $themes[] = ClubVideos::theme(Carbon::parse('2026-11-01'))['key'];
        $this->assertSame(['errors', 'expression', 'sound', 'dialogue', 'challenge', 'progress', 'revision'], $themes);
    }

    public function test_the_club_video_is_written_voiced_and_taken_first_by_github_without_any_advertising_ending(): void
    {
        Carbon::setTestNow('2026-10-27 08:00'); // mardi : expression
        $this->member();
        app(LessonVideos::class)->plan(today(), 1);
        $this->artisan('videos:prepare --budget=1')->assertSuccessful();
        $this->prepareAll();

        $video = LessonVideo::query()->club()->firstOrFail();
        $this->assertSame(LessonVideo::VOICED, $video->status);
        $this->assertSame('expression', $video->items[0]['type']);
        $this->assertSame(ClubCatalog::all('expressions')[0]['key'], $video->items[0]['key']);
        $segments = $video->script['segments'];
        $last = end($segments);
        $this->assertSame('Répondez-moi dans le groupe du Club. À demain !', $last['spoken']);
        $this->assertStringNotContainsString('abonnez-vous', mb_strtolower(json_encode($video->script, JSON_UNESCAPED_UNICODE)));
        $this->assertStringEndsWith('— '.config('avatar.model.name'), $video->caption);
        $this->assertSame('talkami-club-2026-10-27.mp4', $video->downloadName());

        $jobs = $this->withToken(self::TOKEN)->getJson('/api/videos/travaux?limit=1')->assertOk()->json('jobs');
        $this->assertSame($video->id, $jobs[0]['id']);
        $this->assertSame(LessonVideo::RENDERING, $video->fresh()->status);
    }

    public function test_no_expression_sound_or_situation_comes_back_before_several_weeks(): void
    {
        Carbon::setTestNow('2026-10-27 08:00');
        $this->member();
        $first = ClubCatalog::all('expressions')[0]['key'];
        LessonVideo::query()->create(['kind' => 'club', 'theme' => 'expression', 'topic_key' => 'club-2026-10-20', 'day' => '2026-10-20', 'slot' => 0, 'level' => 'A2', 'title' => 'x', 'status' => LessonVideo::READY, 'items' => [['type' => 'expression', 'key' => $first, 'text' => 'x']]]);

        $video = app(ClubVideos::class)->ensureToday();
        $this->assertSame(ClubCatalog::all('expressions')[1]['key'], app(ClubVideos::class)->brief($video)['items'][0]['key']);

        // Au-delà de la fenêtre, l'élément peut revenir.
        Carbon::setTestNow(Carbon::parse('2026-10-20')->addWeeks((int) config('videos.club.repeat_weeks') + 1));
        $later = LessonVideo::query()->create(['kind' => 'club', 'theme' => 'expression', 'topic_key' => 'club-later', 'day' => today(), 'slot' => 0, 'level' => 'A2', 'title' => 'x', 'status' => LessonVideo::PLANNED]);
        $this->assertSame($first, app(ClubVideos::class)->brief($later)['items'][0]['key']);
    }

    public function test_a_mistake_is_kept_only_when_three_different_people_made_it_and_never_as_they_said_it(): void
    {
        $said = ['he go to school', 'he goes to school'];
        $group = fn (array $people, string $wrong = 'She work at the market.') => ['label' => 'Oubli du -s à la troisième personne', 'people' => $people, 'wrong_example' => $wrong, 'right_example' => 'She works at the market.', 'rule' => 'Avec he, she ou it, le verbe prend un -s.'];

        $this->assertCount(1, ClubScriptWriter::keepGroups([$group(['p1', 'p2', 'p3'])], ['p1', 'p2', 'p3'], $said, []));
        // Deux personnes seulement, ou des pseudonymes inventés : refusé.
        $this->assertSame([], ClubScriptWriter::keepGroups([$group(['p1', 'p2'])], ['p1', 'p2', 'p3'], $said, []));
        $this->assertSame([], ClubScriptWriter::keepGroups([$group(['p1', 'p2', 'p9'])], ['p1', 'p2', 'p3'], $said, []));
        $this->assertSame([], ClubScriptWriter::keepGroups([$group(['p1', 'p1', 'p2'])], ['p1', 'p2', 'p3'], $said, []));
        // Une phrase telle qu'un abonné l'a dite, ou un prénom : refusé.
        $this->assertSame([], ClubScriptWriter::keepGroups([$group(['p1', 'p2', 'p3'], 'He go to school!')], ['p1', 'p2', 'p3'], $said, []));
        $this->assertSame([], ClubScriptWriter::keepGroups([$group(['p1', 'p2', 'p3'], 'Kossi work at the market.')], ['p1', 'p2', 'p3'], $said, [], ['Kossi']));
        // Présentée récemment : refusée.
        $this->assertSame([], ClubScriptWriter::keepGroups([$group(['p1', 'p2', 'p3'])], ['p1', 'p2', 'p3'], $said, ['oubli-du-s-a-la-troisieme-personne']));
    }

    public function test_monday_presents_the_common_mistakes_of_the_club_without_names_nor_ids(): void
    {
        Carbon::setTestNow('2026-10-26 08:00'); // lundi

        foreach (['Kossi Mensah', 'Afi Amouzou', 'Yao Agbeko'] as $name) {
            LearnerError::query()->forceCreate(['user_id' => $this->member($name)->id, 'wrong' => 'he go to school', 'correct' => 'he goes to school', 'explanation' => 'Troisième personne']);
        }

        $this->groups = ['groups' => [['label' => 'Oubli du -s à la troisième personne', 'people' => ['p1', 'p2', 'p3'], 'wrong_example' => 'She work at the market.', 'right_example' => 'She works at the market.', 'rule' => 'Avec he, she ou it, le verbe prend un -s.']]];
        $video = app(ClubVideos::class)->ensureToday();
        $this->assertTrue(app(ClubVideos::class)->writeScript($video));

        $video->refresh();
        $this->assertSame('errors', $video->theme);
        $this->assertSame('error', $video->items[0]['type']);

        Http::assertSent(function (Request $request) {
            $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($body, 'You group the English mistakes')
                && ! str_contains($body, 'Kossi') && ! str_contains($body, 'Mensah') && ! preg_match('/"user_id"/', $body);
        });
    }

    public function test_monday_without_enough_common_mistakes_falls_back_honestly_on_the_traps_of_french_speakers(): void
    {
        Carbon::setTestNow('2026-10-26 08:00');
        $user = $this->member();
        LearnerError::query()->forceCreate(['user_id' => $user->id, 'wrong' => 'I have 25 years', 'correct' => "I'm 25", 'explanation' => 'Âge']);

        $video = app(ClubVideos::class)->ensureToday();
        $brief = app(ClubVideos::class)->brief($video);
        $this->assertSame('pitfall', $brief['theme']);
        $this->assertSame(ClubCatalog::pitfalls()[0]['text'], $brief['data']);
        Http::assertNothingSent();

        // « J'ai remarqué » sur un piège qui n'a pas été observé : script refusé.
        $words = $this->words();
        $this->assertNull(ClubScriptWriter::normalize($this->script("J'ai remarqué cette erreur chez vous."), $words, $brief));
        $this->assertNotNull(ClubScriptWriter::normalize($this->script(), $words, $brief));

        $this->assertTrue(app(ClubVideos::class)->writeScript($video));
        $this->assertSame('pitfall', $video->fresh()->theme);
        $this->assertSame('Les pièges des francophones', $video->fresh()->themeLabel());
    }

    public function test_a_script_naming_a_subscriber_is_refused(): void
    {
        $words = $this->words();
        $brief = ['theme' => 'expression', 'data' => 'x', 'figures' => null];

        $this->assertNull(ClubScriptWriter::normalize($this->script('Bravo Kossi !'), $words, $brief, ['Kossi']));
        $this->assertNotNull(ClubScriptWriter::normalize($this->script('Bravo à tous !'), $words, $brief, ['Kossi']));
    }

    public function test_saturday_gives_global_figures_written_by_the_code_only_with_three_people(): void
    {
        Carbon::setTestNow('2026-10-31 08:00'); // samedi
        $words = $this->words();
        $members = [$this->member('Kossi Mensah'), $this->member('Afi Amouzou')];

        foreach ($members as $member) {
            Lesson::query()->forceCreate(['user_id' => $member->id, 'date' => '2026-10-29', 'allowed_seconds' => 900, 'used_seconds' => 600, 'status' => 'completed']);
        }

        $video = app(ClubVideos::class)->ensureToday();
        $this->assertNull(app(ClubVideos::class)->brief($video)['figures']);

        $third = $this->member('Yao Agbeko');
        Lesson::query()->forceCreate(['user_id' => $third->id, 'date' => '2026-10-30', 'allowed_seconds' => 900, 'used_seconds' => 900, 'status' => 'completed']);

        $brief = app(ClubVideos::class)->brief($video);
        $this->assertSame('Ces 7 derniers jours, les membres du Club ont parlé anglais 35 minutes avec moi, en 3 séances. Bravo à vous !', $brief['figures']);

        $script = ClubScriptWriter::normalize($this->script(), $words, $brief);
        $this->assertSame($brief['figures'], $script['segments'][0]['spoken']);
        // Un chiffre écrit par le modèle lui-même : refusé.
        $this->assertNull(ClubScriptWriter::normalize($this->script('Vous avez fait 12 séances.'), $words, $brief));
    }

    public function test_the_club_block_is_on_the_videos_page_and_an_assistant_sees_only_it(): void
    {
        Carbon::setTestNow('2026-10-29 08:00');
        $this->member();
        $club = LessonVideo::query()->create(['kind' => 'club', 'theme' => 'dialogue', 'topic_key' => 'club-2026-10-29', 'day' => today(), 'slot' => 0, 'level' => 'A2', 'title' => 'Au restaurant', 'status' => LessonVideo::READY, 'video_path' => 'videos/club.mp4', 'caption' => "Votre mini-dialogue du jour.\n\n— Teacher Joe"]);
        Storage::disk('local')->put('videos/club.mp4', 'mp4');
        app(LessonVideos::class)->plan(today(), 1);
        $promo = LessonVideo::query()->promo()->firstOrFail();
        $promo->update(['video_path' => 'videos/promo.mp4']);
        Storage::disk('local')->put('videos/promo.mp4', 'mp4');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.videos.index'))->assertOk()
            ->assertSeeInOrder(['Club des abonnés', 'Mini-dialogue de situation', 'Télécharger pour WhatsApp', 'Votre mini-dialogue du jour.', 'Copier le texte', 'Vidéos de promotion par jour']);

        $assistant = User::factory()->create();
        $assistant->forceFill(['role' => Role::Assistant])->save();
        $this->actingAs($assistant)->get(route('admin.videos.index'))->assertOk()
            ->assertSee('Club des abonnés')->assertSee('Télécharger pour WhatsApp')
            ->assertDontSee('Vidéos de promotion par jour')->assertDontSee('Essai comparatif des voix')->assertDontSee($promo->title);
        $this->assertNotSame(403, $this->actingAs($assistant)->get(route('admin.videos.file', ['video' => $club, 'telecharger' => 1]))->status());
        $this->actingAs($assistant)->get(route('admin.videos.file', $promo))->assertForbidden();
        $this->actingAs($assistant)->put(route('admin.videos.publish', $club), ['published' => 1])->assertRedirect(route('admin.videos.index').'#club-'.$club->id);
        $this->assertNotNull($club->fresh()->published_at);
        $this->actingAs($assistant)->put(route('admin.videos.publish', $promo), ['published' => 1])->assertForbidden();
        $this->actingAs($assistant)->post(route('admin.videos.club.prepare'))->assertForbidden();
        $this->actingAs($assistant)->post(route('admin.videos.prepare'), ['count' => 1])->assertForbidden();
    }

    public function test_the_privacy_page_explains_the_club_videos(): void
    {
        $this->get(route('privacy'))->assertOk()
            ->assertSee('Préparer les vidéos du Club des abonnés')
            ->assertSee('seulement si au moins trois personnes différentes les ont faites')
            ->assertSee('chiffres globaux');
    }

    public function test_every_english_sentence_is_translated_explained_and_repeated_with_a_pause(): void
    {
        $brief = ['theme' => 'expression', 'data' => 'x', 'figures' => null];
        $script = ClubScriptWriter::normalize($this->script(), $this->words(), $brief);

        $this->assertNotNull($script);
        $lesson = $script['segments'][2];
        $this->assertStringContainsString("How's it going? En français : Comment ça va ? On l'utilise avec des amis", $lesson['spoken']);
        $this->assertStringEndsWith("Répétez après moi : How's it going?", $lesson['spoken']);
        $this->assertStringContainsString("[en]How's it going?[/en] En français : Comment ça va ?", $lesson['subtitle']);
        $this->assertSame(config('videos.club.repeat_pause_ms'), $lesson['pause_after']);
        // Une partie en français seulement : ni traduction ni pause.
        $this->assertArrayNotHasKey('pause_after', $script['segments'][0]);
        $this->assertStringNotContainsString('En français', $script['segments'][0]['spoken']);

        // Phrase anglaise sans traduction, traduction recopiée en anglais, sans explication : script refusé.
        $this->assertNull(ClubScriptWriter::normalize($this->script(override: ['translation' => '']), $this->words(), $brief));
        $this->assertNull(ClubScriptWriter::normalize($this->script(override: ['translation' => "How's it going?"]), $this->words(), $brief));
        $this->assertNull(ClubScriptWriter::normalize($this->script(override: ['explain' => '']), $this->words(), $brief));
        // Rien à répéter à voix haute : refusé.
        $this->assertNull(ClubScriptWriter::normalize($this->script(override: ['repeat' => false]), $this->words(), $brief));
    }

    public function test_a_club_script_shorter_than_three_minutes_is_refused(): void
    {
        $brief = ['theme' => 'expression', 'data' => 'x', 'figures' => null];
        $this->assertSame(180, config('videos.club.min_seconds'));
        $this->assertSame(180, config('videos.club.duration_seconds'));
        $this->assertNotNull(ClubScriptWriter::normalize($this->script(), $this->words(), $brief));
        // Trop peu de mots (moins de 3 minutes, même avec des silences plus longs) ou beaucoup trop : refusé.
        $this->assertNull(ClubScriptWriter::normalize($this->script(override: ['explain' => 'Pour saluer.']), $this->words(), $brief));
        $this->assertNull(ClubScriptWriter::normalize($this->script('On prend le temps, doucement, pour bien entendre chaque mot de cette phrase.'), $this->words(), $brief));
        $this->assertStringContainsString('NEVER fewer than :min_words nor more than :max_words', ClubScriptWriter::INSTRUCTIONS);
        $this->assertStringContainsString('EVERY English sentence', ClubScriptWriter::INSTRUCTIONS);
    }

    public function test_a_club_voice_shorter_than_three_minutes_gets_a_new_script(): void
    {
        Carbon::setTestNow('2026-10-27 08:00');
        $this->member();
        $this->pieceSeconds = 5; // 12 parties de 5 secondes : une minute
        app(ClubVideos::class)->ensureToday();
        $this->prepareAll();

        $video = LessonVideo::query()->club()->firstOrFail();
        $this->assertSame(LessonVideo::PLANNED, $video->status);
        $this->assertNull($video->audio_path);
        $this->assertStringContainsString('Voix trop courte', (string) $video->error);

        $this->pieceSeconds = 16;
        $this->prepareAll();
        $video->refresh();
        $this->assertSame(LessonVideo::VOICED, $video->status);
        $this->assertGreaterThanOrEqual(180, $video->timings['duration']);
    }

    public function test_the_club_has_its_own_lighter_github_pass_and_a_slow_machine_gives_the_video_back(): void
    {
        Carbon::setTestNow('2026-10-27 08:00');
        $this->member();
        app(ClubVideos::class)->ensureToday();
        $this->prepareAll();
        app(LessonVideos::class)->plan(today(), 1);
        $promo = LessonVideo::query()->promo()->firstOrFail();
        $promo->update(['status' => LessonVideo::VOICED, 'audio_path' => 'x.wav', 'script' => $promo->script ?? ['segments' => []]]);

        $this->withToken(self::TOKEN)->getJson('/api/videos/travaux?apercu=1&type=club')->assertJsonPath('waiting', 1);
        $this->withToken(self::TOKEN)->getJson('/api/videos/travaux?apercu=1&type=promo')->assertJsonPath('waiting', 1);

        $job = $this->withToken(self::TOKEN)->getJson('/api/videos/travaux?limit=1&type=club')->assertOk()->json('jobs.0');
        $club = LessonVideo::query()->club()->firstOrFail();
        $this->assertSame($club->id, $job['id']);
        $this->assertSame(396, $job['render']['avatar_width']);
        $this->assertSame(20, $job['render']['fps']);
        $this->assertSame(-14, $job['render']['audio']['target_lufs']);
        $this->assertSame(-1, $job['render']['audio']['max_peak_db']);

        // Machine trop lente : la vidéo est rendue sans échec compté, reprise au passage suivant.
        $this->withToken(self::TOKEN)->postJson($job['release_url'])->assertOk();
        $club->refresh();
        $this->assertSame(LessonVideo::VOICED, $club->status);
        $this->assertSame(0, $club->attempts);
        $this->assertStringContainsString('trop lente', (string) $club->error);

        // Le passage des vidéos de promotion ne prend jamais la vidéo du Club.
        $this->assertNotContains($club->id, array_column($this->withToken(self::TOKEN)->getJson('/api/videos/travaux?limit=1&type=promo')->json('jobs'), 'id'));
        $this->assertSame(LessonVideo::VOICED, $club->fresh()->status);
    }

    public function test_redoing_a_club_video_writes_a_new_script(): void
    {
        Carbon::setTestNow('2026-10-27 08:00');
        $this->member();
        $video = app(ClubVideos::class)->ensureToday();
        $video->update(['status' => LessonVideo::READY, 'script' => ['segments' => []], 'audio_path' => 'videos/x/voice.wav', 'video_path' => 'videos/x/club.mp4']);

        $this->assertTrue(app(LessonVideos::class)->redo($video));
        $video->refresh();
        $this->assertSame(LessonVideo::PLANNED, $video->status);
        $this->assertNull($video->script);
        $this->assertNull($video->audio_path);
        $this->assertSame('videos/x/club.mp4', $video->video_path);
    }

    public function test_a_slightly_short_club_voice_gets_longer_repeat_silences_and_lasts_three_minutes(): void
    {
        Carbon::setTestNow('2026-10-27 08:00');
        $this->member();
        // 9 parties de 16 secondes, avec les silences : un peu moins de 3 minutes.
        app(ClubVideos::class)->ensureToday();
        $this->prepareAll();

        $video = LessonVideo::query()->club()->firstOrFail();
        $this->assertSame(LessonVideo::VOICED, $video->status);
        $this->assertGreaterThanOrEqual(180, $video->timings['duration']);
        $this->assertLessThan(182, $video->timings['duration']);
        // Le silence ajouté va aux moments de répétition : une partie à répéter dure plus que sa voix.
        $repeat = $video->timings['segments'][2];
        $this->assertGreaterThan(16 + 2.5, $repeat['end'] - $repeat['start']);
    }
}
