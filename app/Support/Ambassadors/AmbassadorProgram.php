<?php

namespace App\Support\Ambassadors;

use App\Enums\AmbassadorStatus;
use App\Http\Requests\Admin\OfferAccessRequest;
use App\Models\Ambassador;
use App\Models\Lesson;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AccessGrant;
use App\Support\Live\WorkingTogether;
use App\Support\Plan;
use App\Support\SiteSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Programme d'ambassadeurs : places (réglables dans « Réglages »), nomination, mois gratuits et retrait.
 *
 * Un mois gratuit est un accès offert ORDINAIRE (AccessGrant : mêmes droits que la formule, prix 0, jamais un
 * revenu), de la formule platform.ambassadors.plan, relié à l'ambassadeur (subscriptions.ambassador_id).
 * « Renouveler 1 mois » prolonge le mois en cours de platform.ambassadors.month_days jours (ou en ouvre un
 * nouveau s'il est fini), jusqu'à platform.ambassadors.max_months mois. Toujours décidé par l'administrateur.
 */
final class AmbassadorProgram
{
    public const PLACES_SETTING = 'ambassadors.places';

    /** Nombre de places (Réglages, sinon configuration). */
    public static function places(): int
    {
        return max(0, (int) SiteSettings::get(self::PLACES_SETTING));
    }

    public static function activeCount(): int
    {
        return Ambassador::query()->active()->count();
    }

    /** Places encore libres : jamais négatif (le nombre de places a pu baisser sous le nombre d'actifs). */
    public static function freePlaces(): int
    {
        return max(0, self::places() - self::activeCount());
    }

    /**
     * Fait d'un compte un ambassadeur (ou le réintègre, avec le même code et ses mois déjà ouverts), puis ouvre
     * son premier mois gratuit quand c'est possible. Renvoie l'ambassadeur et, si le mois n'a pas pu s'ouvrir,
     * la raison.
     *
     * @return array{ambassador: Ambassador, grant: ?Subscription, notice: ?string}
     */
    public static function appoint(User $user, User $admin): array
    {
        if ($user->isAdmin()) {
            throw new AmbassadorRefused('Un compte administrateur ne peut pas être ambassadeur.');
        }

        $ambassador = DB::transaction(function () use ($user, $admin) {
            // Verrou sur les ambassadeurs actifs : deux nominations en même temps ne dépassent jamais les places.
            $active = Ambassador::query()->active()->lockForUpdate()->get();
            $existing = Ambassador::query()->where('user_id', $user->id)->lockForUpdate()->first();

            if ($existing?->isActive()) {
                throw new AmbassadorRefused('Ce compte est déjà ambassadeur.');
            }

            if ($active->count() >= self::places()) {
                throw new AmbassadorRefused(sprintf(
                    'Les %d places d\'ambassadeurs sont toutes prises. Retirez un ambassadeur ou augmentez le nombre de places dans « Réglages ».',
                    self::places(),
                ));
            }

            if ($existing !== null) {
                $existing->forceFill(['status' => AmbassadorStatus::Active, 'removed_at' => null, 'appointed_by' => $admin->id])->save();

                return $existing;
            }

            return Ambassador::query()->create([
                'user_id' => $user->id,
                'code' => AmbassadorCode::generate($user->name),
                'status' => AmbassadorStatus::Active,
                'appointed_by' => $admin->id,
            ]);
        });

        try {
            return ['ambassador' => $ambassador, 'grant' => self::renew($ambassador, $admin), 'notice' => null];
        } catch (AmbassadorRefused $refused) {
            return ['ambassador' => $ambassador, 'grant' => null, 'notice' => $refused->getMessage()];
        }
    }

    /**
     * Ouvre le mois gratuit suivant : prolonge le mois en cours, ou en ouvre un nouveau à partir de maintenant.
     */
    public static function renew(Ambassador $ambassador, User $admin): Subscription
    {
        return DB::transaction(function () use ($ambassador, $admin) {
            $ambassador = Ambassador::query()->with('user')->lockForUpdate()->findOrFail($ambassador->id);
            $days = (int) config('platform.ambassadors.month_days');

            if (! $ambassador->isActive()) {
                throw new AmbassadorRefused('Cet ambassadeur a été retiré du programme : réintégrez-le d\'abord.');
            }

            if ($ambassador->monthsLeft() === 0) {
                throw new AmbassadorRefused(sprintf('Les %d mois gratuits ont déjà tous été offerts à cet ambassadeur.', (int) config('platform.ambassadors.max_months')));
            }

            $current = $ambassador->currentGrant();

            if ($current !== null) {
                $current->forceFill([
                    'ends_at' => $current->ends_at->copy()->addDays($days),
                    'granted_days' => $current->granted_days + $days,
                ])->save();
                $grant = $current;
            } else {
                $blocking = OfferAccessRequest::blockingMessage($ambassador->user);

                if ($blocking !== null) {
                    throw new AmbassadorRefused('Mois gratuit non ouvert : '.lcfirst($blocking));
                }

                $plan = Plan::find((string) config('platform.ambassadors.plan'));
                $grant = AccessGrant::offer($ambassador->user, $admin, $plan, $days, 'Ambassadeur');
                $grant->forceFill(['ambassador_id' => $ambassador->id])->save();
            }

            $ambassador->increment('months_granted');

            return $grant;
        });
    }

    /**
     * Retire l'ambassadeur du programme : sa place se libère et son mois gratuit en cours s'arrête tout de suite.
     * Ses mois déjà ouverts et son code sont gardés (réintégration possible).
     */
    public static function remove(Ambassador $ambassador): void
    {
        DB::transaction(function () use ($ambassador) {
            $ambassador->forceFill(['status' => AmbassadorStatus::Removed, 'removed_at' => now()])->save();

            if ($ambassador->currentGrant() !== null) {
                AccessGrant::revoke($ambassador->user);
            }
        });
    }

    public static function setParentalConsent(Ambassador $ambassador, bool $received): void
    {
        $ambassador->forceFill(['parental_consent_at' => $received ? ($ambassador->parental_consent_at ?? now()) : null])->save();
    }

    /**
     * Régularité : jours (heure de l'application) avec au moins une séance du jour qui a vraiment eu lieu
     * (au moins teacher.working_together.held_seconds), sur les platform.ambassadors.activity_days derniers jours.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, array{days: int, last: ?Carbon}>
     */
    public static function activity(array $userIds): array
    {
        $since = today()->subDays((int) config('platform.ambassadors.activity_days') - 1);
        $rows = Lesson::query()
            ->whereIn('user_id', $userIds)
            ->where('used_seconds', '>=', WorkingTogether::heldSeconds())
            ->get(['user_id', 'date']);

        $activity = [];

        foreach ($userIds as $id) {
            $mine = $rows->where('user_id', $id);
            $activity[$id] = [
                'days' => $mine->filter(fn (Lesson $lesson) => $lesson->date->gte($since))->map(fn (Lesson $lesson) => $lesson->date->toDateString())->unique()->count(),
                'last' => $mine->max('date'),
            ];
        }

        return $activity;
    }

    /** Statut lisible du mois gratuit : « Mois 2 sur 6, jusqu'au 12 décembre 2026 ». */
    public static function monthLabel(Ambassador $ambassador, ?Subscription $grant): string
    {
        $label = sprintf('Mois %d sur %d', $ambassador->months_granted, (int) config('platform.ambassadors.max_months'));

        return $grant !== null
            ? $label.', jusqu\'au '.$grant->ends_at->translatedFormat('j F Y')
            : $label.', aucun mois gratuit en cours';
    }

    /** Un mois gratuit de plus peut être ouvert (bouton « Renouveler 1 mois »). */
    public static function canRenew(Ambassador $ambassador): bool
    {
        return $ambassador->isActive() && $ambassador->monthsLeft() > 0;
    }
}
