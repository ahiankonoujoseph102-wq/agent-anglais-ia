<?php

namespace App\Models;

use App\Enums\Level;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Le rôle et le niveau ne sont volontairement pas « fillable » : ils ne
 * doivent jamais pouvoir être fixés depuis un formulaire.
 */
#[Fillable(['name', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => Role::Learner->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'level' => Level::class,
            'role' => Role::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * @return HasOne<Assessment, $this>
     */
    public function assessment(): HasOne
    {
        return $this->hasOne(Assessment::class);
    }

    /**
     * @return HasMany<Program, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
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
     * L'abonnement en cours de validité, s'il y en a un.
     *
     * @return HasOne<Subscription, $this>
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->ofMany(
            ['ends_at' => 'max', 'id' => 'max'],
            fn ($query) => $query
                ->where('status', SubscriptionStatus::Active)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now()),
        );
    }

    /**
     * Secondes de séance déjà consommées aujourd'hui.
     */
    public function secondsUsedToday(): int
    {
        return (int) $this->lessons()
            ->whereDate('date', today())
            ->sum('used_seconds');
    }

    /**
     * Secondes de séance restantes aujourd'hui, ou null sans abonnement actif.
     */
    public function remainingSecondsToday(): ?int
    {
        $subscription = $this->activeSubscription;

        if ($subscription === null) {
            return null;
        }

        return max(0, $subscription->dailySeconds() - $this->secondsUsedToday());
    }
}
