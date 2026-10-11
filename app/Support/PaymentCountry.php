<?php

namespace App\Support;

/**
 * Un pays où l'on peut payer, avec ses opérateurs (config/payment.php).
 */
final readonly class PaymentCountry
{
    /**
     * @param  array<string, PaymentOperator>  $operators
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $holder,
        public array $operators,
    ) {}

    /**
     * @return array<string, self>
     */
    public static function all(): array
    {
        $countries = [];

        foreach (config('payment.countries', []) as $code => $country) {
            $operators = [];

            foreach ($country['operators'] as $key => $operator) {
                // L'indicatif est lu sur le numéro lui-même (« +228 … »).
                preg_match('/^\+(\d{1,3})\s/', $operator['number'], $match);
                $operators[$key] = new PaymentOperator($key, $operator['name'], $operator['number'], $match[1] ?? '');
            }

            $countries[$code] = new self($code, $country['name'], $country['holder'], $operators);
        }

        return $countries;
    }

    public static function find(?string $code): ?self
    {
        return self::all()[strtoupper((string) $code)] ?? null;
    }

    public function operator(?string $key): ?PaymentOperator
    {
        return $this->operators[(string) $key] ?? null;
    }

    /**
     * Noms de tous les opérateurs acceptés, tous pays confondus.
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
