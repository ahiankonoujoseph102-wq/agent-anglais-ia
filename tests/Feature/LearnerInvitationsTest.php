<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Mail\LearnerInvitationMail;
use App\Models\LearnerInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Invitations à refaire l'évaluation gratuite après une remise à zéro (app:reset-learners --notify), envoyées par
 * lots (learners:send-invitations) dans la marge d'e-mails du jour des documents de révision.
 */
class LearnerInvitationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        config([
            'filesystems.private_disk' => 'local',
            'mail.default' => 'smtp',
            'mail.from.address' => 'expediteur@example.test',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.username' => 'utilisateur-smtp',
            'mail.mailers.smtp.password' => 'MOT-DE-PASSE-SMTP-SECRET',
        ]);
    }

    private function learner(string $name, ?string $email): User
    {
        $user = User::factory()->level(Level::A2)->create(['name' => $name, 'email' => $email]);
        $user->assessment()->create(['status' => AssessmentStatus::Completed, 'started_at' => now()->subDay(), 'ended_at' => now()->subDay(), 'allowed_seconds' => 300, 'used_seconds' => 300, 'level' => Level::A2]);

        return $user;
    }

    private function resetAndNotify(int $accounts): void
    {
        $this->artisan('app:reset-learners', ['--database' => DB::connection()->getDatabaseName(), '--force' => true, '--confirm' => "EFFACER {$accounts} COMPTES", '--notify' => true])
            ->expectsOutputToContain('Invitations par e-mail')
            ->assertSuccessful();
    }

    public function test_the_reset_queues_one_invitation_per_learner_and_never_the_administrator(): void
    {
        $afi = $this->learner('Afi Mensah', 'afi@example.test');
        $yao = $this->learner('Yao', null);
        $admin = User::factory()->admin()->create(['email' => 'admin@example.test']);

        $this->resetAndNotify(2);
        Mail::assertNothingSent();

        $this->assertSame(LearnerInvitation::PENDING, LearnerInvitation::query()->where('user_id', $afi->id)->value('status'));
        $this->assertSame(LearnerInvitation::NO_EMAIL, LearnerInvitation::query()->where('user_id', $yao->id)->value('status'));
        $this->assertFalse(LearnerInvitation::query()->where('user_id', $admin->id)->exists());

        $this->artisan('learners:send-invitations')->expectsOutputToContain('Invitations envoyées : 1')->assertSuccessful();

        Mail::assertSent(LearnerInvitationMail::class, fn (LearnerInvitationMail $mail) => $mail->hasTo('afi@example.test'));
        Mail::assertSentCount(1);
        $this->assertSame(LearnerInvitation::SENT, LearnerInvitation::query()->where('user_id', $afi->id)->value('status'));
    }

    public function test_the_email_says_the_validated_text_with_the_names_from_the_configuration(): void
    {
        $mail = new LearnerInvitationMail(User::factory()->make(['name' => 'Afi Mensah']));

        $mail->assertHasSubject('Teacher Joe a été amélioré : refaites votre évaluation gratuite');
        $mail->assertSeeInHtml('Bonjour Afi,', false);
        $mail->assertSeeInHtml('Vous avez déjà rencontré Teacher Joe, votre professeur d\'anglais sur Talkami. Depuis, nous l\'avons beaucoup amélioré : il parle plus lentement et plus clairement, il s\'adapte à votre niveau et il répond plus vite.', false);
        $mail->assertSeeInHtml('Nous vous invitons à refaire votre évaluation orale gratuite. En quelques minutes, Teacher Joe vous écoute, vous donne votre niveau et vous montre une première phrase.', false);
        $mail->assertSeeInHtml('Votre compte est toujours là : connectez-vous avec votre numéro de téléphone et votre mot de passe habituels.', false);
        $mail->assertSeeInHtml('href="'.route('dashboard').'"', false);
        $mail->assertSeeInHtml('Refaire mon évaluation gratuite');
        $mail->assertSeeInHtml('L\'équipe Talkami', false);
        $mail->assertSeeInHtml('Une question ? Écrivez-nous sur WhatsApp', false);
        $mail->assertDontSeeInHtml('Gemini');
        $mail->assertDontSeeInHtml('OpenAI');
    }

    public function test_invitations_go_out_in_batches_within_the_daily_margin_and_never_take_the_alerts_emails(): void
    {
        config(['platform.courses.review.daily_emails' => 2, 'mail.daily_limit' => 4]);

        foreach (['Ama', 'Kossi', 'Efua'] as $name) {
            $this->learner($name, strtolower($name).'@example.test');
        }

        $this->resetAndNotify(3);

        $this->artisan('learners:send-invitations')->expectsOutputToContain('la suite partira les jours suivants')->assertSuccessful();
        Mail::assertSentCount(2);
        $this->artisan('learners:send-invitations')->assertSuccessful();
        Mail::assertSentCount(2);

        // Le lendemain : la suite.
        $this->travel(1)->days();
        $this->artisan('learners:send-invitations')->expectsOutputToContain('encore en attente : 0')->assertSuccessful();
        Mail::assertSentCount(3);
    }

    public function test_without_email_settings_nothing_is_sent_and_the_queue_waits(): void
    {
        config(['mail.mailers.smtp.password' => null]);
        $this->learner('Ama', 'ama@example.test');
        $this->resetAndNotify(1);

        $this->artisan('learners:send-invitations')->expectsOutputToContain('Envoi d\'e-mails non réglé')->assertSuccessful();
        Mail::assertNothingSent();
        $this->assertSame(1, LearnerInvitation::query()->where('status', LearnerInvitation::PENDING)->count());
    }

    public function test_the_alerts_page_shows_sent_remaining_and_failed_invitations(): void
    {
        $this->learner('Ama', 'ama@example.test');
        $this->learner('Yao', null);
        $this->resetAndNotify(2);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.alerts.index'))->assertOk()
            ->assertSeeText('Invitations à refaire l\'évaluation')
            ->assertSeeText('restantes : 1')
            ->assertSeeText('sans adresse e-mail : 1');
    }

    public function test_a_second_reset_never_sends_an_invitation_twice(): void
    {
        $afi = $this->learner('Afi Mensah', 'afi@example.test');
        $this->resetAndNotify(1);
        $this->artisan('learners:send-invitations')->assertSuccessful();
        Mail::assertSentCount(1);

        // Afi refait son évaluation, puis une nouvelle remise à zéro avec --notify : l'invitation envoyée reste envoyée.
        $afi->assessment()->create(['status' => AssessmentStatus::Completed, 'started_at' => now(), 'ended_at' => now(), 'allowed_seconds' => 300, 'used_seconds' => 300, 'level' => Level::A2]);
        $this->artisan('app:reset-learners', ['--database' => DB::connection()->getDatabaseName(), '--force' => true, '--confirm' => 'EFFACER 1 COMPTES', '--notify' => true])
            ->expectsOutputToContain('1 déjà envoyée(s) (jamais en double)')
            ->assertSuccessful();

        $this->assertSame(LearnerInvitation::SENT, LearnerInvitation::query()->where('user_id', $afi->id)->value('status'));
        $this->artisan('learners:send-invitations')->assertSuccessful();
        Mail::assertSentCount(1);
    }

    public function test_invitations_can_be_queued_alone_from_a_backup_without_erasing_anything(): void
    {
        $afi = $this->learner('Afi Mensah', 'afi@example.test');
        $yao = $this->learner('Yao', null);
        $efua = $this->learner('Efua', 'efua@example.test');
        $kossi = $this->learner('Kossi', 'kossi@example.test');

        // Remise à zéro sans --notify.
        $this->artisan('app:reset-learners', ['--database' => DB::connection()->getDatabaseName(), '--force' => true, '--confirm' => 'EFFACER 4 COMPTES'])->assertSuccessful();
        $backup = Storage::disk('local')->files('backups')[0];
        $this->assertSame(0, LearnerInvitation::count());

        // Efua a déjà refait son évaluation ; Kossi a déjà été invité.
        $efua->assessment()->create(['status' => AssessmentStatus::Completed, 'started_at' => now(), 'ended_at' => now(), 'allowed_seconds' => 300, 'used_seconds' => 300, 'level' => Level::B1]);
        LearnerInvitation::query()->create(['user_id' => $kossi->id, 'status' => LearnerInvitation::SENT, 'sent_at' => now()]);
        $lines = DB::table('assessments')->count();

        $this->artisan('learners:queue-invitations', ['file' => $backup])
            ->expectsOutputToContain('Déjà envoyées (jamais en double) : 1 ; déjà en file ou notées sans adresse : 0 ; évaluation déjà refaite : 1.')
            ->expectsOutputToContain('À mettre en file : 2 (1 avec une adresse e-mail, 1 sans adresse')
            ->expectsOutputToContain('Simulation : rien n\'a été mis en file.')
            ->expectsOutputToContain("learners:queue-invitations {$backup} --force")
            ->assertSuccessful();
        $this->assertSame(1, LearnerInvitation::count());

        $this->artisan('learners:queue-invitations', ['file' => $backup, '--force' => true])->expectsOutputToContain('1 en file d\'attente, 1 sans adresse e-mail. Rien n\'a été effacé.')->assertSuccessful();

        $this->assertSame(LearnerInvitation::PENDING, LearnerInvitation::query()->where('user_id', $afi->id)->value('status'));
        $this->assertSame(LearnerInvitation::NO_EMAIL, LearnerInvitation::query()->where('user_id', $yao->id)->value('status'));
        $this->assertFalse(LearnerInvitation::query()->where('user_id', $efua->id)->exists());
        $this->assertSame(LearnerInvitation::SENT, LearnerInvitation::query()->where('user_id', $kossi->id)->value('status'));
        $this->assertSame($lines, DB::table('assessments')->count(), 'rien d\'effacé');

        // Relancée : plus rien à mettre en file.
        $this->artisan('learners:queue-invitations', ['file' => $backup, '--force' => true])->expectsOutputToContain('À mettre en file : 0')->assertSuccessful();
        $this->artisan('learners:queue-invitations', ['file' => 'backups/inconnue.json.gz'])->assertFailed();
    }
}
