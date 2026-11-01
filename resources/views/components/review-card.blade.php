{{-- Un avis publié : note, texte tel que l'élève l'a écrit, prénom et initiale, pays, niveau, date, réponse de l'équipe. --}}
@props(['review'])
<article {{ $attributes->class('card review-card') }} id="avis-{{ $review->id }}">
    <div class="review-head">
        <x-stars :rating="$review->rating" />
        <span @class(['badge', 'badge-muted' => $review->kind === 'free'])>{{ $review->kindLabel() }}</span>
    </div>
    <p class="review-body">{!! nl2br(e($review->body)) !!}</p>
    <p class="review-meta muted small">
        <strong>{{ $review->authorName() }}</strong>@if ($review->countryName()) · {{ $review->countryName() }}@endif @if ($review->level) · niveau {{ $review->level->value }}@endif
        · {{ ($review->published_at ?? $review->created_at)->translatedFormat('j F Y') }}
    </p>
    @if ($review->reply)
        <div class="review-reply">
            <p class="small"><strong>Réponse de l'équipe {{ config('platform.name') }}</strong></p>
            <p class="small">{!! nl2br(e($review->reply)) !!}</p>
        </div>
    @endif
</article>
