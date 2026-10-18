<?php

namespace Tests\Feature\Admin;

use App\Enums\AssessmentStatus;
use App\Enums\LessonStatus;
use App\Enums\Level;
use App\Models\ConversationLine;
use App\Models\Lesson;
use App\Models\User;
use App\Support\Live\Transcripts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Administration → Inscrits → un apprenant : ses séances, la lecture d'une conversation, la suppression.
 */
class ConversationAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $learner;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-18 09:30:00', 'Africa/Lome'));
        $this->admin = User::factory()->admin()->create();
        $this->learner = User::factory()->level(Level::A2)->create(['name' => 'Kossi Amegah']);
        $this->lesson = Lesson::query()->forceCreate([
            'user_id' => $this->learner->id, 'date' => today(), 'started_at' => now()->subMinutes(15), 'ended_at' => now(),
            'allowed_seconds' => 900, 'used_seconds' => 852, 'status' => LessonStatus::Completed, 'outcome' => 'completed', 'live_provider' => 'openai',
        ]);
        Transcripts::record($this->lesson, [
            ['seq' => 1, 'speaker' => 'teacher', 'text' => 'Good morning Kossi! Today: at the market.', 'at' => now()->subMinutes(14)->getTimestampMs()],
            ['seq' => 2, 'speaker' => 'learner', 'text' => 'Good morning teacher.', 'at' => now()->subMinutes(13)->getTimestampMs()],
        ]);
    }

    public function test_the_learner_page_lists_every_session_with_a_link_to_read_it(): void
    {
        $this->learner->assessment()->create(['status' => AssessmentStatus::Completed, 'started_at' => now()->subDays(2), 'allowed_seconds' => 300, 'used_seconds' => 290, 'level' => Level::A2]);

        $this->actingAs($this->admin)->get(route('admin.users.show', $this->learner))
            ->assertOk()
            ->assertSeeInOrder(['Conversations', '18/10/2026 09:15', 'Séance du jour', '14 minutes 12 secondes', 'OpenAI', 'Leçon terminée', 'Lire la conversation', '(2 répliques)'], false)
            ->assertSeeInOrder(['Évaluation gratuite', 'Niveau A2', 'Aucun texte'], false)
            ->assertSee(route('admin.conversations.show', [$this->learner, 'lesson', $this->lesson->id]), false)
            ->assertSeeText('Supprimer les conversations de cet apprenant');
    }

    public function test_the_conversation_is_read_in_bubbles_with_the_time_of_each_line(): void
    {
        $this->actingAs($this->admin)->get(route('admin.conversations.show', [$this->learner, 'lesson', $this->lesson->id]))
            ->assertOk()
            ->assertSeeText('Séance du jour')
            ->assertSeeText('14 minutes 12 secondes')
            ->assertSeeText('OpenAI')
            ->assertSeeText('Leçon terminée')
            ->assertSeeInOrder(['chat-teacher', 'Teacher Joe', '09:16:00', 'Good morning Kossi! Today: at the market.', 'chat-learner', 'Kossi Amegah', '09:17:00', 'Good morning teacher.'], false)
            ->assertSeeText('La voix n\'est jamais enregistrée');
    }

    public function test_each_reply_shows_its_delay_slow_ones_in_orange_with_the_median_and_the_longest_and_grey_events(): void
    {
        $ms = fn (int $minutes, int $seconds = 0) => now()->subMinutes($minutes)->addSeconds($seconds)->getTimestampMs();
        Transcripts::record($this->lesson, [
            ['seq' => 1, 'speaker' => 'teacher', 'text' => 'Good morning Kossi! Today: at the market.', 'at' => $ms(14), 'metrics' => []],
            ['seq' => 3, 'speaker' => 'teacher', 'text' => 'Great! What do you buy?', 'at' => $ms(12, 30), 'metrics' => ['delay_ms' => 1400]],
            ['seq' => 4, 'speaker' => 'learner', 'text' => 'I buy tomatoes and rice for my family.', 'at' => $ms(11), 'end' => $ms(10)],
            ['seq' => 5, 'speaker' => 'teacher', 'text' => 'So you buy tomatoes and rice. Nice!', 'at' => $ms(10, 12), 'metrics' => ['delay_ms' => 12300]],
            ['seq' => 6, 'speaker' => 'teacher', 'text' => 'Do you cook?', 'at' => $ms(9), 'metrics' => ['delay_ms' => 2000]],
        ]);
        Transcripts::recordEvents($this->lesson, [
            ['kind' => 'gate_closed', 'detail' => 'voix du professeur', 'at' => $ms(12, 31)],
            ['kind' => 'no_answer', 'detail' => '8 secondes', 'at' => $ms(10, 8)],
        ]);

        $this->actingAs($this->admin)->get(route('admin.conversations.show', [$this->learner, 'lesson', $this->lesson->id]))
            ->assertOk()
            ->assertSeeInOrder(['Délai de réponse : médian', '2,0 secondes', 'le plus long', 'chat-delay-slow', '12,3 secondes', '(3 réponses mesurées)'], false)
            ->assertSeeInOrder([
                'Great! What do you buy?', 'répond en 1,4 seconde',
                'chat-event', '09:18:31', 'Micro fermé : voix du professeur',
                'I buy tomatoes and rice for my family.',
                'Aucune réponse du professeur : 8 secondes',
                'So you buy tomatoes and rice. Nice!', '<p class="chat-delay chat-delay-slow">répond en 12,3 secondes</p>',
                'Do you cook?', '<p class="chat-delay">répond en 2,0 secondes</p>',
            ], false);
    }

    public function test_a_session_without_measures_says_so(): void
    {
        $this->actingAs($this->admin)->get(route('admin.conversations.show', [$this->learner, 'lesson', $this->lesson->id]))
            ->assertOk()
            ->assertSeeText('Délai de réponse : aucune réponse mesurée pour cette séance.')
            ->assertDontSee('répond en', false);
    }

    public function test_only_administrators_can_read_or_delete_conversations(): void
    {
        $this->get(route('admin.conversations.show', [$this->learner, 'lesson', $this->lesson->id]))->assertRedirect(route('login'));
        $this->actingAs($this->learner)->get(route('admin.conversations.show', [$this->learner, 'lesson', $this->lesson->id]))->assertForbidden();
        $this->actingAs($this->learner)->delete(route('admin.conversations.destroy', $this->learner), ['confirmed' => 1])->assertForbidden();

        // Une séance d'un autre apprenant n'est pas lisible sous ce nom.
        $other = User::factory()->create();
        $this->actingAs($this->admin)->get(route('admin.conversations.show', [$other, 'lesson', $this->lesson->id]))->assertNotFound();

        $this->assertSame(2, ConversationLine::count());
    }

    public function test_the_administrator_deletes_the_conversations_of_a_learner_after_confirmation(): void
    {
        $other = User::factory()->create();
        $otherLesson = Lesson::query()->forceCreate(['user_id' => $other->id, 'date' => today(), 'started_at' => now(), 'allowed_seconds' => 900, 'used_seconds' => 0, 'status' => LessonStatus::InProgress]);
        Transcripts::record($otherLesson, [['seq' => 1, 'speaker' => 'learner', 'text' => 'Autre apprenant']]);
        $this->learner->assessment()->create(['status' => AssessmentStatus::Completed, 'started_at' => now(), 'allowed_seconds' => 300, 'used_seconds' => 290]);
        $this->learner->assessment->forceFill(['transcript' => 'Learner: hello'])->save();

        // Sans confirmation (JavaScript absent) : page de confirmation, rien n'est effacé.
        $this->actingAs($this->admin)->delete(route('admin.conversations.destroy', $this->learner))
            ->assertOk()
            ->assertSeeText('Supprimer les conversations de cet apprenant ?');
        $this->assertSame(3, ConversationLine::count());

        $this->actingAs($this->admin)->delete(route('admin.conversations.destroy', $this->learner), ['confirmed' => 1])
            ->assertRedirect(route('admin.users.show', $this->learner).'#conversations')
            ->assertSessionHas('status', 'Les conversations de Kossi Amegah sont supprimées (2 répliques).');

        $this->assertSame(['Autre apprenant'], ConversationLine::pluck('text')->all());
        $this->assertNull($this->learner->assessment->fresh()->transcript);
        $this->assertNotNull($this->lesson->fresh(), 'La séance et son suivi restent.');
        $this->actingAs($this->admin)->get(route('admin.users.show', $this->learner))->assertSeeText('Aucun texte');
    }

    public function test_the_delete_button_uses_the_confirmation_inside_the_page(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.users.show', $this->learner))->getContent();

        $this->assertMatchesRegularExpression('/<form[^>]+conversations"[^>]*data-confirm/s', $html);
    }
}
