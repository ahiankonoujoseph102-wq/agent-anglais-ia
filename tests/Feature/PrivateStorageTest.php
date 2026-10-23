<?php

namespace Tests\Feature;

use App\Enums\Level;
use App\Models\Attestation;
use App\Models\Payment;
use App\Models\User;
use App\Support\PrivateStorage;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Disque privé des fichiers (captures de paiement, attestations) : réglé par PRIVATE_FILES_DISK.
 */
class PrivateStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_setting_the_default_disk_is_used(): void
    {
        config(['filesystems.private_disk' => null, 'filesystems.default' => 'local']);

        $this->assertSame('local', PrivateStorage::name());
    }

    public function test_the_disk_comes_from_the_environment_setting(): void
    {
        config(['filesystems.private_disk' => 'bucket', 'filesystems.default' => 'local']);

        $this->assertSame('bucket', PrivateStorage::name());

        // Sur Laravel Cloud, le bucket est attaché comme disque par défaut : aucun réglage n'est nécessaire.
        config(['filesystems.private_disk' => null, 'filesystems.default' => 'bucket']);

        $this->assertSame('bucket', PrivateStorage::name());
    }

    public function test_a_public_disk_is_refused(): void
    {
        config(['filesystems.private_disk' => 'public']);

        $this->expectException(RuntimeException::class);
        PrivateStorage::disk();
    }

    public function test_a_disk_with_public_visibility_is_refused(): void
    {
        config(['filesystems.disks.ouvert' => ['driver' => 'local', 'root' => storage_path('app/ouvert'), 'visibility' => 'public']]);
        config(['filesystems.private_disk' => 'ouvert']);

        $this->expectException(RuntimeException::class);
        PrivateStorage::disk();
    }

    public function test_the_local_disk_is_not_served_by_a_public_route(): void
    {
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->assertFalse(Route::has('storage.local'));
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.local.root'));
        $this->assertNotSame('public', config('filesystems.default'));
    }

    public function test_payment_proofs_are_stored_on_the_configured_private_disk_only(): void
    {
        config(['filesystems.private_disk' => 'bucket', 'filesystems.default' => 'local']);
        Storage::fake('bucket');
        // Numéro fictif ; lecture de la capture impossible (aucune clé) : le paiement attend la vérification.
        SiteSettings::save([SiteSettings::operatorNumberKey('TG', 'flooz') => '+228 90 00 00 22']);
        config(['services.gemini.key' => null]);

        $this->actingAs(User::factory()->create())->post('/abonnement/essentiel', [
            'country' => 'TG',
            'operator' => 'flooz',
            'proof' => UploadedFile::fake()->image('capture.png', 600, 1200),
        ])->assertRedirect();

        $payment = Payment::query()->sole();

        Storage::disk('bucket')->assertExists($payment->proof_path);
        Storage::disk('local')->assertMissing($payment->proof_path);
        Storage::disk('public')->assertMissing($payment->proof_path);
    }

    public function test_the_attestation_is_written_once_on_the_private_disk_and_served_unchanged(): void
    {
        config(['filesystems.private_disk' => 'bucket']);
        Storage::fake('bucket');

        $user = User::factory()->create(['name' => 'Edem Kossi']);
        $attestation = new Attestation(['level' => Level::B1, 'code' => 'TA-B1-ABCD-EFGH', 'issued_at' => now()]);
        $attestation->user_id = $user->id;
        $attestation->save();

        $this->assertSame('attestations/TA-B1-ABCD-EFGH.pdf', $attestation->pdfPath());
        Storage::disk('bucket')->assertMissing($attestation->pdfPath());

        $response = $this->actingAs($user)->get(route('attestation.download', $attestation))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
        $first = $response->getContent();

        Storage::disk('bucket')->assertExists($attestation->pdfPath());
        Storage::disk('local')->assertMissing($attestation->pdfPath());

        // Le nom de la plateforme change plus tard : l'attestation déjà délivrée reste identique.
        config(['platform.name' => 'Autre nom']);

        $this->assertSame($first, $this->actingAs($user)->get(route('attestation.download', $attestation))->getContent());
        $this->assertStringStartsWith('%PDF-', $first);
    }
}
