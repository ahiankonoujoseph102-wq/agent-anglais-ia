<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personne à prévenir quand une place d'ambassadeur se libère (« Me prévenir quand une place se libère »).
 */
#[Fillable(['user_id'])]
class AmbassadorWaitlistEntry extends Model
{
    protected $table = 'ambassador_waitlist';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['notified_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
