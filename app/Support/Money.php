<?php

namespace App\Support;

class Money
{
    /**
     * Formate un montant à la française : « 5 000 FCFA ».
     */
    public static function format(int $amount, ?string $currency = null): string
    {
        // Espace insécable fine entre les milliers, insécable avant la devise.
        return number_format($amount, 0, ',', "\u{202F}")."\u{00A0}".($currency ?? config('platform.currency'));
    }
}
