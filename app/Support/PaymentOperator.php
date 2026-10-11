<?php

namespace App\Support;

/**
 * Un opérateur Mobile Money et le numéro sur lequel payer (config/payment.php).
 */
final readonly class PaymentOperator
{
    public function __construct(
        public string $key,
        public string $name,
        public string $number,
        public string $dialCode,
    ) {}

    /**
     * Le numéro sans indicatif ni espaces, tel qu'on le tape dans le menu Mobile Money.
     */
    public function localNumber(): string
    {
        $digits = preg_replace('/\D+/', '', $this->number);

        return str_starts_with($digits, $this->dialCode) ? substr($digits, strlen($this->dialCode)) : $digits;
    }
}
