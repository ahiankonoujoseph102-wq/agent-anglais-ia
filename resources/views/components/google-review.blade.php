{{--
    Avis sur la fiche Google de l'établissement (« Mon espace », demande du fondateur, 9 novembre 2026) : toujours
    visible pour un élève qui a fait son évaluation ou une séance, tant qu'il ne l'a pas caché (« C'est fait »,
    sur ce téléphone : data-dismissible de app.js). S'il a déjà écrit un avis sur le site, il peut le copier pour le
    coller sur Google. Règles de Google : la même demande pour tous, aucune récompense, aucune note suggérée.
    Pas d'avis sur le site encore : cette carte n'est pas montrée, l'invitation <x-review-invite> porte le lien.
--}}
@props(['user' => auth()->user()])
@php($siteReview = $user && ! $user->isStaff() ? $user->review()->first() : null)
@if ($siteReview !== null && \App\Support\Reviews\Reviews::canReview($user))
    <section {{ $attributes->merge(['class' => 'card google-review']) }} data-dismissible="google-review" aria-labelledby="google-review-title">
        <div class="card-title"><span class="icon-bubble icon-bubble-accent"><x-icon name="star" /></span><h2 id="google-review-title">Votre avis sur Google</h2></div>
        <p>Merci pour votre avis ! Pourriez-vous aussi le laisser sur notre page Google ? C'est là que d'autres personnes nous trouvent : votre avis les aide à oser apprendre l'anglais.</p>
        @if (filled($siteReview->body))
            <p class="small">Touchez « Copier mon avis », puis collez-le sur Google.</p>
            <button type="button" class="btn btn-outline btn-block" data-copy="{{ $siteReview->body }}" data-copied="Avis copié : collez-le sur Google"><x-icon name="copy" /> Copier mon avis</button>
        @endif
        <a class="btn btn-outline btn-block google-review-go" href="{{ route('reviews.google') }}" target="_blank" rel="noopener"><x-icon name="star" /> Laisser mon avis sur Google<span class="sr-only"> (s'ouvre dans un nouvel onglet)</span></a>
        <button type="button" class="btn-link google-review-done" data-dismiss>C'est fait, merci</button>
    </section>
    @once
        <script type="module" src="{{ \App\Support\Asset::url('js/copy.js') }}"></script>
    @endonce
@endif
