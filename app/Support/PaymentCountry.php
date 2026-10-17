<?php

namespace App\Support;

/**
 * Un pays où l'on peut payer, avec ses opérateurs (config/payment.php) et leurs
 * numéros de réception (Réglages de l'administration, App\Support\SiteSettings).
 */
final readonly class PaymentCountry
{
    /**
     * @param  array<string, PaymentOperator>  $operators
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $dialCode,
        public array $operators,
    ) {}

    /**
     * Tous les pays et opérateurs configurés, avec ou sans numéro saisi.
     *
     * @return array<string, self>
     */
    public static function all(): array
    {
        $countries = [];

        foreach (config('payment.countries', []) as $code => $country) {
            $operators = [];

            foreach ($country['operators'] as $key => $operator) {
                $operators[$key] = new PaymentOperator(
                    $key,
                    $operator['name'],
                    SiteSettings::operatorNumber($code, $key),
                    SiteSettings::operatorHolder($code, $key),
                    (string) $country['dial_code'],
                    $operator['aliases'] ?? [],
                );
            }

            $countries[$code] = new self($code, $country['name'], (string) $country['dial_code'], $operators);
        }

        return $countries;
    }

    /**
     * Pays proposés aux apprenants : ceux qui ont au moins un opérateur avec un numéro saisi,
     * avec seulement ces opérateurs.
     *
     * @return array<string, self>
     */
    public static function available(): array
    {
        $countries = [];

        foreach (self::all() as $code => $country) {
            $operators = array_filter($country->operators, fn (PaymentOperator $operator) => $operator->isAvailable());

            if ($operators !== []) {
                $countries[$code] = new self($country->code, $country->name, $country->dialCode, $operators);
            }
        }

        return $countries;
    }

    public static function find(?string $code): ?self
    {
        return self::all()[strtoupper((string) $code)] ?? null;
    }

    public static function findAvailable(?string $code): ?self
    {
        return self::available()[strtoupper((string) $code)] ?? null;
    }

    /**
     * Pays proposé dont l'indicatif est celui du téléphone de l'apprenant (« +228… » : Togo),
     * ou null si aucun ne correspond.
     */
    public static function forPhone(?string $phone): ?self
    {
        $digits = ltrim((string) $phone, '+');

        foreach (self::available() as $country) {
            if ($country->dialCode !== '' && str_starts_with($digits, $country->dialCode)) {
                return $country;
            }
        }

        return null;
    }

    public function operator(?string $key): ?PaymentOperator
    {
        return $this->operators[(string) $key] ?? null;
    }

    /**
     * Noms de tous les opérateurs configurés, tous pays confondus.
     *
     * @return list<string>
     */
    public static function operatorNames(): array
    {
        $names = [];

        foreach (self::all() as $country) {
            foreach ($country->operators as $operator) {
                $names[] = $operator->name;
            }
        }

        return array_values(array_unique($names));
    }
}
