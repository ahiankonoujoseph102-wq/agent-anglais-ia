<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\UploadLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Limites d'envoi de « Mes cours » (PDF de 20 mégaoctets, lecture d'une minute) : affichées dans le
 * « Diagnostic des fournisseurs » et par app:check-deployment, et deux essais réels réservés aux administrateurs.
 */
class UploadCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_diagnostic_page_shows_the_limits_and_the_two_trials(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.text-diagnostic.index'))
            ->assertOk()
            ->assertSee('Limites d\'envoi (« Mes cours »)', false)
            ->assertSee('Taille d\'un fichier', false)
            ->assertSee('Taille d\'un envoi', false)
            ->assertSee('Durée d\'une page', false)
            ->assertSee('Essayer un envoi de 20 mégaoctets')
            ->assertSee('Essayer une attente de 60 secondes')
            ->assertSee(route('admin.upload-check.size'));
    }

    public function test_the_trials_answer_for_an_administrator_only(): void
    {
        config(['platform.courses.check_wait_seconds' => 0]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson(route('admin.upload-check.size'), ['essai' => UploadedFile::fake()->create('essai.pdf', 2048, 'application/pdf')])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('label', '2 mégaoctets');
        $this->actingAs($admin)->postJson(route('admin.upload-check.size'))->assertUnprocessable()->assertJsonPath('ok', false);
        $this->actingAs($admin)->postJson(route('admin.upload-check.wait'))->assertOk()->assertJsonPath('ok', true);

        $learner = User::factory()->create();
        $this->actingAs($learner)->postJson(route('admin.upload-check.size'))->assertForbidden();
        $this->actingAs($learner)->postJson(route('admin.upload-check.wait'))->assertForbidden();
    }

    public function test_server_values_are_read_and_compared_with_what_courses_need(): void
    {
        $this->assertSame(20 * 1024 * 1024, UploadLimits::bytes('20M'));
        $this->assertSame(512 * 1024, UploadLimits::bytes('512K'));
        $this->assertSame(1024 ** 3, UploadLimits::bytes('1G'));
        $this->assertSame(0, UploadLimits::bytes('0'));
        $this->assertSame('20 mégaoctets', UploadLimits::label(20 * 1024 * 1024));
        $this->assertSame('aucune limite', UploadLimits::label(0));
        $this->assertSame(90, UploadLimits::readingSeconds());

        $report = UploadLimits::report();
        $this->assertSame(20 * 1024 * 1024, $report['file']['needed']);
        $this->assertSame(21 * 1024 * 1024, $report['post']['needed']);

        $this->artisan('app:check-deployment')->expectsOutputToContain('Envoi de fichiers (« Mes cours »)');
    }
}
