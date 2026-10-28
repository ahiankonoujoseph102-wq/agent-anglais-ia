<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Compte rattaché à un ambassadeur (App\Support\Ambassadors\AmbassadorReferrals).
 */
#[Fillable(['ambassador_id', 'user_id', 'source', 'flags', 'ip_hash'])]
class AmbassadorReferral extends Model
{
    /** Façons d'être rattaché, avec leur libellé. */
    public const SOURCES = [
        'code' => 'code tapé à l\'inscription',
        'link' => 'lien ou code QR',
        'admin' => 'rattaché par l\'administrateur',
    ];

    /** Signaux de triche, avec leur libellé (jamais un blocage : l'administrateur décide). */
    public const FLAGS = [
        'same_device' => 'Inscrit depuis le téléphone ou l\'ordinateur de l\'ambassadeur',
        'same_network' => 'Même réseau internet que l\'ambassadeur',
        'shared_network' => 'Même réseau internet qu\'un autre inscrit de cet ambassadeur',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['flags' => 'array'];
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

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }

    /**
     * @return array<int, string>
     */
    public function flagLabels(): array
    {
        return array_values(array_map(fn (string $flag) => self::FLAGS[$flag] ?? $flag, (array) $this->flags));
    }
}
