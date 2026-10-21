<?php

namespace App\Models;

use App\Enums\AssessmentStatus;
use App\Enums\Level;
use App\Models\Concerns\IsLiveConversation;
use App\Support\Gemini\AssessmentEstimator;
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

    public function isInProgress(): bool
    {
        return $this->status === AssessmentStatus::InProgress;
    }

    /** Longueur maximale gardée de la transcription (une évaluation dure quelques minutes). */
    public const TRANSCRIPT_MAX = 20000;

    /**
     * Fin de l'évaluation. Sans verdict du professeur (temps écoulé, page fermée), le niveau
     * est estimé à partir de ce que l'apprenant a dit ; sans aucune parole de l'apprenant,
     * l'évaluation reste interrompue (rien à juger).
     */
    public function finish(Carbon $at, bool $interrupted = false): void
    {
        $this->ended_at = $at;
        $this->used_seconds = $this->elapsedUntil($at);
        $this->save();

        if ($this->level === null && $this->learnerSpoke()) {
            $this->recordEstimatedLevel();
        }

        $this->status = $this->level !== null ? AssessmentStatus::Completed : AssessmentStatus::Abandoned;
        $this->save();
    }

    /**
     * Ajoute des sous-titres envoyés par le navigateur (« Learner: … », « Teacher: … »).
     */
    public function appendTranscript(?string $text): void
    {
        $text = trim((string) $text);

        if ($text === '' || mb_strlen((string) $this->transcript) >= self::TRANSCRIPT_MAX) {
            return;
        }

        $this->transcript = mb_substr(ltrim($this->transcript."\n".$text), 0, self::TRANSCRIPT_MAX);
    }

    public function learnerSpoke(): bool
    {
        return (bool) preg_match('/^Learner:\s*\S/m', (string) $this->transcript);
    }

    /**
     * Niveau estimé par le modèle texte ; s'il ne répond pas, A1 provisoire
     * (le programme commence au plus simple, l'administrateur peut réinitialiser l'évaluation).
     */
    private function recordEstimatedLevel(): void
    {
        $estimate = app(AssessmentEstimator::class)->estimate((string) $this->transcript);

        $this->recordResult(
            $estimate['level'] ?? Level::A1,
            $estimate['feedback'] ?? 'Votre évaluation s\'est terminée avant l\'annonce du niveau : niveau A1 provisoire. Votre professeur l\'ajustera pendant les séances.',
        );
    }

    /**
     * Après le verdict, pendant l'évaluation : mini-leçon découverte, questions libres et conclusion.
     * La fin autorisée devient « maintenant + afterVerdictSeconds() » : le temps s'ajoute à partir du
     * verdict (au plus 5 + 1 + 2 minutes au total ; plus court si le verdict arrive tôt). Le serveur
     * coupe toujours à cette nouvelle limite.
     */
    public function startQuestions(): void
    {
        if (! $this->isInProgress() || $this->level === null) {
            return;
        }

        $this->allowed_seconds = $this->elapsedUntil(now()) + self::afterVerdictSeconds();
        $this->save();
    }

    /**
     * Temps accordé après le verdict : mini-leçon découverte, questions libres et conclusion.
     */
    public static function afterVerdictSeconds(): int
    {
        return (int) config('platform.assessment.discovery_seconds') + (int) config('platform.assessment.questions_seconds');
    }

    /**
     * Durée la plus longue d'une évaluation, en minutes : l'évaluation elle-même (max_minutes), puis la mini-leçon,
     * les questions et la conclusion. Pour les textes du site qui donnent une durée totale.
     */
    public static function totalMinutes(): int
    {
        return (int) config('platform.assessment.max_minutes') + (int) ceil(self::afterVerdictSeconds() / 60);
    }

    /**
     * Verdict donné, conversation encore ouverte : moment des questions et de la conclusion.
     */
    public function inQuestions(): bool
    {
        return $this->isInProgress() && $this->level !== null;
    }

    /**
     * Enregistre le résultat annoncé par le professeur, ici et sur le compte.
     */
    public function recordResult(Level $level, string $feedback): void
    {
        $this->level = $level;
        $this->feedback = $feedback;
        $this->save();

        $this->loadMissing('user');
        $this->user->level = $level;
        $this->user->save();
    }
}
