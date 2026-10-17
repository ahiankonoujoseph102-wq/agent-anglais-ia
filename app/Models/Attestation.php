<?php

namespace App\Models;

use App\Enums\Level;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Attestation de niveau délivrée après un bilan de fin de niveau réussi.
 */
#[Fillable(['level_test_id', 'level', 'code', 'issued_at'])]
class Attestation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => Level::class,
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Code de vérification lisible : « TA-B1-7KQ4-M2XD » (préfixe tiré du nom de la plateforme).
     */
    public static function newCode(Level $level): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $random = '';

            for ($i = 0; $i < 8; $i++) {
                $random .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $code = strtoupper(Str::substr(Str::slug(config('platform.name'), ''), 0, 2)).'-'.$level->value.'-'.substr($random, 0, 4).'-'.substr($random, 4);
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }
}
