<?php

namespace App\Support;

use DateTimeZone;

/**
 * Tous les pays (resources/data/countries.php : nom en français, indicatif, pays francophone), pour l'inscription
 * ouverte à tous. Les quatre pays de paiement Mobile Money (config/payment.php) restent les seuls où l'on paie
 * en ligne ; ailleurs, l'abonnement passe par WhatsApp puis « Offrir un accès ».
 */
final class Countries
{
    /** « Autre pays » (code ISO réservé à un usage libre). */
    public const OTHER = 'ZZ';

    /**
     * @return array<string, array{name: string, dial: string, francophone: bool}>
     */
    public static function all(): array
    {
        static $countries = null;

        return $countries ??= require resource_path('data/countries.php');
    }

    public static function exists(?string $code): bool
    {
        return $code === self::OTHER || isset(self::all()[strtoupper((string) $code)]);
    }

    /**
     * Nom en français ; pour les pays de paiement, celui de config/payment.php.
     */
    public static function name(?string $code): string
    {
        $code = strtoupper((string) $code);

        if ($code === self::OTHER) {
            return 'Autre pays';
        }

        return (string) (config("payment.countries.{$code}.name") ?? self::all()[$code]['name'] ?? 'Pays inconnu');
    }

    public static function dial(?string $code): ?string
    {
        return self::all()[strtoupper((string) $code)]['dial'] ?? null;
    }

    /** Pays où l'on paie par Mobile Money sur le site (config/payment.php). */
    public static function hasOnlinePayment(?string $code): bool
    {
        return array_key_exists(strtoupper((string) $code), (array) config('payment.countries'));
    }

    /**
     * Liste de l'inscription, en trois groupes : les pays de paiement, puis les autres pays francophones, puis
     * tous les autres (ordre alphabétique français), et enfin « Autre pays ».
     *
     * @return array<string, array<string, string>>
     */
    public static function choices(): array
    {
        $payment = [];

        foreach (array_keys((array) config('payment.countries')) as $code) {
            $payment[$code] = self::name($code);
        }

        $francophone = [];
        $others = [];

        foreach (self::all() as $code => $country) {
            if (isset($payment[$code])) {
                continue;
            }

            if ($country['francophone']) {
                $francophone[$code] = $country['name'];
            } else {
                $others[$code] = $country['name'];
            }
        }

        return [
            'Pays où l\'on peut payer par Mobile Money' => $payment,
            'Autres pays francophones' => $francophone,
            'Tous les autres pays' => $others + [self::OTHER => 'Autre pays'],
        ];
    }

    /**
     * Pays d'un numéro international (indicatif le plus long qui correspond) ; « 1 » : États-Unis, « 7 » : Russie.
     */
    public static function forPhone(?string $phone): ?string
    {
        $digits = ltrim((string) $phone, '+');

        foreach (array_keys((array) config('payment.countries')) as $code) {
            $dial = (string) config("payment.countries.{$code}.dial_code");

            if ($dial !== '' && str_starts_with($digits, $dial)) {
                return $code;
            }
        }

        $best = null;
        $length = 0;
        $preferred = ['1' => 'US', '7' => 'RU', '44' => 'GB', '262' => 'RE', '590' => 'GP', '39' => 'IT', '358' => 'FI', '212' => 'MA', '61' => 'AU', '47' => 'NO'];

        foreach (self::all() as $code => $country) {
            $dial = $country['dial'];

            if (strlen($dial) > $length && str_starts_with($digits, $dial)) {
                $best = $preferred[$dial] ?? $code;
                $length = strlen($dial);
            }
        }

        return $best;
    }

    /**
     * Fuseau horaire du pays (salutation « Bonjour » ou « Bonsoir ») : celui de config/payment.php pour les pays de
     * paiement, sinon le premier fuseau connu de PHP pour ce pays, sinon celui du site.
     */
    public static function timezone(?string $code): string
    {
        $code = strtoupper((string) $code);

        if (filled($zone = config("payment.countries.{$code}.timezone"))) {
            return (string) $zone;
        }

        if ($code !== '' && $code !== self::OTHER && isset(self::all()[$code])) {
            $zones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $code);

            if ($zones !== []) {
                return $zones[0];
            }
        }

        return (string) config('app.timezone');
    }
}
