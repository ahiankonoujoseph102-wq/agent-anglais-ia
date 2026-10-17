<?php

namespace App\Support;

/**
 * Un opérateur Mobile Money (config/payment.php) et, s'ils sont saisis dans les
 * Réglages, le numéro sur lequel payer et le nom de son titulaire.
 */
final readonly class PaymentOperator
{
    /**
     * @param  list<string>  $aliases  mots qui identifient l'opérateur sur une capture
     */
    public function __construct(
        public string $key,
        public string $name,
        public ?string $number,
        public ?string $holder,
        public string $dialCode,
        public array $aliases = [],
    ) {}

    /** Proposé aux apprenants seulement si son numéro de réception est saisi. */
    public function isAvailable(): bool
    {
        return $this->number !== null;
    }

    /**
     * Le numéro sans indicatif ni espaces, tel qu'on le tape dans le menu Mobile Money.
     */
    public function localNumber(): string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $this->number);

        return str_starts_with($digits, $this->dialCode) && strlen($digits) > strlen($this->dialCode) + 6
            ? substr($digits, strlen($this->dialCode))
            : $digits;
    }

    /**
     * Le numéro au format international, pour l'affichage : « +228 72 00 00 00 ».
     */
    public function displayNumber(): string
    {
        $local = $this->localNumber();

        return '+'.$this->dialCode.' '.trim(chunk_split($local, 2, ' '));
    }
}
