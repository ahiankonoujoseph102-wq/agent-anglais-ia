{{-- Onglet « Avis » (Admin\ReviewController) : vérifier, publier, retirer, répondre. Le texte de l'élève n'est jamais modifié. --}}
@php
    use App\Enums\ReviewStatus;
    use App\Support\Confirmation;
@endphp
<x-layout title="Avis des élèves">
    @include('admin.nav')

    <h1>Avis des élèves</h1>
    <p class="muted">Publiés tout de suite, positifs comme négatifs. Attendent votre vérification : les notes de {{ config('platform.reviews.hold_rating_at_most') }} étoiles ou moins, et les textes avec un lien, un numéro, une adresse e-mail, un mot grossier ou un nom de marque. Retirez seulement ce qui est insultant, hors sujet ou contient des données personnelles ; une vraie critique se publie, avec votre réponse.</p>

    <div class="stats">
        <div class="stat"><div class="label">À vérifier</div><div class="value">{{ $pending }}</div></div>
        <div class="stat"><div class="label">Avis publiés</div><div class="value">{{ $stats['count'] }}</div>
            <div class="detail">@if ($stats['average'] !== null) note moyenne {{ str_replace('.', ',', (string) $stats['average']) }} sur 5 @else note moyenne affichée à partir de {{ config('platform.reviews.average_min') }} avis @endif</div></div>
    </div>

    <form method="GET" action="{{ route('admin.reviews.index') }}" class="search" role="search">
        <select name="etat" aria-label="État des avis">
            <option value="">Tous les avis</option>
            <option value="a-verifier" @selected($filter === 'a-verifier')>À vérifier</option>
            <option value="publies" @selected($filter === 'publies')>Publiés</option>
            <option value="retires" @selected($filter === 'retires')>Retirés</option>
        </select>
        <button type="submit" class="btn">Afficher</button>
    </form>

    @forelse ($reviews as $review)
        <article class="card admin-review" id="avis-{{ $review->id }}">
            <div class="review-head">
                <x-stars :rating="$review->rating" />
                <span @class(['badge', 'badge-alert' => $review->isPending(), 'badge-muted' => $review->status === ReviewStatus::Hidden])>{{ $review->status->label() }}</span>
            </div>
            <p class="review-body">{!! nl2br(e($review->body)) !!}</p>
            <p class="muted small">
                <a href="{{ route('admin.users.show', $review->user) }}">{{ $review->user->name }}</a>
                (affiché : {{ $review->authorName() }}) · {{ $review->kindLabel() }}@if ($review->level) · {{ $review->level->value }}@endif
                · envoyé le {{ $review->updated_at->timezone('Africa/Lome')->translatedFormat('j F Y \à H:i') }}
                @if ($review->reviewer) · {{ $review->status === ReviewStatus::Hidden ? 'retiré' : 'vérifié' }} par {{ $review->reviewer->name }} @endif
            </p>
            @if ($review->flags)
                <p class="small"><strong>À vérifier :</strong> {{ implode(', ', $review->flagLabels()) }}</p>
            @endif

            <div class="actions">
                @unless ($review->isPublished())
                    <form method="POST" action="{{ route('admin.reviews.publish', $review) }}">
                        @csrf
                        <button type="submit" class="btn btn-accent"><x-icon name="check" /> Publier</button>
                    </form>
                @endunless
                @unless ($review->status === ReviewStatus::Hidden)
                    <form method="POST" action="{{ route('admin.reviews.hide', $review) }}" {!! Confirmation::attributes('review.hide') !!}>
                        @csrf
                        <button type="submit" class="btn btn-outline"><x-icon name="eye-off" /> Retirer</button>
                    </form>
                @endunless
            </div>

            <details @if ($review->reply) open @endif>
                <summary>{{ $review->reply ? 'Réponse publique de l\'équipe' : 'Répondre publiquement' }}</summary>
                <form method="POST" action="{{ route('admin.reviews.reply', $review) }}">
                    @csrf
                    <label for="reply-{{ $review->id }}" class="sr-only">Réponse</label>
                    <textarea id="reply-{{ $review->id }}" name="reply" rows="3" maxlength="{{ config('platform.reviews.max_characters') }}" placeholder="Merci pour votre avis…">{{ $review->reply }}</textarea>
                    <p class="hint">Affichée sous l'avis, signée « Réponse de l'équipe {{ config('platform.name') }} ». Champ vide : réponse retirée.</p>
                    <button type="submit" class="btn btn-outline">Enregistrer la réponse</button>
                </form>
            </details>
        </article>
    @empty
        <div class="card empty-state"><span class="icon-bubble"><x-icon name="star" /></span><p>Aucun avis{{ $filter ? ' dans cette liste' : '' }} pour le moment.</p></div>
    @endforelse

    {{ $reviews->links() }}
</x-layout>
