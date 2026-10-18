<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une réplique d'une conversation orale (texte seulement, jamais l'audio) : de l'apprenant (learner) ou de
 * Teacher Joe (teacher). Voir App\Support\Live\Transcripts.
 */
#[Fillable(['user_id', 'conversation_type', 'conversation_id', 'position', 'speaker', 'text', 'spoken_at'])]
class ConversationLine extends Model
{
    public const LEARNER = 'learner';

    public const TEACHER = 'teacher';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conversation_id' => 'integer',
            'position' => 'integer',
            'spoken_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLearner(): bool
    {
        return $this->speaker === self::LEARNER;
    }
}
