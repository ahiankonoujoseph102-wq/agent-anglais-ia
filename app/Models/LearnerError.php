<?php

namespace App\Models;

use App\Enums\ErrorStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une erreur relevée par le professeur. Elle revient dans les séances suivantes
 * jusqu'à ce que l'apprenant dise la forme correcte deux séances de suite.
 */
#[Fillable(['lesson_id', 'wrong', 'correct', 'explanation', 'status', 'correct_streak', 'times_seen', 'last_reviewed_at', 'resolved_at'])]
class LearnerError extends Model
{
    /**
     * Nombre de corrections consécutives qui retirent l'erreur de la liste.
     */
    public const CORRECTIONS_TO_RESOLVE = 2;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ErrorStatus::Open->value,
        'correct_streak' => 0,
        'times_seen' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ErrorStatus::class,
            'correct_streak' => 'integer',
            'times_seen' => 'integer',
            'last_reviewed_at' => 'datetime',
            'resolved_at' => 'datetime',
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
     * Résultat de la révision de cette erreur pendant une séance.
     */
    public function review(bool $correctedByLearner): void
    {
        $this->last_reviewed_at = now();

        if ($correctedByLearner) {
            $this->correct_streak++;

            if ($this->correct_streak >= self::CORRECTIONS_TO_RESOLVE) {
                $this->status = ErrorStatus::Resolved;
                $this->resolved_at = now();
            }
        } else {
            $this->correct_streak = 0;
            $this->times_seen++;
        }

        $this->save();
    }

    /**
     * Forme comparable d'une phrase, pour ne pas enregistrer deux fois la même erreur.
     */
    public static function normalize(string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('/[^\pL\pN\']+/u', ' ', $text)));
    }
}
