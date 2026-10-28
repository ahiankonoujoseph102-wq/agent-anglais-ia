<?php

namespace App\Support\Ambassadors;

use App\Models\Ambassador;
use Illuminate\Support\Str;

/**
 * Code personnel d'un ambassadeur, facile à dire et à taper : le prénom en majuscules sans accent (8 lettres au
 * plus), puis deux chiffres (« KOFI27 »). Jamais deux fois le même ; lu sans tenir compte des majuscules.
 */
final class AmbassadorCode
{
    public static function generate(string $name): string
    {
        $first = Str::of($name)->trim()->explode(' ')->first() ?? '';
        $letters = substr(preg_replace('/[^A-Z]/', '', strtoupper(Str::ascii((string) $first))) ?? '', 0, 8);
        $base = strlen($letters) >= 2 ? $letters : 'TALK';

        for ($attempt = 0; $attempt < 50; $attempt++) {
            // Deux chiffres d'abord, trois si le prénom est très demandé.
            $code = $base.random_int($attempt < 30 ? 10 : 100, $attempt < 30 ? 99 : 999);

            if (! Ambassador::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        return $base.strtoupper(Str::random(5));
    }

    /** Forme normale d'un code saisi ou lu dans une adresse (majuscules, lettres et chiffres seulement). */
    public static function normalize(?string $code): string
    {
        return substr(preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $code)) ?? '', 0, 20);
    }
}
