<?php

namespace App\Support;

use App\Enums\LessonStatus;
use App\Enums\SubscriptionStatus;
use App\Models\CourseSession;
use App\Models\Lesson;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Accès offert par l'administrateur : un abonnement ordinaire de la formule choisie
 * (donc exactement les mêmes droits), mais gratuit, sans paiement et marqué « offert ».
 */
final class AccessGrant
{
    /**
     * Offre l'accès : la formule choisie, à partir de maintenant, pour ce nombre de jours.
     */
    public static function offer(User $learner, User $admin, Plan $plan, int $days, ?string $note): Subscription
    {
        $days = max(1, min($days, (int) config('platform.granted_access.max_days')));

        return DB::transaction(function () use ($learner, $admin, $plan, $days, $note) {
            // Verrou du compte : deux administrateurs qui offrent en même temps n'ouvrent qu'un accès.
            $learner = User::query()->lockForUpdate()->findOrFail($learner->id);

            return $learner->subscriptions()->create([
                'plan' => $plan->key,
                'plan_name' => $plan->name,
                'daily_minutes' => $plan->dailyMinutes,
                // Jamais un revenu : prix 0, aucun paiement lié.
                'price' => 0,
                'currency' => config('platform.currency'),
                'starts_at' => now(),
                'ends_at' => now()->addDays($days),
                'status' => SubscriptionStatus::Active,
                'granted_by' => $admin->id,
                'grant_note' => filled($note) ? trim((string) $note) : null,
                'granted_days' => $days,
            ]);
        });
    }

    /**
     * Retire l'accès offert en cours : il s'arrête tout de suite (ends_at ramené à maintenant, pour que
     * la consommation reste bien attribuée à la période offerte).
     */
    public static function revoke(User $learner): bool
    {
        return DB::transaction(function () use ($learner) {
            $grants = $learner->subscriptions()->granted()
                ->where('status', SubscriptionStatus::Active)
                ->where('ends_at', '>', now())
                ->lockForUpdate()
                ->get();

            foreach ($grants as $grant) {
                $grant->forceFill(['status' => SubscriptionStatus::Cancelled, 'ends_at' => now(), 'revoked_at' => now()])->save();
            }

            // Une séance « Mes cours » ou orale en cours s'arrête avec l'accès (comme au refus d'un paiement).
            if ($grants->isNotEmpty()) {
                $learner->lessons()->where('status', LessonStatus::InProgress)->get()
                    ->each(fn (Lesson $lesson) => $lesson->finish(now(), interrupted: true));
                $learner->courseSessions()->where('status', LessonStatus::InProgress)->get()
                    ->each(fn (CourseSession $session) => $session->finish(now(), interrupted: true));
            }

            return $grants->isNotEmpty();
        });
    }

    /**
     * Un paiement qui commence un abonnement payé met fin à l'accès offert en cours : le nouvel
     * abonnement commence tout de suite, l'apprenant n'attend pas la fin de la démonstration.
     */
    public static function endForPayment(User $learner): void
    {
        $learner->subscriptions()->granted()
            ->where('status', SubscriptionStatus::Active)
            ->where('ends_at', '>', now())
            ->get()
            ->each(fn (Subscription $grant) => $grant->forceFill(['status' => SubscriptionStatus::Expired, 'ends_at' => now()])->save());
    }

    /**
     * Date de fin d'un accès offert maintenant pour ce nombre de jours.
     */
    public static function endsAt(int $days): Carbon
    {
        return now()->addDays($days);
    }

    /**
     * Formule et date de fin pour le rappel de la confirmation (valeurs du formulaire, éventuellement invalides).
     *
     * @return array{plan: string, end: string}
     */
    public static function summary(mixed $planKey, mixed $duration, mixed $customDays): array
    {
        $plan = Plan::find((string) $planKey);
        $days = $duration === 'custom' ? (int) $customDays : (int) $duration;
        $days = max(1, min($days, (int) config('platform.granted_access.max_days')));

        return [
            'plan' => $plan?->name ?? 'choisie',
            'end' => self::endsAt($days)->translatedFormat('j F Y \à H:i'),
        ];
    }
}
