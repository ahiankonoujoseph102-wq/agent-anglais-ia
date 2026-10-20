<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Document personnel de révision d'une séance « Mes cours » (App\Support\CourseReviews) : la leçon expliquée, les
 * exercices travaillés, les erreurs de l'élève, les phrases à retenir et un exercice à refaire seul. Un par séance.
 */
#[Fillable(['user_id', 'course_session_id', 'course_lesson_id', 'status', 'reason', 'content', 'path', 'provider', 'attempts', 'next_attempt_at', 'prepared_at', 'mail_status', 'mail_error', 'mailed_at'])]
class CourseReview extends Model
{
    public const PENDING = 'pending';

    public const READY = 'ready';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'prepared_at' => 'datetime',
            'mailed_at' => 'datetime',
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
     * @return BelongsTo<CourseSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }

    /**
     * @return BelongsTo<CourseLesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'course_lesson_id');
    }

    public function isReady(): bool
    {
        return $this->status === self::READY;
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::FAILED;
    }
}
