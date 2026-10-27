<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Provenance d'un inscrit (première page vue, site d'origine, utm_*) : voir App\Support\Origins.
 */
#[Fillable(['user_id', 'landing_path', 'referrer_host', 'channel', 'utm_source', 'utm_medium', 'utm_campaign', 'first_seen_at'])]
class UserOrigin extends Model
{
    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
