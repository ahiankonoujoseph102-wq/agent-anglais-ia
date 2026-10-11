<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectPaymentRequest;
use App\Models\Payment;
use App\Support\Money;
use App\Support\PaymentCountry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Validation des paiements Mobile Money déclarés par les apprenants.
 */
class PaymentController extends Controller
{
    public function pending(): View
    {
        return view('admin.payments.pending', [
            'payments' => Payment::query()
                ->where('status', PaymentStatus::Pending)
                ->with('user')
                ->oldest()
                ->get(),
            'countries' => PaymentCountry::all(),
        ]);
    }

    /**
     * Historique de tous les paiements, avec filtres, et total encaissé du mois.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'statut' => ['nullable', Rule::enum(PaymentStatus::class)],
            'pays' => ['nullable', Rule::in(array_keys(PaymentCountry::all()))],
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date'],
        ]);

        $payments = Payment::query()
            ->with('user')
            ->when($filters['statut'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['pays'] ?? null, fn (Builder $query, string $country) => $query->where('country', $country))
            ->when($filters['du'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['au'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        // Encaissé ce mois-ci : paiements validés pendant le mois en cours.
        $monthTotal = (int) Payment::query()
            ->where('status', PaymentStatus::Validated)
            ->whereBetween('reviewed_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        return view('admin.payments.index', [
            'payments' => $payments,
            'filters' => $filters,
            'statuses' => PaymentStatus::cases(),
            'countries' => PaymentCountry::all(),
            'monthTotal' => Money::format($monthTotal),
            'monthLabel' => now()->translatedFormat('F Y'),
        ]);
    }

    /**
     * La capture d'écran, servie depuis le stockage privé (jamais d'URL publique).
     */
    public function proof(Payment $payment): StreamedResponse
    {
        $disk = Payment::proofDisk();

        abort_unless($disk->exists($payment->proof_path), 404);

        return $disk->response($payment->proof_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function approve(Request $request, Payment $payment): RedirectResponse
    {
        try {
            $subscription = $payment->approve($request->user());
        } catch (LogicException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', sprintf(
            'Paiement de %s validé : formule %s active du %s au %s.',
            $payment->user->name,
            $subscription->plan_name,
            $subscription->starts_at->translatedFormat('j F Y'),
            $subscription->ends_at->translatedFormat('j F Y'),
        ));
    }

    public function reject(RejectPaymentRequest $request, Payment $payment): RedirectResponse
    {
        try {
            $payment->reject($request->user(), trim($request->validated('reason')));
        } catch (LogicException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', "Paiement de {$payment->user->name} refusé.");
    }
}
