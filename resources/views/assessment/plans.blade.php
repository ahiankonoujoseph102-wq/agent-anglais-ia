{{-- Formules proposées après l'évaluation (page de résultat et fin de la conversation). --}}
<div class="plans">
    @foreach (\App\Support\Plan::all() as $plan)
        <article class="card plan">
            <h3>{{ $plan->name }}</h3>
            <p class="price">{{ $plan->formattedPrice() }}</p>
            <span class="period">{{ $plan->periodLabel() }}</span>
            <ul>
                <li><x-icon name="clock" /> {{ $plan->dailyMinutes }} minutes de séance orale par jour</li>
                <li><x-icon name="calendar" /> Valable {{ $plan->durationDays }} jours</li>
                <li><x-icon name="check" /> Programme construit pour votre niveau</li>
            </ul>
            <a class="btn btn-accent btn-block" href="{{ route('payment.create', $plan->key) }}">Je commence maintenant <x-icon name="arrow-right" /></a>
        </article>
    @endforeach
</div>
