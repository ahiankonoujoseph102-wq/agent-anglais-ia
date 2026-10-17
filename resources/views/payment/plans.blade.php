<x-layout title="Choisir ma formule">
    @include('payment.steps', ['current' => 1])

    <div class="step-panel">
        <div class="page-head">
            <h1>Choisissez votre formule</h1>
            <p class="muted">
                Vous payez par Mobile Money depuis votre téléphone, puis vous envoyez la capture d'écran de la confirmation.
                Si tout est clair, vous pouvez commencer vos séances tout de suite, le temps que notre équipe vérifie votre paiement.
            </p>
        </div>

        @if ($rejected)
            <div class="alert alert-error" role="alert">
                <x-icon name="alert" />
                <div>
                    <strong>Nous n'avons pas pu valider votre dernier paiement.</strong><br>
                    @if ($rejected->rejection_reason)
                        Motif : {{ $rejected->rejection_reason }}<br>
                    @endif
                    Écrivez-nous sur WhatsApp : nous regarderons ensemble. Vous pouvez aussi envoyer un nouveau paiement.
                </div>
            </div>
            @if ($whatsappUrl)
                <a class="btn btn-whatsapp btn-block" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Écrire sur WhatsApp</a>
            @endif
        @endif

        @if ($subscription)
            <div class="alert alert-info">
                <x-icon name="info" />
                <div>
                    Votre formule {{ $subscription->plan_name }} est valable jusqu'au
                    {{ $subscription->ends_at->translatedFormat('j F Y') }}. Le renouvellement commencera à cette date.
                </div>
            </div>
        @endif

        <div class="plans">
            @foreach ($plans as $plan)
                <x-plan-card :plan="$plan" :href="route('payment.create', $plan->key)" heading="h2" :current="$subscription?->plan === $plan->key" />
            @endforeach
        </div>

        <ul class="check-list next-step" aria-label="Comment se passe le paiement">
            <li><x-icon name="check-circle" /> Paiement par Mobile Money depuis votre téléphone, sans carte bancaire{{ $countries ? ' ('.implode(', ', $countries).')' : '' }}.</li>
            <li><x-icon name="check-circle" /> Montant exact, numéro et titulaire affichés à l'étape suivante, avec un bouton « Copier le numéro ».</li>
            <li><x-icon name="check-circle" /> Capture claire : vos séances peuvent commencer tout de suite, pendant la vérification.</li>
        </ul>
    </div>
</x-layout>
