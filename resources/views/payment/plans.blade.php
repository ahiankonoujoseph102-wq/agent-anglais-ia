<x-layout title="Choisir ma formule">
    @include('payment.steps', ['current' => 1])

    <div class="step-panel">
        <div class="page-head">
            <h1>Choisissez votre formule</h1>
            <p class="muted">Vous payez par Mobile Money, puis nous validons votre paiement : votre formule s'active dès cette validation.</p>
        </div>

        @if ($rejected)
            <div class="alert alert-error" role="alert">
                <x-icon name="alert" />
                <div>
                    <strong>Votre dernier paiement a été refusé.</strong><br>
                    Motif : {{ $rejected->rejection_reason }}<br>
                    Vous pouvez envoyer un paiement corrigé.
                </div>
            </div>
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
                <article @class(['card', 'plan', 'is-current' => $subscription?->plan === $plan->key])>
                    <h2>{{ $plan->name }}</h2>
                    <p class="price">{{ $plan->formattedPrice() }}</p>
                    <span class="period">{{ $plan->periodLabel() }}</span>
                    <ul>
                        <li><x-icon name="clock" /> {{ $plan->dailyMinutes }} minutes de séance par jour pendant {{ $plan->durationDays }} jours</li>
                    </ul>
                    <a class="btn btn-block" href="{{ route('payment.create', $plan->key) }}">Choisir {{ $plan->name }} <x-icon name="arrow-right" /></a>
                </article>
            @endforeach
        </div>
    </div>
</x-layout>
