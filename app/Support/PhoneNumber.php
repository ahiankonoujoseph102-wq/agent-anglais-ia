<?php

namespace App\Support;

/**
 * Normalise les numéros de téléphone au format international (+22890123456)
 * pour qu'un même numéro saisi de plusieurs façons corresponde au même compte.
 */
class PhoneNumber
{
    /**
     * Pays où le 0 du début fait partie du numéro (il n'est jamais retiré devant l'indicatif).
     */
    private const ZERO_KEPT = ['CI', 'BJ', 'IT', 'SM', 'VA'];

    /**
     * Renvoie le numéro au format international, ou null s'il est invalide.
     *
     * @param  string|null  $country  pays choisi à l'inscription (code ISO) : un numéro saisi sans indicatif
     *                                reçoit celui de ce pays (le 0 du début est retiré quand il ne fait pas
     *                                partie du numéro, comme en France : 06… devient +33 6…)
     */
    public static function normalize(?string $input, ?string $country = null): ?string
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

        $country = strtoupper((string) $country);
        $dial = Countries::dial($country);

        // Pays choisi autre que le pays par défaut : son indicatif est ajouté à un numéro local.
        if (! $hasPlus && $dial !== null && $dial !== (string) config('platform.phone.default_country_code')) {
            if (! (str_starts_with($digits, $dial) && strlen($digits) - strlen($dial) >= 6)) {
                $local = in_array($country, self::ZERO_KEPT, true) ? $digits : ltrim($digits, '0');
                $digits = $dial.$local;
            }

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
