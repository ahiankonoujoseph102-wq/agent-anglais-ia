<?php

namespace App\Models;

use App\Enums\CommissionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Commission d'un ambassadeur (App\Support\Ambassadors\AmbassadorCommissions) : jamais plus d'une par compte rattaché.
 */
#[Fillable(['ambassador_id', 'user_id', 'payment_id', 'source', 'plan', 'plan_name', 'base_amount', 'percent', 'amount', 'status', 'flags', 'note', 'created_by'])]
class AmbassadorCommission extends Model
{
    /** Signaux propres au paiement, avec leur libellé (ceux du rattachement restent sur AmbassadorReferral). */
    public const FLAGS = [
        'paid_by_ambassador' => 'Payé depuis le numéro de l\'ambassadeur',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CommissionStatus::class,
            'base_amount' => 'integer',
            'percent' => 'integer',
            'amount' => 'integer',
            'flags' => 'array',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ambassador, $this>
     */
    public function ambassador(): BelongsTo
    {
        return $this->belongsTo(Ambassador::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isDue(): bool
    {
        return $this->status === CommissionStatus::Due;
    }

    /**
     * @return array<int, string>
     */
    public function flagLabels(): array
    {
        return array_values(array_map(fn (string $flag) => self::FLAGS[$flag] ?? $flag, (array) $this->flags));
    }
}
