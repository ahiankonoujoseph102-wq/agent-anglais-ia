{{--
    LA SUITE sur la page de résultat : une phrase et UN bouton orange, dans le haut de la page, avant l'avis de
    Teacher Joe, le tableau de la première leçon et les formules (plus besoin de descendre pour trouver la suite).
--}}
@php
    $level = $chosen?->value ?? $found?->value;
    [$nextText, $nextHref, $nextLabel] = match (true) {
        $hasSubscription => ['Votre accès est ouvert : retrouvez votre programme et votre séance du jour dans votre espace.', route('dashboard'), 'Aller à mon espace'],
        $level !== null => ["Votre programme {$level} vous attend : choisissez votre formule pour commencer avec ".$assessment->teacher()->name().'.', '#formules', 'Choisir ma formule'],
        $canChoose => ['Choisissez le niveau qui vous ressemble : votre programme sera construit à partir de lui.', '#autre-niveau', 'Choisir mon niveau'],
        default => ['Retrouvez votre programme dans votre espace.', route('dashboard'), 'Aller à mon espace'],
    };
@endphp
@php
    // Séance d'essai gratuite : la première suite proposée à qui n'a jamais payé ni essayé (App\Support\Trial).
    $trialOffer = ! $hasSubscription && $level !== null && auth()->user() && \App\Support\Trial::eligible(auth()->user());
@endphp
<div class="result-next" id="suite">
    @if ($trialOffer)
        <p class="result-next-text">
            Essayez maintenant un vrai cours avec {{ \App\Support\Teachers\Teachers::forUser(auth()->user())->name() }} :
            {{ \App\Support\Trial::minutes() }} minutes offertes, une fois par compte, à utiliser dans les {{ \App\Support\Trial::validity() }}.
        </p>
        <form method="POST" action="{{ route('trial.claim') }}">
            @csrf
            <button type="submit" class="btn btn-accent btn-lg btn-next"><x-icon name="gift" /> Commencer mon essai gratuit</button>
        </form>
        <p><a href="#formules">Voir les formules</a></p>
    @else
        <p class="result-next-text">{{ $nextText }}</p>
        <a class="btn btn-accent btn-lg btn-next" href="{{ $nextHref }}">{{ $nextLabel }} <x-icon name="arrow-right" /></a>
    @endif
    <x-install-hint />
</div>
