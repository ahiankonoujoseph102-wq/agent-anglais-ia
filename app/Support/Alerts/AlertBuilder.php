<?php

namespace App\Support\Alerts;

use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Compose les alertes de paiement : prénom et nom, pays, formule, montant, moyen de paiement, état, heure de
 * Lomé et lien direct dans l'administration. Le numéro de transaction, la capture et le numéro de téléphone ne
 * sont jamais repris.
 */
final class AlertBuilder
{
    /** Raison courte, sans jargon, de chaque règle qui peut retenir un paiement. */
    private const SHORT_REASONS = [
        'readable' => 'la capture n\'est pas claire ou ne montre pas le bon moyen de paiement',
        'amount' => 'le montant lu est trop faible',
        'recipient' => 'le numéro qui a reçu l\'argent n\'est pas le bon',
        'date' => 'la date du paiement semble trop ancienne',
        'transaction' => 'le numéro de transaction manque ou a déjà servi',
        'image' => 'cette capture a déjà été envoyée',
    ];

    /**
     * Capture envoyée : accès provisoire ouvert, ou paiement qui attend la vérification.
     */
    public static function captured(Payment $payment): AlertMessage
    {
        $payment->loadMissing('user', 'subscription');
        $provisional = $payment->provisionalSubscription() !== null;
        $amount = Money::format($payment->amount, $payment->currency);
        $state = $provisional
            ? 'Accès provisoire de '.(int) config('payment.provisional.hours').' heures ouvert'
            : 'En attente de votre vérification : '.self::shortReason($payment);

        return new AlertMessage(
            kind: 'payment_captured',
            title: 'Nouveau paiement : '.$amount,
            body: self::shortName($payment->user).' · '.$payment->plan_name.' · '.$payment->operator_name.' · '
                .($provisional ? 'accès provisoire ouvert' : 'à vérifier'),
            url: self::paymentUrl($payment),
            buttonLabel: 'Voir le paiement',
            details: self::paymentDetails($payment, $state),
            paymentId: $payment->id,
            tag: 'paiement-'.$payment->id,
        );
    }

    /**
     * L'accès provisoire se termine bientôt sans validation.
     */
    public static function expiring(Payment $payment): AlertMessage
    {
        $payment->loadMissing('user', 'subscription');
        $until = $payment->subscription?->provisional_until;
        $left = $until !== null ? max(1, (int) round(now()->diffInMinutes($until, false))) : 0;
        $amount = Money::format($payment->amount, $payment->currency);

        return new AlertMessage(
            kind: 'payment_expiring',
            title: 'À valider vite : '.$amount,
            body: self::shortName($payment->user).' : l\'accès provisoire se termine dans '.self::minutes($left).'.',
            url: self::paymentUrl($payment),
            buttonLabel: 'Voir le paiement',
            details: self::paymentDetails($payment, 'Accès provisoire : il se termine dans '.self::minutes($left).' sans votre validation'),
            paymentId: $payment->id,
            tag: 'paiement-'.$payment->id,
        );
    }

    /**
     * Un apprenant d'un pays sans paiement en ligne touche « Écrire sur WhatsApp » pour payer.
     */
    public static function whatsappClick(User $user, string $planName, string $amount): AlertMessage
    {
        return new AlertMessage(
            kind: 'whatsapp_click',
            title: 'Paiement demandé sur WhatsApp : '.$amount,
            body: self::fullName($user).' · '.$user->countryName().' · '.$planName.' : il vous écrit sur WhatsApp.',
            url: route('admin.users.show', $user),
            buttonLabel: 'Voir l\'apprenant',
            details: [
                ['Apprenant', self::fullName($user)],
                ['Pays', $user->countryName()],
                ['Formule', $planName],
                ['Montant', $amount],
                ['Moyen de paiement', 'WhatsApp (pas de paiement en ligne dans ce pays)'],
                ['État', 'Il vous écrit sur WhatsApp : ouvrez-lui un accès après le paiement'],
                ['Heure', self::now()],
            ],
            tag: 'whatsapp-'.$user->id,
        );
    }

    /**
     * Alerte d'essai : montre ce que l'on reçoit, sans rien de réel.
     */
    public static function test(): AlertMessage
    {
        return new AlertMessage(
            kind: 'test',
            title: 'Alerte d\'essai '.config('platform.name'),
            body: 'Tout fonctionne : vous recevrez ainsi chaque nouveau paiement.',
            url: route('admin.payments.pending'),
            buttonLabel: 'Ouvrir l\'administration',
            details: [['Heure', self::now()]],
            tag: 'essai',
        );
    }

    public static function paymentUrl(Payment $payment): string
    {
        return route('admin.payments.pending').'#paiement-'.$payment->id;
    }

    /**
     * La raison courte pour laquelle un paiement attend la vérification de l'administrateur.
     */
    public static function shortReason(Payment $payment): string
    {
        if ($payment->analysis === null) {
            return 'la capture n\'a pas pu être lue automatiquement';
        }

        foreach ((array) $payment->checks as $check) {
            if (! ($check['passed'] ?? true)) {
                return self::SHORT_REASONS[$check['key'] ?? ''] ?? 'une vérification automatique n\'a pas abouti';
            }
        }

        return 'à vérifier';
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private static function paymentDetails(Payment $payment, string $state): array
    {
        return [
            ['Apprenant', self::fullName($payment->user)],
            ['Pays', $payment->user->countryName()],
            ['Formule', $payment->plan_name],
            ['Montant', Money::format($payment->amount, $payment->currency)],
            ['Moyen de paiement', $payment->operator_name],
            ['État', $state],
            ['Heure', self::now()],
        ];
    }

    /** Prénom et nom, comme l'apprenant les a écrits. */
    private static function fullName(User $user): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $user->name) ?? '');
    }

    private static function shortName(User $user): string
    {
        return self::fullName($user);
    }

    /** « 21 octobre 2026 à 14 h 05 », heure de Lomé. */
    private static function now(): string
    {
        return Carbon::now(config('app.timezone'))->translatedFormat('j F Y \à H \h i');
    }

    private static function minutes(int $minutes): string
    {
        if ($minutes >= 60) {
            $hours = intdiv($minutes, 60);
            $rest = $minutes % 60;

            return $hours.($hours > 1 ? ' heures' : ' heure').($rest > 0 ? ' '.$rest.' minutes' : '');
        }

        return $minutes.($minutes > 1 ? ' minutes' : ' minute');
    }
}
