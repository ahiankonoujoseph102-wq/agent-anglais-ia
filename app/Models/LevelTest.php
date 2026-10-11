<?php

namespace App\Models;

use App\Enums\Level;
use App\Enums\LevelTestStatus;
use App\Models\Concerns\IsLiveConversation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bilan oral de fin de niveau, passé avec le professeur quand les 12 unités sont terminées.
 */
#[Fillable(['program_id', 'level', 'status', 'started_at', 'ended_at', 'allowed_seconds', 'used_seconds'])]
class LevelTest extends Model
{
    use IsLiveConversation;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => Level::class,
            'status' => LevelTestStatus::class,
            'passed' => 'boolean',
            'units_to_review' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'allowed_seconds' => 'integer',
            'used_seconds' => 'integer',
            'last_seen_at' => 'datetime',
            'token_expires_at' => 'datetime',
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
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function isInProgress(): bool
    {
        return $this->status === LevelTestStatus::InProgress;
    }

    public function hasResult(): bool
    {
        return $this->passed !== null;
    }

    /**
     * Sans résultat transmis par le professeur, le bilan est interrompu et pourra être repassé.
     */
    public function finish(Carbon $at, bool $interrupted = false): void
    {
        $this->ended_at = $at;
        $this->used_seconds = $this->elapsedUntil($at);
        $this->status = match ($this->passed) {
            true => LevelTestStatus::Passed,
            false => LevelTestStatus::Failed,
            null => LevelTestStatus::Abandoned,
        };
        $this->save();
    }
}
