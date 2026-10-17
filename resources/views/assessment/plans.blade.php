{{-- Formules proposées après l'évaluation (page de résultat et fin de la conversation). --}}
<div class="plans">
    @foreach (\App\Support\Plan::all() as $plan)
        <x-plan-card :plan="$plan" :href="route('payment.create', $plan->key)" />
    @endforeach
</div>
