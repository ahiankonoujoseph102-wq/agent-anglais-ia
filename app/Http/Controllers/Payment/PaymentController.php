<?php

namespace App\Http\Controllers\Payment;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Payment;
use App\Support\Money;
use App\Support\PaymentCountry;
use App\Support\Plan;
use App\Support\WhatsApp;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Parcours de paiement manuel en 4 étapes :
 * 1. formule, 2. pays et coordonnées, 3. déclaration du paiement, 4. attente de validation.
 */
class PaymentController extends Controller
{
    private const ALREADY_PENDING = 'Vous avez déjà un paiement en attente de validation. Attendez la réponse avant d\'en envoyer un autre.';

    /**
     * Étape 1 : choix de la formule.
     */
    public function plans(Request $request): View|RedirectResponse
    {
        if ($pending = $request->user()->pendingPayment()->first()) {
            return to_route('payment.show', $pending);
        }

        return view('payment.plans', [
            'plans' => Plan::all(),
            'subscription' => $request->user()->activeSubscription,
            'rejected' => $this->lastRejected($request),
        ]);
    }

    /**
     * Étapes 2 et 3 : pays, coordonnées de paiement, puis formulaire de déclaration.
     */
    public function create(Request $request, string $plan): View|RedirectResponse
    {
        $plan = Plan::find($plan) ?? abort(404);

        if ($pending = $request->user()->pendingPayment()->first()) {
            return to_route('payment.show', $pending);
        }

        $country = PaymentCountry::find($request->query('pays') ?? old('country'));

        return view('payment.create', [
            'plan' => $plan,
            'countries' => PaymentCountry::all(),
            'country' => $country,
            'maxMegabytes' => (int) (config('payment.proofs.max_kilobytes') / 1024),
        ]);
    }

    /**
     * Étape 3 : enregistrement de la déclaration et de la capture.
     */
    public function store(StorePaymentRequest $request, string $plan): RedirectResponse
    {
        $plan = Plan::find($plan) ?? abort(404);
        $user = $request->user();

        if ($user->pendingPayment()->exists()) {
            return to_route('payment.show', $user->pendingPayment()->first())->with('status', self::ALREADY_PENDING);
        }

        $country = PaymentCountry::find($request->validated('country'));
        $operator = $country->operator($request->validated('operator'));
        $path = Payment::proofDisk()->putFile(config('payment.proofs.directory').'/'.now()->format('Y/m'), $request->file('proof'));

        try {
            $payment = $user->payments()->create([
                'plan' => $plan->key,
                'plan_name' => $plan->name,
                'daily_minutes' => $plan->dailyMinutes,
                'duration_days' => $plan->durationDays,
                'amount' => $plan->price,
                'currency' => config('platform.currency'),
                'country' => $country->code,
                'operator' => $operator->key,
                'operator_name' => $operator->name,
                'recipient_number' => $operator->number,
                'payer_phone' => $request->validated('payer_phone'),
                'reference' => $request->validated('reference'),
                'reference_key' => $request->validated('reference_key'),
                'proof_path' => $path,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Deux envois simultanés : la base garantit les deux règles.
            Payment::proofDisk()->delete($path);

            if ($user->pendingPayment()->exists()) {
                return to_route('payment.show', $user->pendingPayment()->first())->with('status', self::ALREADY_PENDING);
            }

            throw ValidationException::withMessages(['reference' => 'Cette référence de transaction a déjà été envoyée.']);
        }

        return to_route('payment.show', $payment);
    }

    /**
     * Étape 4 : état du paiement (en attente, validé ou refusé).
     */
    public function show(Request $request, Payment $payment): View
    {
        abort_unless($payment->user_id === $request->user()->id, 404);

        $user = $request->user();
        $message = implode("\n", [
            'Bonjour, je viens de payer mon abonnement '.config('platform.name').'.',
            'Nom : '.$user->name,
            'Compte : '.$user->phone,
            'Formule : '.$payment->plan_name,
            'Montant : '.Money::format($payment->amount, $payment->currency),
            'Opérateur : '.$payment->operator_name,
            'Référence : '.$payment->reference,
        ]);

        return view('payment.show', [
            'payment' => $payment,
            'whatsappUrl' => WhatsApp::url(config('payment.whatsapp'), $message),
        ]);
    }

    private function lastRejected(Request $request): ?Payment
    {
        $latest = $request->user()->latestPayment()->first();

        return $latest?->status === PaymentStatus::Rejected ? $latest : null;
    }
}
