@php
    use App\Enums\PaymentStatus;
@endphp

<x-layout title="Mon paiement">
    @include('payment.steps', ['current' => 4])

    <div class="step-panel">
        @if ($provisional)
            <div class="status-head">
                <span class="icon-bubble icon-bubble-primary"><x-icon name="check-circle" /></span>
                <h1>Vous pouvez commencer</h1>
            </div>
            <div class="alert alert-success">
                <x-icon name="check-circle" />
                <div>
                    Votre capture a bien été lue. Votre formule {{ $payment->plan_name }} est ouverte
                    <strong>pour {{ $provisionalLeft }}</strong>, le temps que notre équipe vérifie votre paiement.
                    Une fois vérifié, il reste valable jusqu'au {{ $provisional->ends_at->translatedFormat('j F Y') }}.
                </div>
            </div>
            <a class="btn btn-accent btn-block btn-lg" href="{{ route('lesson.show') }}"><x-icon name="mic" /> Parler avec {{ config('avatar.model.name') }}</a>
            <a class="btn btn-light btn-block next-step" href="{{ route('dashboard') }}">Aller à mon espace</a>
        @elseif ($payment->status === PaymentStatus::Pending)
            <div class="status-head">
                <span class="icon-bubble icon-bubble-accent"><x-icon name="clock" /></span>
                <h1>Nous vérifions votre paiement</h1>
            </div>
            <div class="card">
                <p>
                    Merci ! Votre capture est bien arrivée. Notre équipe vérifie votre paiement
                    ({{ \Illuminate\Support\Str::lcfirst($hours) }}) et vous serez informé ici, ainsi que dans votre espace,
                    dès que votre formule est activée.
                </p>
                @if ($whatsappUrl)
                    <p>Pour aller plus vite, vous pouvez aussi nous prévenir sur WhatsApp.</p>
                    <a class="btn btn-block btn-whatsapp btn-lg" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Prévenir sur WhatsApp</a>
                @endif
            </div>
        @elseif ($payment->status === PaymentStatus::Validated)
            <div class="status-head">
                <span class="icon-bubble icon-bubble-primary"><x-icon name="badge-check" /></span>
                <h1>Paiement validé</h1>
            </div>
            <div class="alert alert-success">
                <x-icon name="check-circle" />
                <div>
                    Votre formule {{ $payment->plan_name }} est active
                    @if ($payment->subscription)
                        du {{ $payment->subscription->starts_at->translatedFormat('j F Y') }}
                        au {{ $payment->subscription->ends_at->translatedFormat('j F Y') }}
                    @endif.
                </div>
            </div>
            <a class="btn btn-block btn-lg" href="{{ route('dashboard') }}">Aller à mon espace <x-icon name="arrow-right" /></a>
        @else
            <div class="status-head">
                <span class="icon-bubble icon-bubble-danger"><x-icon name="alert" /></span>
                <h1>Paiement non validé</h1>
            </div>
            <div class="card">
                <p>Nous n'avons pas pu valider ce paiement. Écrivez-nous sur WhatsApp : nous regarderons ensemble ce qui s'est passé.</p>
                @if ($payment->rejection_reason)
                    <p><strong>Motif :</strong> {{ $payment->rejection_reason }}</p>
                @endif
                @if ($whatsappUrl)
                    <a class="btn btn-block btn-whatsapp btn-lg" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Écrire sur WhatsApp</a>
                @endif
            </div>
            <a class="btn btn-light btn-block next-step" href="{{ route('payment.create', $payment->plan) }}">Envoyer un nouveau paiement</a>
        @endif

        <section class="card next-step">
            <h2>Récapitulatif</h2>
            <dl class="summary">
                <dt>Formule</dt><dd>{{ $payment->plan_name }}</dd>
                <dt>Montant</dt><dd>{{ $payment->formattedAmount() }}</dd>
                <dt>Opérateur</dt><dd>{{ $payment->operator_name }}</dd>
                <dt>Envoyé le</dt><dd>{{ $payment->created_at->translatedFormat('j F Y à H:i') }}</dd>
                <dt>État</dt><dd>{{ $provisional ? 'Accès provisoire, vérification en cours' : $payment->status->label() }}</dd>
            </dl>
        </section>
    </div>
</x-layout>
