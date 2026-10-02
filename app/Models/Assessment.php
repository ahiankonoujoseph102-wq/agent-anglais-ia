<?php

namespace App\Models;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['status', 'started_at', 'ended_at', 'used_seconds', 'level', 'feedback'])]
class Assessment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssessmentStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'used_seconds' => 'integer',
            'level' => Level::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
