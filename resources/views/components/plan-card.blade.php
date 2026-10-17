{{--
    Carte d'une formule, la même partout (accueil, résultat, sous le verdict, choix du paiement).
    Bouton orange plein « Choisir … » (texte bleu nuit) ; formule « recommended » de config/platform.php
    mise en avant : badge « Recommandée » et contour orange (jamais de statistique).
--}}
@props(['plan', 'href', 'heading' => 'h3', 'current' => false])

<article {{ $attributes->class(['card', 'plan', 'is-recommended' => $plan->recommended, 'is-current' => $current]) }}>
    @if ($plan->recommended)
        <span class="plan-badge"><x-icon name="sparkle" /> Recommandée</span>
    @endif
    <{{ $heading }}>{{ $plan->name }}</{{ $heading }}>
    <p class="price">{{ $plan->formattedPrice() }}</p>
    <span class="period">{{ $plan->periodLabel() }}</span>
    <ul>
        <li><x-icon name="clock" /> {{ $plan->dailyMinutes }} minutes de séance orale par jour</li>
        <li><x-icon name="calendar" /> Valable {{ $plan->durationDays }} jours</li>
        <li><x-icon name="check" /> Programme personnel et suivi de vos erreurs</li>
    </ul>
    <a class="btn btn-accent btn-block btn-plan" href="{{ $href }}">Choisir {{ $plan->name }} <x-icon name="arrow-right" /></a>
</article>
