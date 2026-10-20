{{--
    Document personnel de révision d'une séance « Mes cours » (App\Support\CourseReviews::stateOf). Les trois états
    utiles sont rendus, un seul visible : « en préparation » se met à jour tout seul (public/js/course-review.js, qui
    interroge l'état de temps en temps) ; « prêt » propose le téléchargement ; « échec » propose « Réessayer ».
--}}
@props(['session'])

@php
    $state = \App\Support\CourseReviews::stateOf($session);
@endphp

@if ($state === \App\Models\CourseReview::SKIPPED)
    <p class="review-doc muted small"><x-icon name="info" /> Pas de document de révision pour cette séance : elle était trop courte.</p>
@elseif ($state !== 'none')
    <div class="review-doc" data-review-state="{{ $state }}" @if ($state === 'pending') data-review-status="{{ route('courses.review.status', $session) }}" @endif>
        <p class="review-doc-line" data-review-when="pending" @if ($state !== 'pending') hidden @endif role="status">
            <span class="review-spinner" aria-hidden="true"></span> Votre document de révision est en préparation. Cette page se met à jour toute seule.
        </p>
        <p class="review-doc-line" data-review-when="ready" @if ($state !== 'ready') hidden @endif>
            <a class="btn btn-accent" href="{{ route('courses.review.download', $session) }}"><x-icon name="download" /> Télécharger mon document de révision</a>
        </p>
        <div class="review-doc-line" data-review-when="failed" @if ($state !== 'failed') hidden @endif>
            <p class="small">La préparation de votre document de révision n'a pas abouti. Vous pouvez réessayer.</p>
            <form method="POST" action="{{ route('courses.review.retry', $session) }}">
                @csrf
                <button type="submit" class="btn btn-outline"><x-icon name="refresh" /> Réessayer</button>
            </form>
        </div>
    </div>
@endif
