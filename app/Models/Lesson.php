<?php

namespace App\Models;

use App\Enums\LessonStatus;
use App\Models\Concerns\IsLiveConversation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une séance orale quotidienne avec le professeur.
 */
#[Fillable(['subscription_id', 'program_id', 'program_lesson_id', 'date', 'allowed_seconds', 'used_seconds', 'started_at', 'ended_at', 'status', 'topic', 'summary', 'homework', 'outcome', 'teacher'])]
class Lesson extends Model
{
    use IsLiveConversation;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'allowed_seconds' => 'integer',
            'used_seconds' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'status' => LessonStatus::class,
            'last_seen_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'paused_seconds' => 'integer',
            'connections' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'score_fluency' => 'integer',
            'score_pronunciation' => 'integer',
            'score_grammar' => 'integer',
            'score_vocabulary' => 'integer',
            'score_comprehension' => 'integer',
            'reported_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return BelongsTo<ProgramLesson, $this>
     */
    public function programLesson(): BelongsTo
    {
        return $this->belongsTo(ProgramLesson::class);
    }

    public function isInProgress(): bool
    {
        return $this->status === LessonStatus::InProgress;
    }

    public function finish(Carbon $at, bool $interrupted = false): void
    {
        $this->ended_at = $at;
        $this->used_seconds = $this->elapsedUntil($at);
        $this->status = $interrupted ? LessonStatus::Interrupted : LessonStatus::Completed;
        $this->save();
    }
}
