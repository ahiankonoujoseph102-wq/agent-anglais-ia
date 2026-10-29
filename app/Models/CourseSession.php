<?php

namespace App\Models;

use App\Enums\LessonStatus;
use App\Models\Concerns\IsLiveConversation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Une séance « Mes cours » : Teacher Joe réexplique une leçon de l'apprenant puis la fait pratiquer.
 * Elle prend sur le temps du jour de la formule, partagé avec la séance orale (User::remainingSecondsToday).
 */
#[Fillable(['course_lesson_id', 'course_part_id', 'date', 'allowed_seconds', 'used_seconds', 'started_at', 'ended_at', 'status', 'teacher'])]
class CourseSession extends Model
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
     * @return BelongsTo<CourseLesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'course_lesson_id');
    }

    /**
     * Partie du PDF travaillée pendant la séance (long PDF), gardée pour le document de révision.
     *
     * @return BelongsTo<CoursePart, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(CoursePart::class, 'course_part_id');
    }

    /**
     * @return HasOne<CourseReview, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(CourseReview::class);
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
