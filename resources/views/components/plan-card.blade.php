{{--
    Carte d'une formule, la même partout (accueil, résultat, sous le verdict, choix du paiement).
    Bouton orange plein « Choisir … » (texte bleu nuit) ; badge de chaque formule (« badge » dans
    config/platform.php) ; formule « recommended » mise en avant : badge orange et contour orange.
    Jamais de statistique. « compact » (accueil) : temps par jour et validité sur une ligne, le détail replié.
--}}
@props(['plan', 'href', 'heading' => 'h3', 'current' => false, 'compact' => false])

<article {{ $attributes->class(['card', 'plan', 'has-badge' => $plan->badge, 'is-recommended' => $plan->recommended, 'is-current' => $current, 'plan-compact' => $compact]) }}>
    @if ($plan->badge)
        <span @class(['plan-badge', 'plan-badge-strong' => $plan->recommended])>@if ($plan->recommended)<x-icon name="sparkle" /> @endif{{ $plan->badge }}</span>
    @endif
    <{{ $heading }}>{{ $plan->name }}</{{ $heading }}>
    @if ($compact)
        <p class="plan-price-line"><span class="price">{{ $plan->formattedPrice() }}</span> <span class="period">{{ $plan->periodLabel() }}</span></p>
    @else
        <p class="price">{{ $plan->formattedPrice() }}</p>
        <span class="period">{{ $plan->periodLabel() }}</span>
    @endif
    @if ($compact)
        <p class="plan-summary"><x-icon name="clock" /> {{ $plan->dailyMinutes }} minutes par jour · {{ $plan->durationDays }} jours</p>
        <details class="plan-more">
            <summary>Ce qui est compris</summary>
            <ul>
                <li><x-icon name="clock" /> {{ $plan->dailyMinutes }} minutes par jour, à partager entre la séance orale et « Mes cours »</li>
                <li><x-icon name="book" /> Aide à vos cours d'anglais (Mes cours), comprise dans ce temps</li>
                <li><x-icon name="check" /> Programme personnel et suivi de vos erreurs</li>
            </ul>
        </details>
    @else
        <ul>
            <li><x-icon name="clock" /> {{ $plan->dailyMinutes }} minutes par jour, à partager entre la séance orale et « Mes cours »</li>
            <li><x-icon name="calendar" /> Valable {{ $plan->durationDays }} jours</li>
            <li><x-icon name="book" /> Aide à vos cours d'anglais (Mes cours), comprise dans ce temps</li>
            <li><x-icon name="check" /> Programme personnel et suivi de vos erreurs</li>
        </ul>
    @endif
    <a class="btn btn-accent btn-block btn-plan" href="{{ $href }}">Choisir {{ $plan->name }} <x-icon name="arrow-right" /></a>
</article>
