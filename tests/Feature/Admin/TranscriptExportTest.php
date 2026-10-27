<?php

namespace Tests\Feature\Admin;

use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\Lesson;
use App\Models\User;
use App\Support\Live\TranscriptAnonymizer;
use App\Support\Live\Transcripts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Export anonymisé des conversations (« Consommation » → « Améliorer Teacher Joe ») : pour analyser les vraies
 * séances sans nom, numéro, e-mail ni lien ; administrateurs seulement ; comptes d'administrateur exclus.
 * Toutes les données ci-dessous sont inventées.
 */
class TranscriptExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $learner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-27 10:00:00', 'Africa/Lome'));
        $this->admin = User::factory()->admin()->create(['name' => 'Responsable Essai']);
        $this->learner = User::factory()->level(Level::A2)->create(['name' => 'Kossi Amégah', 'email' => 'kossi.essai@example.com']);
    }

    private function lesson(User $user, string $learnerSays): Lesson
    {
        $lesson = Lesson::query()->forceCreate([
            'user_id' => $user->id, 'date' => today(), 'started_at' => now()->subMinutes(15), 'ended_at' => now(),
            'allowed_seconds' => 900, 'used_seconds' => 852, 'status' => LessonStatus::Completed, 'outcome' => 'completed', 'live_provider' => 'gemini',
        ]);
        Transcripts::record($lesson, [
            ['seq' => 1, 'speaker' => 'teacher', 'text' => 'Good morning Kossi! Today: at the market.', 'at' => now()->subMinutes(14)->getTimestampMs()],
            ['seq' => 2, 'speaker' => 'learner', 'text' => $learnerSays, 'at' => now()->subMinutes(13)->getTimestampMs()],
            ['seq' => 3, 'speaker' => 'teacher', 'text' => 'Great! What do you buy?', 'at' => now()->subMinutes(12)->getTimestampMs(), 'metrics' => ['delay_ms' => 1400]],
        ]);

        return $lesson;
    }

    public function test_the_anonymizer_removes_names_numbers_emails_and_links(): void
    {
        $clean = TranscriptAnonymizer::clean(
            'Je suis KOSSI amegah, mon numéro est +228 90 12 34 56, écrivez à kossi.essai@example.com ou sur https://exemple.tg/page. J\'ai 25 ans et 3 enfants.',
            ['Kossi Amégah'],
        );

        $this->assertSame('Je suis [prénom] [prénom], mon numéro est [numéro], écrivez à [e-mail] ou sur [lien] J\'ai 25 ans et 3 enfants.', $clean);
        // Un mot qui contient le prénom n'est pas touché ; les petits nombres (âge, prix) restent.
        $this->assertSame('Kossivi paie 2 000 francs.', TranscriptAnonymizer::clean('Kossivi paie 2 000 francs.', ['Kossi']));
    }

    public function test_the_admin_downloads_an_anonymized_text_file_of_recent_learner_conversations(): void
    {
        $this->lesson($this->learner, 'Hello, I am Kossi, my phone is 90 12 34 56 78.');
        $this->lesson($this->admin, 'Test of the administrator, Responsable.');

        $response = $this->actingAs($this->admin)->get(route('admin.conversations.export', ['jours' => 30]))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="conversations-anonymisees-2026-10-27.txt"');

        $text = $response->getContent();

        $this->assertStringContainsString('export anonymisé (Talkami)', $text);
        $this->assertStringContainsString('Séance du jour : 1', $text);
        $this->assertStringContainsString('Conversation 1 · Séance du jour · 2026-10-27 · niveau du compte A2 · Leçon terminée', $text);
        $this->assertStringContainsString('Élève : Hello, I am [prénom], my phone is [numéro].', $text);
        $this->assertStringContainsString('Teacher Joe : Great! What do you buy? [répond en 1,4 seconde]', $text);
        $this->assertStringContainsString('Teacher Joe : Good morning [prénom]! Today: at the market.', $text);
        // Rien qui identifie : ni nom, ni numéro, ni e-mail, ni identifiant de compte ; aucun compte d'administrateur.
        $this->assertStringNotContainsString('Kossi', $text);
        $this->assertStringNotContainsString('90 12 34', $text);
        $this->assertStringNotContainsString('example.com', $text);
        $this->assertStringNotContainsString('Responsable', $text);
        $this->assertStringNotContainsString('Conversation 2', $text);
    }

    public function test_only_administrators_can_export_and_the_period_is_bounded(): void
    {
        $this->actingAs($this->learner)->get(route('admin.conversations.export'))->assertForbidden();

        $this->lesson($this->learner, 'Hello.');
        $this->travel(40)->days();

        $text = $this->actingAs($this->admin)->get(route('admin.conversations.export', ['jours' => 30]))->assertOk()->getContent();
        $this->assertStringContainsString('Aucune conversation sur cette période.', $text);

        // Une période inconnue revient à 30 jours ; 90 jours retrouve la séance.
        $this->assertStringContainsString('les 30 derniers jours', $this->actingAs($this->admin)->get(route('admin.conversations.export', ['jours' => 9999]))->getContent());
        $this->assertStringContainsString('Conversation 1', $this->actingAs($this->admin)->get(route('admin.conversations.export', ['jours' => 90]))->getContent());
    }

    public function test_the_usage_page_offers_the_export(): void
    {
        $this->actingAs($this->admin)->get(route('admin.usage.index'))
            ->assertOk()
            ->assertSeeText('Améliorer Teacher Joe : export anonymisé')
            ->assertSee(route('admin.conversations.export', ['jours' => 30]), false);
    }
}
