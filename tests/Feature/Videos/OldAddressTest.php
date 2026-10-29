<?php

namespace Tests\Feature\Videos;

use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Models\Setting;
use App\Models\User;
use App\Support\Teachers\Teachers;
use App\Support\Videos\OldAddress;
use App\Support\Videos\VideoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ancienne adresse du site (talkami.laravel.cloud) : jamais plus dite ni affichée par une NOUVELLE vidéo, texte à
 * copier corrigé, vidéos déjà préparées qui la portent signalées « à refaire » (jamais refaites toutes seules), nom du
 * professeur sur chaque carte des vidéos de présentation.
 */
class OldAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://talkami.net', 'app.redirect_from_hosts' => ['talkami.laravel.cloud']]);
    }

    public function test_the_old_address_saved_in_the_settings_is_read_as_the_official_one(): void
    {
        Setting::put(VideoSettings::SITE, 'talkami.laravel.cloud');

        $this->assertSame('talkami.net', VideoSettings::site());
        $this->assertSame(['talkami.laravel.cloud'], OldAddress::hosts());
    }

    public function test_the_old_address_cannot_be_saved_again_in_the_videos_page(): void
    {
        VideoSettings::saveSite('talkami.net');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.videos.site'), ['site' => 'https://talkami.laravel.cloud/'])
            ->assertSessionHasErrors('site');

        $this->assertSame('talkami.net', Setting::get(VideoSettings::SITE));
    }

    public function test_the_migration_fixes_the_setting_and_the_captions_but_never_the_scripts(): void
    {
        Setting::put(VideoSettings::SITE, 'https://Talkami.Laravel.Cloud/');
        $script = ['segments' => [['subtitle' => 'Rendez-vous sur talkami.laravel.cloud', 'spoken' => 'sur talkami point laravel point cloud']]];
        $presentation = PresentationVideo::create(['key' => 'rose-talkami', 'title' => 'Essai', 'status' => LessonVideo::READY, 'script' => $script, 'caption' => 'Évaluez votre niveau sur talkami.laravel.cloud #anglais']);
        $lesson = LessonVideo::create(['kind' => LessonVideo::PROMO, 'topic_key' => 'essai', 'day' => today(), 'slot' => 1, 'level' => 'A1', 'title' => 'Leçon', 'status' => LessonVideo::READY, 'script' => $script, 'caption' => 'Sur https://talkami.laravel.cloud']);

        (require database_path('migrations/2026_11_21_000001_replace_old_address_in_videos.php'))->up();

        $this->assertSame('talkami.net', Setting::get(VideoSettings::SITE));
        $this->assertSame('Évaluez votre niveau sur talkami.net #anglais', $presentation->fresh()->caption);
        $this->assertSame('Sur https://talkami.net', $lesson->fresh()->caption);
        // Sous-titres et voix gardés : la vidéo n'est pas refaite, elle est signalée.
        $this->assertSame($script, $presentation->fresh()->script);
        $this->assertTrue($presentation->fresh()->showsOldAddress());
        $this->assertTrue($lesson->fresh()->showsOldAddress());
    }

    public function test_the_videos_page_flags_the_old_address_and_names_the_teacher_of_each_presentation(): void
    {
        VideoSettings::saveSite('talkami.net');
        PresentationVideo::create(['key' => 'rose-talkami', 'title' => 'Talkami en 5 minutes', 'status' => LessonVideo::READY, 'script' => ['segments' => [['subtitle' => 'Sur talkami.laravel.cloud']]], 'caption' => 'Texte']);
        PresentationVideo::create(['key' => 'talkami', 'title' => 'Talkami en 5 minutes', 'status' => LessonVideo::READY, 'script' => ['segments' => [['subtitle' => 'Sur talkami.net']]], 'caption' => 'Texte']);
        $admin = User::factory()->admin()->create();

        $page = $this->actingAs($admin)->get(route('admin.videos.index'))->assertOk();

        $html = $page->getContent();
        $rose = substr($html, strpos($html, 'id="presentation-rose-talkami"'), 3000);
        $joe = substr($html, strpos($html, 'id="presentation-talkami"'), 3000);
        $this->assertStringContainsString(Teachers::find('rose')->name(), $rose);
        $this->assertStringContainsString('Cette vidéo dit et affiche talkami.laravel.cloud', $rose);
        $this->assertStringContainsString(config('avatar.model.name'), $joe);
        $this->assertStringNotContainsString('Cette vidéo dit et affiche', substr($joe, 0, strpos($joe, '</article>')));
    }

    public function test_the_read_only_command_lists_the_videos_to_redo(): void
    {
        PresentationVideo::create(['key' => 'rose-paiement', 'title' => 'Payer', 'status' => LessonVideo::READY, 'script' => ['segments' => [['spoken' => 'talkami point laravel point cloud']]], 'caption' => 'Texte']);

        $this->artisan('videos:old-address')
            ->expectsOutputToContain('rose-paiement')
            ->expectsOutputToContain('1 vidéo(s) à refaire pour montrer talkami.net')
            ->assertSuccessful();
    }
}
