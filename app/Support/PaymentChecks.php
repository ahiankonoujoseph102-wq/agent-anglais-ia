<?php

namespace App\Support;

use App\Models\Payment;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Règles qui décident d'un accès provisoire à partir de ce que l'IA a lu sur la
 * capture (App\Support\Gemini\ProofReader). C'est le code qui décide, jamais le
 * modèle : une seule règle non remplie, ou aucune lecture, et le paiement attend
 * la vérification de l'administrateur.
 *
 * Chaque règle : clé, libellé, réussite et raison (affichée à l'administrateur seulement).
 */
final class PaymentChecks
{
    public const LABELS = [
        'readable' => 'Capture lisible et opérateur reconnu',
        'amount' => 'Montant suffisant',
        'recipient' => 'Bon destinataire',
        'date' => 'Paiement récent',
        'transaction' => 'Numéro de transaction jamais utilisé',
        'image' => 'Capture jamais envoyée',
    ];

    /**
     * @param  list<array{key: string, label: string, passed: bool, reason: string}>  $checks
     */
    private function __construct(
        public readonly array $checks,
        public readonly ?string $transactionKey,
        public readonly bool $hashUsed,
    ) {}

    /**
     * @param  array<string, mixed>|null  $analysis  lecture de l'IA (null : pas de réponse)
     */
    public static function evaluate(?array $analysis, PaymentOperator $operator, int $price, string $hash): self
    {
        $transactionKey = self::transactionKey($analysis['transaction_id'] ?? null);
        $hashUsed = Payment::query()->where('proof_hash', $hash)->exists();

        if ($analysis === null) {
            $checks = [];

            foreach (self::LABELS as $key => $label) {
                $checks[] = ['key' => $key, 'label' => $label, 'passed' => false, 'reason' => 'La capture n\'a pas pu être lue (pas de réponse de l\'IA).'];
            }

            $checks[5] = self::check('image', ! $hashUsed, $hashUsed ? 'Cette image a déjà été envoyée pour un autre paiement.' : 'Image nouvelle.');

            return new self($checks, $transactionKey, $hashUsed);
        }

        return new self([
            self::readable($analysis, $operator),
            self::amount($analysis, $price),
            self::recipient($analysis, $operator),
            self::date($analysis),
            self::transaction($transactionKey),
            self::check('image', ! $hashUsed, $hashUsed ? 'Cette image a déjà été envoyée pour un autre paiement.' : 'Image nouvelle.'),
        ], $transactionKey, $hashUsed);
    }

    public function passed(): bool
    {
        foreach ($this->checks as $check) {
            if (! $check['passed']) {
                return false;
            }
        }

        return true;
    }

    /** Le numéro de transaction à enregistrer : seulement s'il est lu et jamais utilisé. */
    public function transactionKeyIfNew(): ?string
    {
        foreach ($this->checks as $check) {
            if ($check['key'] === 'transaction') {
                return $check['passed'] ? $this->transactionKey : null;
            }
        }

        return null;
    }

    /**
     * Même résultat, quand un autre paiement vient d'enregistrer la même image ou le
     * même numéro de transaction au même instant (index uniques) : ces deux règles échouent.
     */
    public function asDuplicate(): self
    {
        $checks = array_map(fn (array $check) => in_array($check['key'], ['transaction', 'image'], true)
            ? self::check($check['key'], false, 'Envoyé au même moment qu\'un autre paiement avec la même image ou le même numéro de transaction.')
            : $check, $this->checks);

        return new self($checks, null, true);
    }

    /**
     * Forme comparable d'un numéro de transaction : majuscules, sans espaces ni ponctuation.
     */
    public static function transactionKey(mixed $value): ?string
    {
        $key = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', is_scalar($value) ? (string) $value : ''));

        return $key === '' ? null : substr($key, 0, 100);
    }

    /**
     * @return array{key: string, label: string, passed: bool, reason: string}
     */
    private static function check(string $key, bool $passed, string $reason): array
    {
        return ['key' => $key, 'label' => self::LABELS[$key], 'passed' => $passed, 'reason' => $reason];
    }

    private static function simplify(?string $text): string
    {
        $ascii = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $text);

        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $ascii));
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private static function readable(array $analysis, PaymentOperator $operator): array
    {
        if (! $analysis['readable']) {
            return self::check('readable', false, 'L\'IA juge la capture illisible ou incomplète.');
        }

        $read = self::simplify($analysis['operator'] ?? null);

        if ($read === '') {
            return self::check('readable', false, 'Opérateur non lu sur la capture.');
        }

        foreach (array_merge([$operator->name], $operator->aliases) as $alias) {
            $alias = self::simplify($alias);

            if ($alias !== '' && str_contains($read, $alias)) {
                return self::check('readable', true, 'Opérateur lu : '.$analysis['operator'].'.');
            }
        }

        return self::check('readable', false, 'Opérateur lu « '.$analysis['operator'].' », différent de '.$operator->name.'.');
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private static function amount(array $analysis, int $price): array
    {
        $amount = $analysis['amount'] ?? null;

        if ($amount === null) {
            return self::check('amount', false, 'Montant non lu sur la capture.');
        }

        $currency = strtoupper(trim((string) preg_replace('/[\s.]+/', ' ', (string) ($analysis['currency'] ?? ''))));
        $currencies = array_map(fn ($value) => strtoupper($value), config('payment.provisional.currencies', []));

        if (! in_array($currency, $currencies, true)) {
            return self::check('amount', false, 'Devise lue « '.($analysis['currency'] ?? 'aucune').' », pas le franc CFA.');
        }

        if ($amount < $price) {
            return self::check('amount', false, 'Montant lu '.Money::format($amount).', inférieur au prix '.Money::format($price).'.');
        }

        return self::check('amount', true, 'Montant lu : '.Money::format($amount).'.');
    }

    /**
     * Le numéro lu doit être celui des Réglages (8 derniers chiffres, sans indicatif).
     * Un numéro masqué (« 90****56 ») ne suffit pas.
     *
     * @param  array<string, mixed>  $analysis
     */
    private static function recipient(array $analysis, PaymentOperator $operator): array
    {
        $read = (string) ($analysis['recipient_number'] ?? '');

        if ($read === '') {
            return self::check('recipient', false, 'Numéro du destinataire non lu sur la capture.');
        }

        if (preg_match('/[*xX•#]/', $read)) {
            return self::check('recipient', false, 'Numéro du destinataire masqué sur la capture ('.$read.') : à vérifier.');
        }

        $digits = (string) preg_replace('/\D+/', '', $read);
        $expected = $operator->localNumber();

        if (strlen($digits) >= 8 && strlen($expected) >= 8 && substr($digits, -8) === substr($expected, -8)) {
            return self::check('recipient', true, 'Numéro du destinataire : '.$read.'.');
        }

        return self::check('recipient', false, 'Numéro lu '.$read.', différent du numéro enregistré pour '.$operator->name.'.');
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private static function date(array $analysis): array
    {
        $read = $analysis['date_time'] ?? null;

        if ($read === null) {
            return self::check('date', false, 'Date du paiement non lue sur la capture.');
        }

        try {
            // L'heure affichée par le téléphone est celle du pays : fuseau de l'application.
            $date = Carbon::parse((string) preg_replace('/(Z|[+-]\d{2}:?\d{2})$/', '', $read), config('app.timezone'));
        } catch (Throwable) {
            return self::check('date', false, 'Date lue « '.$read.' » incompréhensible.');
        }

        if ($date->greaterThan(now()->addMinutes((int) config('payment.provisional.future_tolerance_minutes')))) {
            return self::check('date', false, 'Date lue '.$date->format('d/m/Y H:i').' : dans le futur.');
        }

        if ($date->lessThan(now()->subHours((int) config('payment.provisional.max_age_hours')))) {
            return self::check('date', false, 'Date lue '.$date->format('d/m/Y H:i').' : plus de '.config('payment.provisional.max_age_hours').' heures.');
        }

        return self::check('date', true, 'Payé le '.$date->format('d/m/Y à H:i').'.');
    }

    private static function transaction(?string $key): array
    {
        if ($key === null || strlen($key) < 4) {
            return self::check('transaction', false, 'Numéro de transaction non lu sur la capture.');
        }

        // Comparé aussi aux références déclarées dans l'ancien parcours.
        $used = Payment::query()->where('transaction_key', $key)->orWhere('reference_key', $key)->exists();

        return $used
            ? self::check('transaction', false, 'Numéro de transaction '.$key.' déjà utilisé pour un autre paiement.')
            : self::check('transaction', true, 'Numéro de transaction : '.$key.'.');
    }
}
