<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un appareil d'administrateur abonné aux notifications (Web Push). Le service de notifications du navigateur
 * donne une adresse (endpoint) et deux clés de chiffrement propres à cet appareil : ce ne sont pas des secrets
 * du site, mais elles ne sont jamais affichées ni journalisées.
 */
#[Fillable(['user_id', 'endpoint_hash', 'endpoint', 'public_key', 'auth_token', 'content_encoding', 'device', 'last_sent_at'])]
class PushSubscription extends Model
{
    protected function casts(): array
    {
        return ['last_sent_at' => 'datetime'];
    }

    public static function hashOf(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
