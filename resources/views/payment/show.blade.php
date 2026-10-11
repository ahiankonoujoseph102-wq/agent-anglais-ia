@php
    use App\Enums\PaymentStatus;
@endphp

<x-layout title="Mon paiement">
    @include('payment.steps', ['current' => 4])

    @if ($payment->status === PaymentStatus::Pending)
        <h1>Paiement en attente de validation</h1>
        <div class="card">
            <p>
                Merci ! Nous vérifions votre paiement. Votre formule sera activée dès sa validation,
                en général dans la journée ({{ \Illuminate\Support\Str::lcfirst(config('platform.contact.hours')) }}).
            </p>
            <a class="btn btn-block btn-whatsapp" href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Prévenir sur WhatsApp</a>
        </div>
    @elseif ($payment->status === PaymentStatus::Validated)
        <h1>Paiement validé</h1>
        <div class="alert alert-success">
            Votre formule {{ $payment->plan_name }} est active
            @if ($payment->subscription)
                du {{ $payment->subscription->starts_at->translatedFormat('j F Y') }}
                au {{ $payment->subscription->ends_at->translatedFormat('j F Y') }}
            @endif.
        </div>
        <a class="btn" href="{{ route('dashboard') }}">Aller à mon espace</a>
    @else
        <h1>Paiement refusé</h1>
        <div class="alert alert-error" role="alert">
            <strong>Motif :</strong> {{ $payment->rejection_reason }}
        </div>
        <a class="btn" href="{{ route('payment.create', $payment->plan) }}">Renvoyer un paiement corrigé</a>
    @endif

    <section class="card next-step">
        <h2>Récapitulatif</h2>
        <dl class="summary">
            <dt>Formule</dt><dd>{{ $payment->plan_name }}</dd>
            <dt>Montant</dt><dd>{{ $payment->formattedAmount() }}</dd>
            <dt>Opérateur</dt><dd>{{ $payment->operator_name }}</dd>
            <dt>Numéro qui a payé</dt><dd>{{ $payment->payer_phone }}</dd>
            <dt>Référence</dt><dd>{{ $payment->reference }}</dd>
            <dt>Envoyé le</dt><dd>{{ $payment->created_at->translatedFormat('j F Y à H:i') }}</dd>
        </dl>
    </section>
</x-layout>
