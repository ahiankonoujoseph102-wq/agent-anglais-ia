<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un e-mail de relance d'un inscrit sans formule payée (App\Support\Reminders\FollowUps), ou un message WhatsApp
 * préparé par l'équipe (« wa_… ») : UN par campagne et par compte (index unique).
 */
#[Fillable(['user_id', 'campaign', 'status', 'error', 'sent_at'])]
class LearnerMail extends Model
{
    /** Lancement de l'offre : refaire l'évaluation gratuite, puis la séance d'essai (learners:offer-trial). */
    public const OFFER = 'offer';

    /** Inscrit depuis un jour sans évaluation. */
    public const NO_ASSESSMENT = 'no_assessment';

    /** Évaluation passée depuis un jour, séance d'essai pas encore ouverte. */
    public const NO_TRIAL = 'no_trial';

    /** Séance d'essai terminée sans formule. */
    public const TRIAL_ENDED = 'trial_ended';

    public const CAMPAIGNS = [self::OFFER, self::NO_ASSESSMENT, self::NO_TRIAL, self::TRIAL_ENDED];

    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    /** Aucune adresse e-mail : à relancer sur WhatsApp depuis « Relances ». */
    public const NO_EMAIL = 'no_email';

    /** Plus d'actualité au moment de l'envoi (évaluation faite, essai ouvert, formule payée, e-mails refusés). */
    public const SKIPPED = 'skipped';

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
