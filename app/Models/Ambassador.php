<?php

namespace App\Models;

use App\Enums\AmbassadorStatus;
use App\Enums\SubscriptionStatus;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ambassadeur de la plateforme : un compte, un code personnel (lien, code QR), un accès gratuit renouvelé mois
 * par mois par l'administrateur (App\Support\Ambassadors\AmbassadorProgram).
 */
#[Fillable(['user_id', 'code', 'status', 'months_granted', 'parental_consent_at', 'appointed_by', 'removed_at', 'last_ip_hash'])]
class Ambassador extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AmbassadorStatus::class,
            'months_granted' => 'integer',
            'parental_consent_at' => 'datetime',
            'removed_at' => 'datetime',
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
     * Mois gratuits ouverts (accès offerts reliés à l'ambassadeur).
     *
     * @return HasMany<Subscription, $this>
     */
    public function grants(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Comptes inscrits grâce à l'ambassadeur.
     *
     * @return HasMany<AmbassadorReferral, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(AmbassadorReferral::class);
    }

    /**
     * @param  Builder<Ambassador>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', AmbassadorStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === AmbassadorStatus::Active;
    }

    /** Mois gratuit en cours (null : aucun). */
    public function currentGrant(): ?Subscription
    {
        return $this->grants()
            ->where('status', SubscriptionStatus::Active)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();
    }

    /** Lien personnel à partager, toujours sur l'adresse officielle du site (APP_URL). */
    public function link(): string
    {
        return Seo::url(route('ambassador.link', $this->code, false));
    }

    /** Lien sans « https:// », plus lisible à l'écran et sur papier. */
    public function shortLink(): string
    {
        return preg_replace('#^https?://#', '', $this->link());
    }

    /** Mois gratuits encore possibles. */
    public function monthsLeft(): int
    {
        return max(0, (int) config('platform.ambassadors.max_months') - $this->months_granted);
    }
}
