<?php

namespace App\Models;

use App\Enums\LessonStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Support\Money;
use App\Support\PrivateStorage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Un paiement Mobile Money envoyé par l'apprenant (capture de la confirmation),
 * lu par l'IA, puis validé (ou refusé) par l'administrateur.
 *
 * Si toutes les règles passent (App\Support\PaymentChecks), un accès provisoire
 * de 24 heures est ouvert tout de suite (grantProvisional) ; le paiement reste
 * « en attente » jusqu'à la décision de l'administrateur.
 */
#[Fillable([
    'plan', 'plan_name', 'daily_minutes', 'duration_days', 'amount', 'currency',
    'country', 'operator', 'operator_name', 'recipient_number',
    'payer_phone', 'reference', 'reference_key', 'proof_path',
    'proof_hash', 'transaction_key', 'analysis', 'checks',
])]
class Payment extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PaymentStatus::Pending->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'daily_minutes' => 'integer',
            'duration_days' => 'integer',
            'status' => PaymentStatus::class,
            'reviewed_at' => 'datetime',
            'analysis' => 'array',
            'checks' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Verrou « un seul paiement en attente par apprenant », garanti par un index unique.
        static::saving(function (Payment $payment) {
            $payment->pending_user_id = $payment->status === PaymentStatus::Pending ? $payment->user_id : null;
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Disque privé des captures d'écran.
     */
    public static function proofDisk(): FilesystemAdapter
    {
        return PrivateStorage::disk();
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    /** Toutes les règles de lecture de la capture sont remplies. */
    public function checksPassed(): bool
    {
        $checks = $this->checks ?? [];

        return $checks !== [] && collect($checks)->every(fn (array $check) => $check['passed'] ?? false);
    }

    /**
     * L'accès provisoire encore ouvert pour ce paiement, s'il y en a un.
     */
    public function provisionalSubscription(): ?Subscription
    {
        $subscription = $this->subscription;

        return $subscription?->isProvisional() && $subscription->provisional_until?->isFuture() ? $subscription : null;
    }

    /**
     * Ouvre l'accès provisoire (toutes les règles sont passées) : un abonnement au
     * statut « provisoire » pendant payment.provisional.hours heures. Ses dates sont
     * déjà les définitives : la validation ne fait perdre aucun jour.
     * Un seul accès provisoire à la fois par compte.
     */
    public function grantProvisional(): ?Subscription
    {
        return DB::transaction(function () {
            $payment = self::query()->lockForUpdate()->findOrFail($this->id);
            $user = User::query()->lockForUpdate()->findOrFail($payment->user_id);

            $alreadyProvisional = $user->subscriptions()
                ->where('status', SubscriptionStatus::Provisional)
                ->where('provisional_until', '>', now())
                ->exists();

            if (! $payment->isPending() || $payment->subscription_id !== null || $alreadyProvisional) {
                return null;
            }

            $subscription = $this->createSubscription($payment, SubscriptionStatus::Provisional, now()->addHours((int) config('payment.provisional.hours')));

            $payment->subscription_id = $subscription->id;
            $payment->save();

            $this->setRawAttributes($payment->getAttributes(), true);
            $this->unsetRelation('subscription');

            return $subscription;
        });
    }

    /**
     * Nouvel abonnement pour ce paiement ; s'il en reste un en cours, il commence à sa fin.
     */
    private function createSubscription(self $payment, SubscriptionStatus $status, ?Carbon $provisionalUntil = null): Subscription
    {
        $startsAt = $payment->user->subscriptions()
            ->where('status', SubscriptionStatus::Active)
            ->where('ends_at', '>', now())
            ->max('ends_at');

        $startsAt = $startsAt !== null ? Carbon::parse($startsAt) : now();

        return $payment->user->subscriptions()->create([
            'plan' => $payment->plan,
            'plan_name' => $payment->plan_name,
            'daily_minutes' => $payment->daily_minutes,
            'price' => $payment->amount,
            'currency' => $payment->currency,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays($payment->duration_days),
            'provisional_until' => $provisionalUntil,
            'status' => $status,
        ]);
    }

    public function formattedAmount(): string
    {
        return Money::format($this->amount, $this->currency);
    }

    /**
     * Valide le paiement et active l'abonnement correspondant.
     * Après un accès provisoire (en cours ou terminé), le même abonnement devient
     * définitif, sans changer ses dates : la durée compte depuis le début de l'accès
     * provisoire, aucun jour n'est perdu. Sinon, un abonnement est créé ; s'il en
     * reste un en cours, le nouveau commence à sa fin.
     */
    public function approve(User $admin): Subscription
    {
        return DB::transaction(function () use ($admin) {
            $payment = self::query()->lockForUpdate()->findOrFail($this->id);

            if (! $payment->isPending()) {
                throw new LogicException('Ce paiement a déjà été traité.');
            }

            $subscription = $payment->subscription;

            if ($subscription !== null && in_array($subscription->status, [SubscriptionStatus::Provisional, SubscriptionStatus::Suspended], true)) {
                $subscription->status = $subscription->ends_at->isFuture() ? SubscriptionStatus::Active : SubscriptionStatus::Expired;
                $subscription->provisional_until = null;
                $subscription->save();
            } else {
                $subscription = $this->createSubscription($payment, SubscriptionStatus::Active);
            }

            $payment->subscription_id = $subscription->id;
            $payment->status = PaymentStatus::Validated;
            $payment->reviewed_by = $admin->id;
            $payment->reviewed_at = now();
            $payment->save();

            $this->setRawAttributes($payment->getAttributes(), true);

            return $subscription;
        });
    }

    /**
     * Refuse le paiement (motif facultatif, affiché à l'apprenant). L'accès
     * provisoire est coupé aussitôt, séance en cours comprise.
     * L'empreinte de l'image et le numéro de transaction restent enregistrés :
     * ils ne peuvent pas resservir.
     */
    public function reject(User $admin, ?string $reason = null): void
    {
        DB::transaction(function () use ($admin, $reason) {
            $payment = self::query()->lockForUpdate()->findOrFail($this->id);

            if (! $payment->isPending()) {
                throw new LogicException('Ce paiement a déjà été traité.');
            }

            if ($subscription = $payment->subscription) {
                $subscription->update(['status' => SubscriptionStatus::Cancelled, 'provisional_until' => null]);

                $subscription->lessons()
                    ->where('status', LessonStatus::InProgress)
                    ->get()
                    ->each(fn (Lesson $lesson) => $lesson->finish(now(), interrupted: true));
            }

            $payment->status = PaymentStatus::Rejected;
            $payment->rejection_reason = filled($reason) ? $reason : null;
            $payment->reference_key = null;
            $payment->reviewed_by = $admin->id;
            $payment->reviewed_at = now();
            $payment->save();

            $this->setRawAttributes($payment->getAttributes(), true);
        });
    }
}
