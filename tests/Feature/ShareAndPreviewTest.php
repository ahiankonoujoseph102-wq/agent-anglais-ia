<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\Attestation;
use App\Models\LessonVideo;
use App\Models\PresentationVideo;
use App\Models\User;
use App\Support\CountryPage;
use App\Support\LightImage;
use App\Support\ShareLinks;
use App\Support\Videos\SiteVideos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Partages volontaires (niveau, attestation), aperçus de partage (pages pays, vérification d'une attestation),
 * réponse aux requêtes HEAD et aperçus allégés de « Comment ça marche ».
 */
class ShareAndPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://talkami.net', 'filesystems.private_disk' => 'local']);
        Storage::fake('local');
        Http::fake();
    }

    private function assessed(?Level $level): User
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
        ]);

        return $user;
    }

    private function attestation(Level $level = Level::B1): Attestation
    {
        $user = User::factory()->create(['name' => 'Akosua Mensah']);

        return $user->attestations()->create(['level' => $level, 'code' => 'TA-B1-ABCD-EFGH', 'issued_at' => now()->subDay()]);
    }

    public function test_the_learner_shares_the_level_really_found_only_by_touching_the_button(): void
    {
        $message = ShareLinks::levelMessage(Level::A2);
        $this->assertStringContainsString('mon niveau d\'anglais est A2 (élémentaire)', $message);
        $this->assertStringContainsString('https://talkami.net/?utm_source=whatsapp&utm_medium=partage&utm_campaign=niveau', $message);
        $this->assertStringContainsString('L\'évaluation est gratuite, 5 minutes à l\'oral', $message);

        // Le niveau choisi ensuite par l'élève ne change pas ce qui est partagé : celui de l'évaluation.
        $user = $this->assessed(Level::A2);
        $user->forceFill(['level' => Level::B2])->save();

        $this->actingAs($user)->get('/evaluation')->assertOk()
            ->assertSee('Partager mon niveau sur WhatsApp')
            ->assertSee('href="'.e(ShareLinks::levelUrl(Level::A2)).'"', false)
            ->assertSee('https://wa.me/?text=', false);

        $this->assertStringNotContainsString('wa.me/?text=', (string) $user->name);
    }

    public function test_no_level_no_share_button(): void
    {
        $this->actingAs($this->assessed(null))->get('/evaluation')->assertOk()->assertDontSee('Partager mon niveau');
    }

    public function test_the_holder_shares_the_attestation_with_its_verification_link(): void
    {
        $attestation = $this->attestation();
        $message = ShareLinks::attestationMessage($attestation);

        $this->assertStringContainsString('J\'ai validé le niveau B1 (intermédiaire) en anglais avec Talkami.', $message);
        $this->assertStringContainsString('https://talkami.net/attestation/verifier/TA-B1-ABCD-EFGH?utm_source=whatsapp', $message);

        $this->actingAs($attestation->user)->get('/espace')->assertOk()
            ->assertSee('Partager mon attestation')
            ->assertSee(e(ShareLinks::attestationUrl($attestation)), false);
    }

    public function test_the_verification_page_has_a_rich_preview_but_stays_out_of_google(): void
    {
        $attestation = $this->attestation();
        $html = $this->get('/attestation/verifier/'.$attestation->code)->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->getContent();

        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
        $this->assertStringNotContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('<meta property="og:image" content="https://talkami.net/images/partage/attestation-b1.jpg">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Attestation de niveau B1 en anglais · Talkami">', $html);
        $this->assertFileExists(public_path('images/partage/attestation-b1.jpg'));

        // Le nom du titulaire est sur la page, jamais dans l'aperçu.
        preg_match_all('/<meta (?:property|name)="(?:og|twitter):[^"]+" content="([^"]*)"/', $html, $previews);
        $this->assertStringNotContainsString('Akosua', implode(' ', $previews[1]));
        $this->assertStringContainsString('Akosua Mensah', $html);

        // Bouton de partage : le titulaire seulement.
        $this->assertStringNotContainsString('Partager mon attestation', $html);
        $this->actingAs($attestation->user)->get('/attestation/verifier/'.$attestation->code)->assertSee('Partager mon attestation');

        // Code inconnu : aucun aperçu.
        $this->assertStringNotContainsString('og:image', $this->get('/attestation/verifier/INCONNU')->getContent());
    }

    public function test_each_country_page_has_its_own_share_image(): void
    {
        foreach (CountryPage::slugs() as $slug) {
            $this->assertFileExists(public_path('images/partage/pays-'.$slug.'.jpg'));
            $this->get('/apprendre-anglais/'.$slug)
                ->assertSee('<meta property="og:image" content="https://talkami.net/images/partage/pays-'.$slug.'-duo.jpg">', false)
                ->assertSee('<meta name="twitter:image" content="https://talkami.net/images/partage/pays-'.$slug.'-duo.jpg">', false);
        }

        [$width, $height] = getimagesize(public_path('images/partage/pays-togo.jpg'));
        $this->assertSame([1200, 630], [$width, $height]);
        $this->get('/')->assertSee('content="https://talkami.net/images/partage-talkami-duo.jpg"', false);
    }

    public function test_a_head_request_reads_the_same_robots_rule_as_google(): void
    {
        foreach (['/', '/a-propos', '/apprendre-anglais/benin'] as $path) {
            $this->assertNull($this->call('HEAD', $path)->headers->get('X-Robots-Tag'), $path);
        }

        $this->call('HEAD', '/connexion')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_video_previews_are_served_lighter_in_webp(): void
    {
        $jpeg = $this->jpeg();
        $video = PresentationVideo::query()->create(['key' => 'evaluation', 'title' => 'Votre évaluation orale gratuite', 'status' => LessonVideo::READY, 'duration_seconds' => 95]);
        Storage::disk('local')->put(SiteVideos::path($video, 'video'), 'mp4');
        Storage::disk('local')->put(SiteVideos::path($video, 'apercu'), $jpeg);
        $this->assertTrue(app(SiteVideos::class)->markReady($video));

        $response = $this->get('/comment-ca-marche/evaluation/apercu')->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->assertLessThan(strlen($jpeg), strlen($response->streamedContent()));

        // Une seule conversion : la copie allégée est gardée.
        $this->assertCount(1, array_filter(Storage::disk('local')->allFiles(), fn ($file) => str_ends_with($file, '.webp')));
        $this->get('/comment-ca-marche/evaluation/apercu')->assertHeader('Content-Type', 'image/webp');
        $this->assertNull(LightImage::webp('pas une image'));
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(480, 854);
        for ($y = 0; $y < 854; $y += 8) {
            imagefilledrectangle($image, 0, $y, 479, $y + 7, imagecolorallocate($image, $y % 255, 120, 200 - $y % 200));
        }
        ob_start();
        imagejpeg($image, null, 92);

        return (string) ob_get_clean();
    }
}
