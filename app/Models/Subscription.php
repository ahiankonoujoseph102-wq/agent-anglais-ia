<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['plan', 'plan_name', 'daily_minutes', 'price', 'currency', 'starts_at', 'ends_at', 'provisional_until', 'status', 'granted_by', 'grant_note', 'granted_days', 'revoked_at'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily_minutes' => 'integer',
            'price' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'provisional_until' => 'datetime',
            'granted_days' => 'integer',
            'revoked_at' => 'datetime',
            'cost_alert_sent_at' => 'datetime',
            'status' => SubscriptionStatus::class,
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
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * Administrateur qui a offert cet accès (null : abonnement payé).
     *
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /** Accès offert par l'administrateur (démonstration) : jamais un paiement ni un revenu. */
    public function isGranted(): bool
    {
        return $this->granted_days !== null;
    }

    /**
     * Seulement les accès offerts.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeGranted(Builder $query): void
    {
        $query->whereNotNull('granted_days');
    }

    /**
     * Seulement les abonnements payés (ni offerts).
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopePaid(Builder $query): void
    {
        $query->whereNull('granted_days');
    }

    /**
     * Garde les conversations (séances, séances « Mes cours ») démarrées pendant un accès offert du même
     * apprenant : repérées à part dans la consommation pour mesurer le coût des démonstrations.
     *
     * @param  Builder<Model>  $query
     */
    public static function whereDuringGrant(Builder $query, string $table): void
    {
        $query->whereExists(fn ($grant) => $grant->selectRaw('1')
            ->from('subscriptions')
            ->whereColumn('subscriptions.user_id', $table.'.user_id')
            ->whereNotNull('subscriptions.granted_days')
            ->whereColumn($table.'.started_at', '>=', 'subscriptions.starts_at')
            ->whereColumn($table.'.started_at', '<=', 'subscriptions.ends_at'));
    }

    /** Accès provisoire (capture lue, paiement pas encore validé par l'administrateur). */
    public function isProvisional(): bool
    {
        return $this->status === SubscriptionStatus::Provisional;
    }

    public function dailySeconds(): int
    {
        return $this->daily_minutes * 60;
    }
}
