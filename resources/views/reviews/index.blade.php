{{--
    « Avis des élèves » (publique, ReviewController::index) : vrais avis, positifs comme négatifs, publiés tels qu'ils
    ont été écrits (App\Support\Reviews\Reviews). Aucun avis inventé, aucune note moyenne avant platform.reviews.average_min
    avis, et AUCUNE donnée structurée de note (Google n'accepte pas les étoiles d'un site sur lui-même).
--}}
@php
    $platform = config('platform.name');
    $teacher = config('avatar.model.name');
@endphp

<x-layout title="Avis des élèves sur les cours d'anglais"
          :description="'Les avis des élèves de '.$platform.' sur leurs séances d\'anglais avec '.$teacher.', publiés tels qu\'ils ont été écrits, positifs comme négatifs.'">
    <div class="auth-head rise">
        <span class="icon-bubble icon-bubble-primary"><x-icon name="star" /></span>
        <h1>Avis des élèves</h1>
        <p>Ce que pensent nos élèves de leurs séances avec {{ $teacher }}.</p>
    </div>

    @if ($stats['count'] > 0)
        <p class="review-stats">
            @if ($stats['average'] !== null)
                <x-stars :rating="(int) round($stats['average'])" />
                <strong>{{ str_replace('.', ',', (string) $stats['average']) }} sur 5</strong> ·
            @endif
            {{ $stats['count'] }} {{ $stats['count'] > 1 ? 'avis publiés' : 'avis publié' }}
        </p>
    @endif

    @if ($canReview)
        <p class="review-own">
            <a class="btn btn-outline" href="{{ route('reviews.edit') }}"><x-icon name="star" /> {{ $mine ? 'Modifier mon avis' : 'Donner mon avis' }}</a>
        </p>
    @endif

    <section class="reviews-list" aria-label="Avis publiés">
        @forelse ($reviews as $review)
            <x-review-card :review="$review" />
        @empty
            <div class="card empty-state">
                <span class="icon-bubble"><x-icon name="star" /></span>
                <p>Aucun avis publié pour le moment. Les premiers élèves sont en train de donner le leur.</p>
                <a class="btn btn-accent" href="{{ \App\Support\Navigation::assessmentUrl() }}"><x-icon name="mic" /> Passer mon évaluation gratuite</a>
            </div>
        @endforelse
    </section>

    {{ $reviews->links() }}

    <section class="card review-rules" aria-labelledby="regles-avis">
        <h2 id="regles-avis">Comment nous publions les avis</h2>
        <ul>
            <li>Seuls des élèves inscrits peuvent laisser un avis : après leur évaluation gratuite, ou après une séance avec une formule.</li>
            <li>Les avis sont publiés tels qu'ils ont été écrits, positifs comme négatifs. Nous ne les modifions jamais.</li>
            <li>Notre équipe vérifie avant publication les avis très négatifs et ceux qui contiennent un lien, un numéro ou un mot grossier. Elle retire seulement les avis insultants, hors sujet ou qui contiennent des données personnelles.</li>
            <li>Pour le remercier du temps pris, chaque élève qui laisse un avis reçoit {{ $bonusMinutes }} minutes de séance offertes, quelle que soit sa note.</li>
            <li>Seuls le prénom, l'initiale du nom, le pays et le niveau sont affichés.</li>
        </ul>
    </section>
</x-layout>
