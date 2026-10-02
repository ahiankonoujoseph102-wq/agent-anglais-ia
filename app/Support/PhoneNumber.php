<?php

namespace App\Support;

/**
 * Normalise les numéros de téléphone au format international (+22890123456)
 * pour qu'un même numéro saisi de plusieurs façons corresponde au même compte.
 */
class PhoneNumber
{
    /**
     * Renvoie le numéro au format international, ou null s'il est invalide.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $input = trim($input);
        $hasPlus = str_starts_with($input, '+');
        $digits = preg_replace('/\D+/', '', $input);

        if ($digits === '') {
            return null;
        }

        if (! $hasPlus && str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
            $hasPlus = true;
        }

        if (! $hasPlus) {
            $countryCode = (string) config('platform.phone.default_country_code');
            $localLength = (int) config('platform.phone.local_length');

            if (strlen($digits) === $localLength) {
                $digits = $countryCode.$digits;
            } elseif (! (str_starts_with($digits, $countryCode) && strlen($digits) === strlen($countryCode) + $localLength)) {
                return null;
            }
        }

        // Norme E.164 : 15 chiffres au maximum, indicatif compris.
        if (strlen($digits) < 8 || strlen($digits) > 15 || str_starts_with($digits, '0')) {
            return null;
        }

        return '+'.$digits;
    }
}
