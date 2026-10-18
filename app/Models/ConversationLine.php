<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une réplique d'une conversation orale (texte seulement, jamais l'audio) : de l'apprenant (learner) ou de
 * Teacher Joe (teacher). Voir App\Support\Live\Transcripts.
 */
#[Fillable(['user_id', 'conversation_type', 'conversation_id', 'position', 'speaker', 'text', 'spoken_at', 'ended_at', 'metrics'])]
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
            'ended_at' => 'datetime',
            // Mesures du tour (heures en millisecondes, délai de réponse) : App\Support\Live\Transcripts::METRICS.
            'metrics' => 'array',
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

    /**
     * Délai de réponse de Teacher Joe (millisecondes entre la fin de la parole de l'apprenant et son premier son).
     */
    public function delayMs(): ?int
    {
        $delay = $this->metrics['delay_ms'] ?? null;

        return $this->isLearner() || $delay === null ? null : (int) $delay;
    }
}
