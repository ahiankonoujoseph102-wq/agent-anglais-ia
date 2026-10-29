{{--
    Action principale en haut d'une page publique longue (« À propos », « Comment ça marche », guides) : le bouton
    orange de l'évaluation gratuite reste visible sans défiler à 360 × 640. Durée lue dans la configuration, niveaux
    lus dans App\Enums\Level. Le même appel peut revenir en bas de la page (<x-guide-cta>).
--}}
@php
    $levels = \App\Enums\Level::cases();
@endphp
<div {{ $attributes->class(['top-cta rise']) }}>
    <a class="btn btn-accent btn-lg" href="{{ \App\Support\Navigation::assessmentUrl() }}"><x-icon name="mic" /> Passer mon évaluation gratuite</a>
    <p class="muted small">Évaluation orale gratuite de {{ config('platform.assessment.max_minutes') }} minutes : votre niveau d'anglais, de {{ $levels[0]->value }} à {{ end($levels)->value }}.</p>
</div>
