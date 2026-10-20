<?php

namespace App\Models;

use App\Enums\ErrorStatus;
use App\Enums\Level;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\Skill;
use App\Enums\SubscriptionStatus;
use App\Support\Countries;
use App\Support\DailyTime;
use App\Support\EmailAddress;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Le rôle, le niveau et l'obligation de changer de mot de passe ne sont
 * volontairement pas « fillable » : ils ne doivent jamais pouvoir être
 * fixés depuis un formulaire.
 */
#[Fillable(['name', 'phone', 'country', 'email', 'password'])]
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
        'must_change_password' => false,
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
            'must_change_password' => 'boolean',
            'welcomed_at' => 'datetime',
        ];
    }

    /**
     * Adresse e-mail facultative, toujours enregistrée en minuscules (null si vide).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => EmailAddress::normalize($value));
    }

    protected static function booted(): void
    {
        // Pays absent (compte créé sans le formulaire d'inscription) : celui de l'indicatif du numéro.
        static::creating(function (User $user) {
            $user->country ??= Countries::forPhone($user->phone);
        });
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** Pays de l'apprenant (choisi à l'inscription, sinon celui de l'indicatif du numéro). */
    public function countryCode(): ?string
    {
        return $this->country ?? Countries::forPhone($this->phone);
    }

    public function countryName(): string
    {
        return Countries::name($this->countryCode());
    }

    /** Pays où l'on paie par Mobile Money sur le site ; ailleurs, l'abonnement passe par WhatsApp. */
    public function canPayOnline(): bool
    {
        return Countries::hasOnlinePayment($this->countryCode());
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
     * Leçons apportées par l'apprenant (« Mes cours »).
     *
     * @return HasMany<CourseLesson, $this>
     */
    public function courseLessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class);
    }

    /**
     * Séances « Mes cours » (consommation séparée des séances orales).
     *
     * @return HasMany<CourseSession, $this>
     */
    public function courseSessions(): HasMany
    {
        return $this->hasMany(CourseSession::class);
    }

    /**
     * Documents de révision des séances « Mes cours ».
     *
     * @return HasMany<CourseReview, $this>
     */
    public function courseReviews(): HasMany
    {
        return $this->hasMany(CourseReview::class);
    }

    /**
     * Le programme en cours (un seul actif à la fois).
     *
     * @return HasOne<Program, $this>
     */
    public function activeProgram(): HasOne
    {
        return $this->hasOne(Program::class)->ofMany(['id' => 'max'], fn ($query) => $query->where('is_active', true));
    }

    /**
     * @return HasMany<LearnerError, $this>
     */
    public function learnerErrors(): HasMany
    {
        return $this->hasMany(LearnerError::class);
    }

    /**
     * Erreurs encore à revoir, les plus fréquentes d'abord.
     *
     * @return HasMany<LearnerError, $this>
     */
    public function openErrors(): HasMany
    {
        return $this->learnerErrors()
            ->where('status', ErrorStatus::Open)
            ->orderByDesc('times_seen')
            ->orderBy('id');
    }

    /**
     * @return HasMany<LevelTest, $this>
     */
    public function levelTests(): HasMany
    {
        return $this->hasMany(LevelTest::class);
    }

    /**
     * @return HasMany<Attestation, $this>
     */
    public function attestations(): HasMany
    {
        return $this->hasMany(Attestation::class);
    }

    /**
     * Nombre de jours consécutifs (jusqu'à aujourd'hui, ou hier si rien encore aujourd'hui)
     * avec au moins une minute de séance.
     */
    public function streakDays(): int
    {
        $days = $this->lessons()
            ->where('used_seconds', '>=', 60)
            ->where('date', '>=', today()->subDays(366))
            ->distinct()
            ->orderByDesc('date')
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();

        $expected = today();

        if ($days->first() !== $expected->toDateString()) {
            $expected = today()->subDay();
        }

        $streak = 0;

        foreach ($days as $day) {
            if ($day !== $expected->toDateString()) {
                break;
            }

            $streak++;
            $expected = $expected->subDay();
        }

        return $streak;
    }

    /**
     * Moyenne des notes (1 à 5) des dernières séances notées, par compétence.
     *
     * @return array<string, float|null>
     */
    public function skillAverages(int $lastLessons = 10): array
    {
        $lessons = $this->lessons()
            ->whereNotNull('reported_at')
            ->latest('id')
            ->limit($lastLessons)
            ->get(array_map(fn (Skill $skill) => $skill->column(), Skill::cases()));

        $averages = [];

        foreach (Skill::cases() as $skill) {
            $scores = $lessons->pluck($skill->column())->filter();
            $averages[$skill->value] = $scores->isEmpty() ? null : round($scores->avg(), 1);
        }

        return $averages;
    }

    /**
     * Le paiement en attente de validation, s'il y en a un (au plus un).
     *
     * @return HasOne<Payment, $this>
     */
    public function pendingPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->where('status', PaymentStatus::Pending);
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /**
     * Le dernier abonnement payé (actif, à venir ou expiré).
     *
     * @return HasOne<Subscription, $this>
     */
    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->ofMany(
            ['ends_at' => 'max', 'id' => 'max'],
            fn ($query) => $query->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Expired]),
        );
    }

    /**
     * L'abonnement en cours de validité, s'il y en a un : définitif, ou provisoire
     * (capture lue, en attente de la validation) tant que ses 24 heures ne sont pas
     * écoulées. L'heure de fin est vérifiée à chaque accès, sans attendre le planificateur.
     *
     * @return HasOne<Subscription, $this>
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->ofMany(
            ['ends_at' => 'max', 'id' => 'max'],
            fn ($query) => $query
                ->where(fn ($query) => $query
                    ->where('status', SubscriptionStatus::Active)
                    ->orWhere(fn ($query) => $query
                        ->where('status', SubscriptionStatus::Provisional)
                        ->where('provisional_until', '>', now())))
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now()),
        );
    }

    /**
     * Secondes du temps du jour déjà prises aujourd'hui : séances orales ET séances « Mes cours »
     * (un seul temps par jour, partagé ; l'évaluation et le bilan de niveau n'y comptent pas). Le « jour »
     * suit APP_TIMEZONE (Africa/Lome) : le décompte repart à zéro à minuit, heure de Lomé.
     *
     * Une séance encore ouverte compte pour tout le temps qui lui a été accordé ($reserveOpen, pour
     * ouvrir une nouvelle séance : deux séances en même temps ne dépassent jamais le temps du jour), ou
     * pour le temps déjà écoulé (affichage). Une séance finie ou abandonnée est d'abord clôturée.
     */
    public function secondsUsedToday(bool $reserveOpen = true): int
    {
        $used = 0;

        foreach ([$this->lessons(), $this->courseSessions()] as $query) {
            foreach ($query->whereDate('date', today())->get() as $conversation) {
                if ($conversation->started_at !== null && $conversation->closeIfOver()) {
                    $used += $reserveOpen ? (int) $conversation->allowed_seconds : $conversation->elapsedUntil(now());
                } else {
                    $used += (int) $conversation->used_seconds;
                }
            }
        }

        return $used;
    }

    /**
     * Secondes du temps du jour restantes (séance orale et « Mes cours »), ou null sans abonnement actif.
     */
    public function remainingSecondsToday(bool $reserveOpen = true): ?int
    {
        $subscription = $this->activeSubscription;

        if ($subscription === null) {
            return null;
        }

        return max(0, $subscription->dailySeconds() - $this->secondsUsedToday($reserveOpen));
    }

    /**
     * Assez de temps aujourd'hui pour ouvrir une NOUVELLE séance (platform.daily_time.min_start_seconds) ?
     */
    public function canStartSessionToday(): bool
    {
        return (int) $this->remainingSecondsToday() >= DailyTime::minStartSeconds();
    }
}
