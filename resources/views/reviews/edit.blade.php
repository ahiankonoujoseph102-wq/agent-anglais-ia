{{-- Donner (ou modifier) son avis (ReviewController::edit) : note, texte court, accord pour la publication. --}}
@php
    $platform = config('platform.name');
    $max = (int) config('platform.reviews.max_characters');
    // Note déjà choisie en touchant une étoile de l'invitation (?note=1 à 5) : il ne reste qu'à écrire.
    $asked = (int) request()->query('note');
    $rating = (int) old('rating', $review?->rating ?? ($asked >= 1 && $asked <= 5 ? $asked : 0));
    $starters = ['Ce que j\'aime : ', 'Ce qui m\'aide le plus : ', 'Ce que j\'ai appris : ', 'Ce qui pourrait être mieux : '];
@endphp

<x-layout title="Mon avis" narrow>
    <div class="auth-head rise">
        <span class="icon-bubble icon-bubble-primary"><x-icon name="star" /></span>
        <h1>{{ $review ? 'Modifier mon avis' : 'Donner mon avis' }}</h1>
        <p>Une minute suffit. Positif ou négatif, votre avis aide d'autres personnes à oser commencer, et nous aide à faire mieux.</p>
        @if (! $bonusGiven)
            <p class="small"><x-icon name="gift" /> Merci du temps pris : <strong>{{ $bonusMinutes }} minutes de séance offertes</strong>, automatiquement, quelle que soit votre note.</p>
        @endif
    </div>

    <form method="POST" action="{{ route('reviews.save') }}" class="card review-form">
        @csrf
        @method('PUT')

        <fieldset class="star-picker">
            <legend><span class="review-step">1</span> Touchez les étoiles pour donner votre note</legend>
            <p class="small muted review-scale">1 étoile : pas content · 5 étoiles : très content</p>
            <div class="star-choices">
                @for ($i = 5; $i >= 1; $i--)
                    <input type="radio" name="rating" id="note-{{ $i }}" value="{{ $i }}" @checked($rating === $i) required>
                    <label for="note-{{ $i }}" title="{{ $i }} sur 5"><x-icon name="star" /><span class="sr-only">{{ $i }} sur 5</span></label>
                @endfor
            </div>
            {{-- Note choisie, dite en clair (CSS :has, sans JavaScript). --}}
            <p class="star-chosen" aria-hidden="true">
                <span data-for="0">Aucune note choisie pour le moment.</span>
                @foreach ([1 => 'pas content', 2 => 'peu content', 3 => 'moyen', 4 => 'content', 5 => 'très content'] as $value => $word)
                    <span data-for="{{ $value }}">Votre note : {{ $value }} sur 5 ({{ $word }})</span>
                @endforeach
            </p>
            @error('rating')<p class="error">{{ $message }}</p>@enderror
        </fieldset>

        <label for="body"><span class="review-step">2</span> Écrivez votre avis en quelques mots</label>
        {{-- Débuts de phrase à toucher (public/js/app.js, data-insert) : l'élève écrit la suite lui-même. --}}
        <div class="review-starters" aria-label="Idées pour commencer">
            @foreach ($starters as $starter)
                <button type="button" class="chip" data-insert="{{ $starter }}" data-insert-target="body">{{ rtrim($starter, ' :') }}</button>
            @endforeach
        </div>
        <textarea id="body" name="body" rows="6" maxlength="{{ $max }}" required @if ($asked && ! old('body') && ! $review) autofocus @endif @class(['is-invalid' => $errors->has('body')]) aria-describedby="body-hint" placeholder="Qu'avez-vous pensé de vos séances ? Qu'est-ce qui vous aide ? Qu'est-ce qui pourrait être mieux ?">{{ old('body', $review?->body) }}</textarea>
        <p class="hint small" id="body-hint">De {{ config('platform.reviews.min_characters') }} à {{ $max }} caractères. Pas de numéro de téléphone ni de lien.</p>
        @error('body')<p class="error">{{ $message }}</p>@enderror

        <p class="review-step-line"><span class="review-step">3</span> Cochez la case, puis touchez le bouton orange</p>
        <label class="checkbox option">
            <input type="checkbox" name="consent" value="1" @checked(old('consent', $review !== null)) required>
            <span>J'accepte que mon avis soit publié sur {{ $platform }} avec mon prénom, l'initiale de mon nom, mon pays et mon niveau.</span>
        </label>
        @error('consent')<p class="error">{{ $message }}</p>@enderror

        <button type="submit" class="btn btn-accent btn-lg btn-block"><x-icon name="check" /> {{ $review ? 'Enregistrer mon avis' : 'Publier mon avis' }}</button>

        <p class="muted small">Positif ou négatif, votre avis est publié tel quel. Une note très basse, ou un texte avec un lien, un numéro ou un mot grossier, est d'abord vérifié par notre équipe. <a href="{{ route('reviews.index') }}">Voir les avis des élèves</a></p>
    </form>
</x-layout>
