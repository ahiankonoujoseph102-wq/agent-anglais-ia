<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\LearnerReset;
use App\Support\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;
use Tests\Concerns\BuildsPrograms;
use Tests\Concerns\HasCourses;
use Tests\TestCase;

/**
 * Remise à zéro des apprenants (app:reset-learners) et retour en arrière (app:restore-learners).
 */
class ResetLearnersTest extends TestCase
{
    use BuildsPrograms, HasCourses, RefreshDatabase;

    private User $ama;

    private User $kofi;

    private User $admin;

    /** Première page de la leçon « Mes cours » d'Ama (identifiants différents d'une base à l'autre). */
    private string $page;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['filesystems.private_disk' => 'local']);

        // Ama : évaluation, programme, séance, conversation, « Mes cours », attestation, ancien abonnement terminé.
        $this->ama = User::factory()->level(Level::A2)->create(['name' => 'Ama']);
        $this->assessed($this->ama, Level::A2);
        $program = $this->buildProgram($this->ama);
        $lesson = $this->ama->lessons()->create(['program_id' => $program->id, 'date' => today(), 'allowed_seconds' => 900, 'used_seconds' => 600, 'status' => LessonStatus::Completed, 'started_at' => now()->subHour(), 'ended_at' => now()->subMinutes(50)]);
        DB::table('conversation_lines')->insert(['user_id' => $this->ama->id, 'conversation_type' => 'lesson', 'conversation_id' => $lesson->id, 'position' => 1, 'speaker' => 'teacher', 'text' => 'Hello!', 'spoken_at' => now()->subHour(), 'created_at' => now(), 'updated_at' => now()]);
        $this->page = "courses/{$this->ama->id}/{$this->storedLesson($this->ama)->id}/page-1.jpg";
        Storage::disk('local')->assertExists($this->page);
        $attestation = $this->ama->attestations()->create(['level' => Level::A1, 'code' => 'CODE123', 'issued_at' => now()->subWeek()]);
        Storage::disk('local')->put("attestations/{$attestation->code}.pdf", 'pdf');
        Subscription::factory()->for($this->ama)->expired()->create(['status' => SubscriptionStatus::Expired]);
        Storage::disk('local')->put('course-reviews/'.$this->ama->id.'/1-abc.pdf', 'document');

        // Kofi : accès offert en cours (démonstration), évaluation.
        $this->kofi = User::factory()->level(Level::B1)->create(['name' => 'Kofi']);
        $this->assessed($this->kofi, Level::B1);
        Subscription::factory()->for($this->kofi)->create(['granted_days' => 7, 'price' => 0]);

        // Administrateur : jamais touché.
        $this->admin = User::factory()->admin()->level(Level::B2)->create();
        $this->assessed($this->admin, Level::B2);

        Storage::disk('local')->put('welcome-messages/openings/gemini-Achird-abc.wav', 'bonjour');
        Storage::disk('local')->put('welcome-messages/gemini-Achird-commun.wav', 'commun');
    }

    private function assessed(User $user, Level $level): void
    {
        $user->assessment()->create(['status' => AssessmentStatus::Completed, 'started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addMinutes(5), 'allowed_seconds' => 300, 'used_seconds' => 300, 'level' => $level]);
    }

    private function payment(User $user, PaymentStatus $status = PaymentStatus::Pending): Payment
    {
        $plan = Plan::find('essentiel');
        $payment = $user->payments()->create([
            'plan' => $plan->key, 'plan_name' => $plan->name, 'daily_minutes' => $plan->dailyMinutes, 'duration_days' => $plan->durationDays,
            'amount' => $plan->price, 'currency' => 'FCFA', 'country' => 'TG', 'operator' => 'flooz', 'operator_name' => 'Flooz',
            'recipient_number' => '+228 90 00 00 22', 'proof_path' => 'payment-proofs/'.$user->id.'.png',
        ]);
        $payment->forceFill(['status' => $status])->save();

        return $payment;
    }

    private function database(): string
    {
        return (string) DB::connection()->getDatabaseName();
    }

    private function reset(array $options = []): PendingCommand
    {
        return $this->artisan('app:reset-learners', $options + ['--database' => $this->database(), '--force' => true, '--confirm' => 'EFFACER 2 COMPTES']);
    }

    public function test_by_default_it_is_only_a_simulation(): void
    {
        $this->artisan('app:reset-learners')
            ->expectsOutputToContain("Comptes d'apprenants : 2 (les administrateurs ne sont jamais touchés).")
            ->expectsOutputToContain('assessments : 2')
            ->expectsOutputToContain('Accès offerts en cours gardés : 1')
            ->expectsOutputToContain('Simulation : rien n\'a été effacé.')
            ->expectsOutputToContain('--confirm="EFFACER 2 COMPTES"')
            ->assertSuccessful();

        $this->assertSame(3, DB::table('assessments')->count());
        $this->assertSame(Level::A2, $this->ama->fresh()->level);
        Storage::disk('local')->assertMissing(LearnerReset::BACKUP_DIRECTORY);
    }

    public function test_it_refuses_without_the_exact_database_and_confirmation(): void
    {
        $this->reset(['--database' => 'autre_base'])->expectsOutputToContain('--database ne correspond pas')->assertFailed();
        $this->reset(['--confirm' => 'EFFACER'])->expectsOutputToContain('écrivez --confirm="EFFACER 2 COMPTES"')->assertFailed();
        $this->reset(['--confirm' => null])->assertFailed();

        $this->assertSame(3, DB::table('assessments')->count());
    }

    public function test_it_refuses_when_a_learner_has_paid_or_a_provisional_access_or_a_pending_proof(): void
    {
        $cases = [
            'paiement validé' => fn () => $this->payment($this->ama, PaymentStatus::Validated),
            'abonnement payé en cours' => fn () => Subscription::factory()->for($this->ama)->create(),
            'accès provisoire' => fn () => Subscription::factory()->for($this->ama)->create(['status' => SubscriptionStatus::Provisional, 'provisional_until' => now()->addHours(5)]),
            'capture en attente' => fn () => $this->payment($this->ama),
        ];

        foreach ($cases as $case => $create) {
            $created = $create();
            $this->reset()->assertFailed();
            $this->assertSame(3, DB::table('assessments')->count(), $case);
            $created->delete();
        }

        $this->reset()->assertSuccessful();
    }

    public function test_the_reset_erases_the_learning_data_and_keeps_accounts_payments_and_granted_access(): void
    {
        $refused = $this->payment($this->ama, PaymentStatus::Rejected);

        $this->reset()->expectsOutputToContain('Remise à zéro faite')->expectsOutputToContain('app:restore-learners backups/remise-a-zero-')->assertSuccessful();

        foreach (['programs', 'program_lessons', 'lessons', 'conversation_lines', 'course_lessons', 'course_pages', 'attestations'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }

        // L'administrateur garde tout.
        $this->assertSame([$this->admin->id], DB::table('assessments')->pluck('user_id')->all());
        $this->assertSame(Level::B2, $this->admin->fresh()->level);

        $this->assertNull($this->ama->fresh()->level);
        $this->assertNull($this->ama->fresh()->welcomed_at);
        $this->assertSame(3, User::count(), 'aucun compte effacé');
        $this->assertTrue($refused->fresh()->exists, 'paiements gardés');
        $this->assertSame(1, Subscription::query()->granted()->count(), 'accès offert en cours gardé');
        $this->assertSame(0, Subscription::query()->where('user_id', $this->ama->id)->count(), 'abonnement terminé effacé');

        Storage::disk('local')->assertMissing($this->page);
        Storage::disk('local')->assertMissing('attestations/CODE123.pdf');
        Storage::disk('local')->assertMissing('welcome-messages/openings/gemini-Achird-abc.wav');
        Storage::disk('local')->assertExists('welcome-messages/gemini-Achird-commun.wav');

        // Sauvegarde : tout ce qui a été effacé, fichiers compris.
        $backup = Storage::disk('local')->files(LearnerReset::BACKUP_DIRECTORY)[0];
        $this->assertMatchesRegularExpression('#^backups/remise-a-zero-\d{4}-\d{2}-\d{2}-\d{4}\.json\.gz$#', $backup);
        $data = json_decode(gzdecode(Storage::disk('local')->get($backup)), true);
        $this->assertCount(2, $data['tables']['assessments']);
        Storage::disk('local')->assertExists(substr($backup, 0, -8).'/attestations/CODE123.pdf');
    }

    public function test_granted_accesses_can_be_removed_too(): void
    {
        $this->reset(['--include-granted' => true])->assertSuccessful();

        $this->assertSame(0, Subscription::query()->granted()->count());
    }

    public function test_the_backup_can_be_restored_once(): void
    {
        $lines = fn () => array_map(fn ($table) => DB::table($table)->count(), LearnerReset::TABLES);
        $refused = $this->payment($this->ama, PaymentStatus::Rejected);
        $cancelled = Subscription::factory()->for($this->ama)->expired()->create(['status' => SubscriptionStatus::Cancelled]);
        $refused->forceFill(['subscription_id' => $cancelled->id])->save();
        $before = $lines();

        $this->reset()->assertSuccessful();
        $backup = Storage::disk('local')->files(LearnerReset::BACKUP_DIRECTORY)[0];
        $this->assertNull($refused->fresh()->subscription_id);

        $this->artisan('app:restore-learners', ['file' => $backup])->expectsOutputToContain('Sauvegarde remise en place')->assertSuccessful();

        $this->assertSame($before, $lines());
        $this->assertSame(Level::A2, $this->ama->fresh()->level);
        $this->assertSame($cancelled->id, $refused->fresh()->subscription_id);
        Storage::disk('local')->assertExists($this->page);
        Storage::disk('local')->assertExists('attestations/CODE123.pdf');

        // Une seconde fois : refusé, rien d'écrasé.
        $this->artisan('app:restore-learners', ['file' => $backup])->expectsOutputToContain('existent déjà')->assertFailed();
        $this->assertSame($before, $lines());
        $this->artisan('app:restore-learners', ['file' => 'backups/inconnue.json.gz'])->assertFailed();
    }
}
