<x-layout title="Choisir ma formule">
    @include('payment.steps', ['current' => 1])

    <h1>Choisissez votre formule</h1>

    @if ($rejected)
        <div class="alert alert-error" role="alert">
            <strong>Votre dernier paiement a été refusé.</strong><br>
            Motif : {{ $rejected->rejection_reason }}<br>
            Vous pouvez envoyer un paiement corrigé.
        </div>
    @endif

    @if ($subscription)
        <p class="muted">
            Votre formule {{ $subscription->plan_name }} est valable jusqu'au
            {{ $subscription->ends_at->translatedFormat('j F Y') }}. Le renouvellement commencera à cette date.
        </p>
    @endif

    <div class="plans">
        @foreach ($plans as $plan)
            <div class="card plan">
                <h2>{{ $plan->name }}</h2>
                <div class="price">{{ $plan->formattedPrice() }}</div>
                <div class="muted">{{ $plan->periodLabel() }}</div>
                <p>{{ $plan->dailyMinutes }} minutes de séance par jour pendant {{ $plan->durationDays }} jours</p>
                <a class="btn btn-block" href="{{ route('payment.create', $plan->key) }}">Choisir {{ $plan->name }}</a>
            </div>
        @endforeach
    </div>
</x-layout>
