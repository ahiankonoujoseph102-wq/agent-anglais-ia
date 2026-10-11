<?php

namespace App\Models;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\Concerns\IsLiveConversation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * L'évaluation orale gratuite (une seule par compte, garantie par la base).
 */
#[Fillable(['status', 'started_at', 'ended_at', 'allowed_seconds', 'used_seconds', 'level', 'feedback'])]
class Assessment extends Model
{
    use IsLiveConversation;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssessmentStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'allowed_seconds' => 'integer',
            'used_seconds' => 'integer',
            'level' => Level::class,
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

    public function isInProgress(): bool
    {
        return $this->status === AssessmentStatus::InProgress;
    }

    /**
     * Sans niveau enregistré, l'évaluation est considérée comme interrompue.
     */
    public function finish(Carbon $at, bool $interrupted = false): void
    {
        $this->ended_at = $at;
        $this->used_seconds = $this->elapsedUntil($at);
        $this->status = $this->level !== null ? AssessmentStatus::Completed : AssessmentStatus::Abandoned;
        $this->save();
    }

    /**
     * Enregistre le résultat annoncé par le professeur, ici et sur le compte.
     */
    public function recordResult(Level $level, string $feedback): void
    {
        $this->level = $level;
        $this->feedback = $feedback;
        $this->save();

        $this->user->level = $level;
        $this->user->save();
    }
}
