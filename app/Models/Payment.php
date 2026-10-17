<?php

namespace App\Models;

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
 * Un paiement Mobile Money déclaré par l'apprenant et validé (ou refusé) par l'administrateur.
 */
#[Fillable([
    'plan', 'plan_name', 'daily_minutes', 'duration_days', 'amount', 'currency',
    'country', 'operator', 'operator_name', 'recipient_number',
    'payer_phone', 'reference', 'reference_key', 'proof_path',
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
     * Forme comparable d'une référence de transaction : majuscules, sans espaces ni ponctuation.
     */
    public static function referenceKey(string $reference): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $reference));
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

    public function formattedAmount(): string
    {
        return Money::format($this->amount, $this->currency);
    }

    /**
     * Valide le paiement et active l'abonnement correspondant.
     * Si un abonnement est encore en cours, le nouveau commence à sa fin.
     */
    public function approve(User $admin): Subscription
    {
        return DB::transaction(function () use ($admin) {
            $payment = self::query()->lockForUpdate()->findOrFail($this->id);

            if (! $payment->isPending()) {
                throw new LogicException('Ce paiement a déjà été traité.');
            }

            $startsAt = $payment->user->subscriptions()
                ->where('status', SubscriptionStatus::Active)
                ->where('ends_at', '>', now())
                ->max('ends_at');

            $startsAt = $startsAt !== null ? Carbon::parse($startsAt) : now();

            $subscription = $payment->user->subscriptions()->create([
                'plan' => $payment->plan,
                'plan_name' => $payment->plan_name,
                'daily_minutes' => $payment->daily_minutes,
                'price' => $payment->amount,
                'currency' => $payment->currency,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addDays($payment->duration_days),
                'status' => SubscriptionStatus::Active,
            ]);

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
     * Refuse le paiement avec un motif, affiché à l'apprenant.
     * La référence est libérée : l'apprenant peut renvoyer un paiement corrigé.
     */
    public function reject(User $admin, string $reason): void
    {
        DB::transaction(function () use ($admin, $reason) {
            $payment = self::query()->lockForUpdate()->findOrFail($this->id);

            if (! $payment->isPending()) {
                throw new LogicException('Ce paiement a déjà été traité.');
            }

            $payment->status = PaymentStatus::Rejected;
            $payment->rejection_reason = $reason;
            $payment->reference_key = null;
            $payment->reviewed_by = $admin->id;
            $payment->reviewed_at = now();
            $payment->save();

            $this->setRawAttributes($payment->getAttributes(), true);
        });
    }
}
