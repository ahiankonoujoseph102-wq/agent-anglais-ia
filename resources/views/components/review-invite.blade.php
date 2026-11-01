{{--
    Invitation à donner son avis (« Mon espace », page de résultat de l'évaluation) : seulement pour un élève qui peut
    en laisser un et ne l'a pas encore fait (App\Support\Reviews\Reviews::shouldInvite). Minutes offertes pour TOUT avis,
    quelle que soit la note ; jamais une demande de bonne note.
--}}
@props(['user' => auth()->user()])
@if (\App\Support\Reviews\Reviews::shouldInvite($user))
    @php
        $minutes = intdiv(\App\Support\Reviews\TimeBonuses::seconds(), 60);
        $teacher = \App\Support\Teachers\Teachers::forUser($user)->name();
    @endphp
    <section {{ $attributes->merge(['class' => 'card review-invite']) }} id="donner-mon-avis" aria-labelledby="review-invite-title">
        <div class="card-title"><span class="icon-bubble icon-bubble-accent"><x-icon name="star" /></span><h2 id="review-invite-title">Votre avis compte</h2></div>
        <p>Comment se passe votre apprentissage avec {{ $teacher }} ? Donnez votre avis en une minute, qu'il soit positif ou non : il aide d'autres personnes à oser commencer, et nous aide à faire mieux.</p>
        @if ($minutes > 0)
            <p class="small"><x-icon name="gift" /> Merci du temps pris : <strong>{{ $minutes }} minutes de séance offertes</strong>, quelle que soit votre note{{ $user->activeSubscription === null ? ', ajoutées à votre première séance avec une formule' : '' }}.</p>
        @endif
        <a class="btn btn-outline btn-block" href="{{ route('reviews.edit') }}"><x-icon name="star" /> Donner mon avis</a>
    </section>
@endif
