{{--
    Questions sur les formules (accueil, dans la foire aux questions, et page « Formules ») : « Quelle formule choisir ? »
    puis une question par formule. Prix, temps par jour, durée et prix par jour lus dans config/platform.php
    (Plan::all(), PlanFacts::perDay) : rien n'est écrit à la main, aucune promesse.
--}}
{{-- Quelle formule choisir : prix, temps par jour et durée lus dans config/platform.php (ordre de Plan::all()). --}}
@php($faqPlans = \App\Support\Plan::all())
@php($fewestMinutes = min(array_map(fn ($plan) => $plan->dailyMinutes, $faqPlans)))
<details>
    <summary><x-icon name="target" /><span>Quelle formule choisir ?</span></summary>
    <p>Les formules donnent toutes la même méthode, le même programme et les mêmes professeurs : seuls le temps par jour et la durée changent.</p>
    <ul>
        @foreach ($faqPlans as $faqPlan)
            <li><strong>{{ $faqPlan->name }}</strong> ({{ $faqPlan->formattedPrice() }} {{ $faqPlan->periodLabel() }}, {{ $faqPlan->dailyMinutes }} minutes par jour) : {{ match (true) {
                $faqPlan->period === 'week' => 'pour essayer la méthode pendant une semaine, avant de vous engager pour un mois',
                $faqPlan->recommended => 'la formule recommandée pour progresser avec régularité, un peu chaque jour pendant tout un mois',
                $faqPlan->dailyMinutes > $fewestMinutes => 'pour progresser plus vite, avec '.$faqPlan->dailyMinutes.' minutes par jour au lieu de '.$fewestMinutes.', donc plus de pratique chaque jour',
                default => $faqPlan->badge ?? '',
            } }}.</li>
        @endforeach
    </ul>
    <p>Ce qui fait le plus progresser, c'est la régularité : mieux vaut un peu chaque jour que beaucoup de temps en temps. Vous pouvez commencer par une formule et en choisir une autre au renouvellement.</p>
</details>
{{-- Une question par formule : caractéristiques et avantage de chacune, tout lu dans la configuration. --}}
@php($trialPlan = collect($faqPlans)->first(fn ($plan) => $plan->period === 'week'))
@php($recommendedPlan = collect($faqPlans)->first(fn ($plan) => $plan->recommended))
@foreach ($faqPlans as $faqPlan)
    @php($facts = $faqPlan->formattedPrice().' pour '.$faqPlan->durationDays.' jours, '.$faqPlan->dailyMinutes.' minutes par jour avec votre professeur, '.\App\Support\Gemini\PlanFacts::perDay($faqPlan))
    @if ($faqPlan->period === 'week')
        <details>
            <summary><x-icon name="sparkle" /><span>À quoi sert la formule {{ $faqPlan->name }} ?</span></summary>
            <p>C'est la formule pour essayer : {{ $facts }}. Vous avez tout ce qu'ont les autres formules : votre programme personnel, la séance orale du jour, « Mes cours » et le choix de votre professeur. C'est le bon choix si vous voulez d'abord voir par vous-même comment se passent les séances.</p>
            @if ($recommendedPlan)
                <p>Si la méthode vous plaît, prenez ensuite {{ $recommendedPlan->name }} : vous pouvez la payer avant la fin de votre semaine, elle commence quand votre semaine se termine, sans perdre aucun jour.</p>
            @endif
        </details>
    @elseif ($faqPlan->recommended)
        <details>
            <summary><x-icon name="award" /><span>Pourquoi la formule {{ $faqPlan->name }} est-elle recommandée ?</span></summary>
            <p>{{ ucfirst($facts) }}.@if ($trialPlan && $faqPlan->price / $faqPlan->durationDays < $trialPlan->price / $trialPlan->durationDays) C'est moins cher par jour que la formule {{ $trialPlan->name }} ({{ \Illuminate\Support\Str::after(\App\Support\Gemini\PlanFacts::perDay($trialPlan), 'soit ') }}).@endif</p>
            <p>Un mois entier, c'est le temps de prendre l'habitude de parler anglais chaque jour et de suivre votre programme leçon après leçon : la régularité est ce qui fait le plus progresser. C'est le meilleur choix pour la plupart des élèves.</p>
        </details>
    @else
        @php($times = $fewestMinutes > 0 && $faqPlan->dailyMinutes % $fewestMinutes === 0 ? intdiv($faqPlan->dailyMinutes, $fewestMinutes) : null)
        <details>
            <summary><x-icon name="trending" /><span>Pour qui est la formule {{ $faqPlan->name }} ?</span></summary>
            <p>{{ ucfirst($facts) }}. {{ $times ? ucfirst([2 => 'deux', 3 => 'trois', 4 => 'quatre'][$times] ?? (string) $times).' fois plus de temps chaque jour' : 'Plus de temps chaque jour' }} qu'avec {{ $recommendedPlan?->name ?? 'les autres formules' }} : plus de pratique à l'oral, et plus de temps pour « Mes cours » (vos leçons d'anglais de l'école ou de l'université). Le temps se partage librement entre les deux.</p>
            <p>C'est la formule pour ceux qui veulent progresser plus vite, ou qui ont un objectif proche : un entretien, un exposé, un voyage, des examens à l'école.</p>
        </details>
    @endif
@endforeach
