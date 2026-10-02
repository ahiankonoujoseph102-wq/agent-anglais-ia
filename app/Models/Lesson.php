<?php

namespace App\Models;

use App\Enums\LessonStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une séance orale quotidienne avec le professeur.
 */
#[Fillable(['subscription_id', 'program_id', 'date', 'allowed_seconds', 'used_seconds', 'started_at', 'ended_at', 'status', 'topic', 'summary'])]
class Lesson extends Model
{
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
}
