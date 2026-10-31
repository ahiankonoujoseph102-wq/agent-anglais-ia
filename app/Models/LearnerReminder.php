<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un rappel envoyé à un élève payant (App\Support\Reminders\LessonReminders) : un seul par jour et par sorte
 * (index unique), même si la commande passe deux fois.
 */
#[Fillable(['user_id', 'day', 'kind', 'push_status', 'mail_status', 'mail_error'])]
class LearnerReminder extends Model
{
    /** 30 minutes avant : e-mail et notification. */
    public const SOON = 'soon';

    /** 1 minute avant : notification seulement. */
    public const NOW = 'now';

    /** Séance pas faite 2 heures après l'heure prévue : e-mail et notification. */
    public const MISSED = 'missed';

    /** Veille du dernier jour d'un abonnement payé pas encore renouvelé : e-mail et notification. */
    public const RENEW = 'renew';

    protected function casts(): array
    {
        return ['day' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
