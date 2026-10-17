<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['plan', 'plan_name', 'daily_minutes', 'price', 'currency', 'starts_at', 'ends_at', 'provisional_until', 'status'])]
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
