{{--
    LA SUITE à la fin d'une étape : une phrase courte qui dit ce qui attend l'élève, puis UN seul bouton principal orange
    (56 pixels de haut au moins), placé AVANT les détails (tableau, résumé, explications). Les actions secondaires
    (revoir, télécharger…) vont dans le contenu du composant, plus discrètes. Le titre reçoit le focus quand la page
    y mène (ancre #suite, public/js/app.js).

    <x-next-step title="…" text="…" :href="…" label="…" icon="mic">liens secondaires</x-next-step>
--}}
@props(['title', 'href', 'label', 'text' => null, 'icon' => null, 'id' => 'suite', 'eyebrow' => 'La suite'])

<section {{ $attributes->merge(['class' => 'card next-card next-step-card']) }} id="{{ $id }}" aria-labelledby="{{ $id }}-titre">
    <span class="next-card-eyebrow">{{ $eyebrow }}</span>
    <h2 id="{{ $id }}-titre" tabindex="-1">{{ $title }}</h2>
    @if ($text)
        <p class="next-step-text">{{ $text }}</p>
    @endif
    <a class="btn btn-accent btn-lg btn-block btn-next" href="{{ $href }}">
        @if ($icon)<x-icon :name="$icon" /> @endif<span data-next-label>{{ $label }}</span>@unless ($icon) <x-icon name="arrow-right" />@endunless
    </a>
    @if (trim($slot) !== '')
        <div class="next-step-more">{{ $slot }}</div>
    @endif
</section>
