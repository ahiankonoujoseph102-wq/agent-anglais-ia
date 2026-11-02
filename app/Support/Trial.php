<?php

namespace App\Support;

use App\Enums\LessonStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Séance d'essai gratuite : après l'évaluation, UNE fois par compte, un accès ordinaire (questionnaire, programme,
 * séance du jour, « Mes cours ») de platform.trial.minutes minutes EN TOUT, valable platform.trial.valid_hours heures.
 * Un accès offert particulier (prix 0, aucun paiement, jamais un revenu), marqué subscriptions.is_trial.
 * Seulement pour un élève qui n'a jamais eu de formule payée, ni d'essai.
 */
final class Trial
{
    public const NOTE = 'Essai gratuit';

    public static function enabled(): bool
    {
        return (bool) config('platform.trial.enabled') && self::minutes() > 0;
    }

    public static function minutes(): int
    {
        return (int) config('platform.trial.minutes');
    }

    public static function seconds(): int
    {
        return self::minutes() * 60;
    }

    public static function validHours(): int
    {
        return max(1, (int) config('platform.trial.valid_hours'));
    }

    /** Durée de validité en toutes lettres (« 48 heures »). */
    public static function validity(): string
    {
        return self::validHours().' heures';
    }

    /**
     * L'élève peut-il ouvrir sa séance d'essai ? Évaluation passée (avec un niveau), jamais de formule payée (même
     * provisoire), jamais d'essai, aucun accès en cours ; jamais l'équipe.
     */
    public static function eligible(User $user): bool
    {
        if (! self::enabled() || $user->role !== Role::Learner) {
            return false;
        }

        if ($user->assessment()->whereNotNull('level')->doesntExist()) {
            return false;
        }

        return self::neverPaidNorTried($user) && $user->activeSubscription()->doesntExist();
    }

    /** Jamais de formule payée (même refusée ou provisoire) ni d'essai : la personne n'a pas encore découvert le cours. */
    public static function neverPaidNorTried(User $user): bool
    {
        return $user->subscriptions()
            ->where(fn ($query) => $query->whereNull('granted_days')->orWhere('is_trial', true))
            ->doesntExist()
            && $user->payments()->doesntExist();
    }

    /** Ouvre l'essai (null : plus possible, par exemple deux touchers en même temps). */
    public static function claim(User $user): ?Subscription
    {
        return DB::transaction(function () use ($user) {
            // Verrou du compte : deux touchers en même temps n'ouvrent qu'un essai.
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! self::eligible($user)) {
                return null;
            }

            $plan = Plan::find((string) config('platform.trial.plan')) ?? collect(Plan::all())->first();

            $trial = $user->subscriptions()->create([
                'plan' => $plan->key,
                'plan_name' => self::NOTE,
                // Le temps de l'essai, en tout (le plafond est appliqué par secondsLeft()).
                'daily_minutes' => self::minutes(),
                'price' => 0,
                'currency' => config('platform.currency'),
                'starts_at' => now(),
                'ends_at' => now()->addHours(self::validHours()),
                'status' => SubscriptionStatus::Active,
                'grant_note' => self::NOTE,
                'granted_days' => (int) ceil(self::validHours() / 24),
            ]);
            $trial->forceFill(['is_trial' => true])->save();

            return $trial;
        });
    }

    /** Dernier essai de l'élève (en cours ou terminé). */
    public static function latest(User $user): ?Subscription
    {
        return $user->subscriptions()->where('is_trial', true)->latest('id')->first();
    }

    /**
     * Secondes de l'essai encore libres : son temps en tout, moins les séances (orales et « Mes cours ») ouvertes
     * depuis son début ; une séance ouverte compte pour tout son temps accordé ($reserveOpen) ou le temps écoulé.
     */
    public static function secondsLeft(Subscription $trial, bool $reserveOpen = true): int
    {
        $used = 0;
        $user = $trial->user;

        foreach ([$user->lessons(), $user->courseSessions()] as $query) {
            foreach ($query->where('created_at', '>=', $trial->starts_at)->get() as $conversation) {
                if ($conversation->started_at !== null && $conversation->closeIfOver()) {
                    $used += $reserveOpen ? (int) $conversation->allowed_seconds : $conversation->elapsedUntil(now());
                } else {
                    $used += (int) $conversation->used_seconds;
                }
            }
        }

        return max(0, self::seconds() - $used);
    }

    /**
     * Essai dont le temps est pris (moins que le minimum pour ouvrir une séance) et aucune séance en cours : il se
     * termine tout de suite, pour que « Mon espace » propose les formules au lieu de « revenez demain ».
     */
    public static function expireIfUsed(User $user): bool
    {
        $trial = $user->activeSubscription;

        if ($trial === null || ! $trial->isTrial()) {
            return false;
        }

        $open = $user->lessons()->where('status', LessonStatus::InProgress)->exists()
            || $user->courseSessions()->where('status', LessonStatus::InProgress)->exists();

        $trial->setRelation('user', $user);

        if ($open || self::secondsLeft($trial, false) >= DailyTime::minStartSeconds()) {
            return false;
        }

        $trial->forceFill(['status' => SubscriptionStatus::Expired, 'ends_at' => now()])->save();
        $user->unsetRelation('activeSubscription');

        return true;
    }

    /** Termine les essais dont le temps est pris (tâche chaque minute, avec live:close-stale). */
    public static function expireUsed(): int
    {
        $count = 0;

        Subscription::query()
            ->where('is_trial', true)
            ->where('status', SubscriptionStatus::Active)
            ->where('ends_at', '>', now())
            ->pluck('user_id')
            ->each(function (int $userId) use (&$count) {
                $user = User::query()->find($userId);

                if ($user !== null && self::expireIfUsed($user)) {
                    $count++;
                }
            });

        return $count;
    }
}
