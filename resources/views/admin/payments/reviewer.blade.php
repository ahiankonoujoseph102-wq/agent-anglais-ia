{{-- Qui a validé ou refusé un paiement (trace), avec son rôle et la date ; rien tant qu'il est en attente. --}}
@if ($payment->reviewed_at)
    {{ $payment->status === \App\Enums\PaymentStatus::Validated ? 'Validé' : 'Refusé' }} par {{ $payment->reviewer?->name ?? 'un compte supprimé' }}{{ $payment->reviewer ? ' ('.mb_strtolower($payment->reviewer->role->label()).')' : '' }} le {{ $payment->reviewed_at->format('d/m/Y H:i') }}
@else
    <span class="muted">—</span>
@endif
