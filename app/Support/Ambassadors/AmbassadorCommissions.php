<?php

namespace App\Support\Ambassadors;

use App\Enums\CommissionStatus;
use App\Enums\PaymentStatus;
use App\Models\Ambassador;
use App\Models\AmbassadorCommission;
use App\Models\Payment;
use App\Models\User;
use App\Support\Plan;
use Illuminate\Support\Facades\DB;

/**
 * Commissions des ambassadeurs (décisions du fondateur) :
 * - platform.ambassadors.commission_percent (20 %) du prix de la formule, sur le PREMIER paiement validé d'un compte
 *   rattaché, créée au moment où l'administrateur valide ce paiement ;
 * - seulement si ce paiement est validé dans platform.ambassadors.commission_days (90) jours après l'inscription du
 *   compte, et si l'ambassadeur est encore actif (un ambassadeur retiré garde ses commissions déjà dues) ;
 * - une seule commission par compte (garantie par la base) ; aucune sur un renouvellement ni sur un accès offert ;
 * - « Ajouter une commission » à la main pour un paiement hors des quatre pays (abonnement par WhatsApp puis
 *   « Offrir un accès ») ;
 * - due, puis « Commission payée » (Mobile Money, versé par l'administrateur) ou annulée avec un motif (triche).
 */
final class AmbassadorCommissions
{
    public static function percent(): int
    {
        return (int) config('platform.ambassadors.commission_percent');
    }

    /** Commission d'une formule à ce prix : 2 000 → 400, 7 500 → 1 500, 14 000 → 2 800 FCFA (20 %). */
    public static function amountFor(int $price): int
    {
        return (int) round($price * self::percent() / 100);
    }

    /**
     * Paiement validé par l'administrateur : crée la commission si c'est le premier paiement validé d'un compte
     * rattaché à un ambassadeur actif, dans le délai. Rien sinon.
     */
    public static function forValidatedPayment(Payment $payment): ?AmbassadorCommission
    {
        $user = $payment->user()->with('referral.ambassador.user')->first();
        $ambassador = $user?->referral?->ambassador;

        if ($user === null || $ambassador === null || ! $ambassador->isActive() || ! self::withinDelay($user)) {
            return null;
        }

        $earlier = Payment::query()
            ->where('user_id', $user->id)
            ->where('status', PaymentStatus::Validated)
            ->whereKeyNot($payment->id)
            ->exists();

        if ($earlier || AmbassadorCommission::query()->where('user_id', $user->id)->exists()) {
            return null;
        }

        $flags = self::paidByAmbassador($payment, $ambassador) ? ['paid_by_ambassador'] : null;

        return AmbassadorCommission::query()->create([
            'ambassador_id' => $ambassador->id,
            'user_id' => $user->id,
            'payment_id' => $payment->id,
            'source' => 'payment',
            'plan' => $payment->plan,
            'plan_name' => $payment->plan_name,
            'base_amount' => $payment->amount,
            'percent' => self::percent(),
            'amount' => self::amountFor($payment->amount),
            'status' => CommissionStatus::Due,
            'flags' => $flags,
        ]);
    }

    /**
     * Commission ajoutée à la main (paiement hors des quatre pays, par WhatsApp) pour un compte rattaché.
     */
    public static function addManual(User $user, Plan $plan, User $admin, ?string $note): AmbassadorCommission
    {
        return DB::transaction(function () use ($user, $plan, $admin, $note) {
            $user = User::query()->with('referral.ambassador')->lockForUpdate()->findOrFail($user->id);
            $ambassador = $user->referral?->ambassador;

            if ($ambassador === null) {
                throw new AmbassadorRefused('Ce compte n\'est rattaché à aucun ambassadeur : rattachez-le d\'abord.');
            }

            if (AmbassadorCommission::query()->where('user_id', $user->id)->exists()) {
                throw new AmbassadorRefused('Une commission existe déjà pour ce compte : une seule commission par personne.');
            }

            return AmbassadorCommission::query()->create([
                'ambassador_id' => $ambassador->id,
                'user_id' => $user->id,
                'source' => 'manual',
                'plan' => $plan->key,
                'plan_name' => $plan->name,
                'base_amount' => $plan->price,
                'percent' => self::percent(),
                'amount' => self::amountFor($plan->price),
                'status' => CommissionStatus::Due,
                'note' => filled($note) ? mb_substr(trim((string) $note), 0, 200) : null,
                'created_by' => $admin->id,
            ]);
        });
    }

    public static function markPaid(AmbassadorCommission $commission, User $admin): void
    {
        self::change($commission, function (AmbassadorCommission $commission) use ($admin) {
            $commission->forceFill(['status' => CommissionStatus::Paid, 'paid_at' => now(), 'paid_by' => $admin->id])->save();
        });
    }

    public static function cancel(AmbassadorCommission $commission, string $reason): void
    {
        self::change($commission, function (AmbassadorCommission $commission) use ($reason) {
            $commission->forceFill(['status' => CommissionStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => mb_substr(trim($reason), 0, 200)])->save();
        });
    }

    /**
     * Sommes d'un ambassadeur : abonnés payants (commissions non annulées), dues et déjà payées.
     *
     * @return array{subscribers: int, due: int, paid: int}
     */
    public static function totals(Ambassador $ambassador): array
    {
        $rows = $ambassador->commissions()->get(['status', 'amount']);

        return [
            'subscribers' => $rows->where('status', '!=', CommissionStatus::Cancelled)->count(),
            'due' => (int) $rows->where('status', CommissionStatus::Due)->sum('amount'),
            'paid' => (int) $rows->where('status', CommissionStatus::Paid)->sum('amount'),
        ];
    }

    /** Premier paiement validé dans le délai suivant l'inscription du compte. */
    private static function withinDelay(User $user): bool
    {
        return $user->created_at !== null
            && $user->created_at->gte(now()->subDays((int) config('platform.ambassadors.commission_days')));
    }

    /**
     * Numéro de l'expéditeur lu sur la capture = numéro de l'ambassadeur (8 derniers chiffres ; un numéro masqué ou
     * trop court ne compte pas). Seulement un signal : l'administrateur décide.
     */
    private static function paidByAmbassador(Payment $payment, Ambassador $ambassador): bool
    {
        $sender = (string) ($payment->analysis['sender_number'] ?? '');

        if ($sender === '' || preg_match('/[*xX•]/', $sender)) {
            return false;
        }

        $senderDigits = preg_replace('/\D+/', '', $sender);
        $ambassadorDigits = preg_replace('/\D+/', '', (string) $ambassador->user->phone);

        return strlen($senderDigits) >= 8 && substr($senderDigits, -8) === substr($ambassadorDigits, -8);
    }

    /** Une commission ne change que si elle est encore due (jamais deux fois payée, ni payée après annulation). */
    private static function change(AmbassadorCommission $commission, callable $apply): void
    {
        DB::transaction(function () use ($commission, $apply) {
            $commission = AmbassadorCommission::query()->lockForUpdate()->findOrFail($commission->id);

            if (! $commission->isDue()) {
                throw new AmbassadorRefused('Cette commission a déjà été traitée.');
            }

            $apply($commission);
        });
    }
}
