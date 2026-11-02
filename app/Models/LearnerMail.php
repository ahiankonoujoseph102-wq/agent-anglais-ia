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

    /** Essai terminé, deuxième et troisième relance (quelques jours après), si aucune formule n'est choisie. */
    public const TRIAL_ENDED_2 = 'trial_ended_2';

    public const TRIAL_ENDED_3 = 'trial_ended_3';

    /** Ancien abonné payant dont la formule est terminée sans renouvellement : trois relances espacées, puis plus rien. */
    public const LAPSED_1 = 'lapsed_1';

    public const LAPSED_2 = 'lapsed_2';

    public const LAPSED_3 = 'lapsed_3';

    public const CAMPAIGNS = [
        self::OFFER, self::NO_ASSESSMENT, self::NO_TRIAL, self::TRIAL_ENDED,
        self::TRIAL_ENDED_2, self::TRIAL_ENDED_3, self::LAPSED_1, self::LAPSED_2, self::LAPSED_3,
    ];

    /** Relances « commerciales » : un petit nombre par jour d'envoi, un jour sur platform.follow_ups.sales.every_days. */
    public const SALES = [self::TRIAL_ENDED_2, self::TRIAL_ENDED_3, self::LAPSED_1, self::LAPSED_2, self::LAPSED_3];

    public const LAPSED = [self::LAPSED_1, self::LAPSED_2, self::LAPSED_3];

    public const CAMPAIGN_LABELS = [
        self::OFFER => 'Lancement de l\'offre',
        self::NO_ASSESSMENT => 'Évaluation à passer',
        self::NO_TRIAL => 'Essai à commencer',
        self::TRIAL_ENDED => 'Fin de l\'essai (bilan)',
        self::TRIAL_ENDED_2 => 'Après l\'essai, deuxième relance',
        self::TRIAL_ENDED_3 => 'Après l\'essai, dernière relance',
        self::LAPSED_1 => 'Formule terminée, première relance',
        self::LAPSED_2 => 'Formule terminée, deuxième relance',
        self::LAPSED_3 => 'Formule terminée, dernière relance',
    ];

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
