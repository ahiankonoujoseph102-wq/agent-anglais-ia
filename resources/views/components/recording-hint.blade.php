{{--
    Rappel discret de « Enregistrer ma séance » (évaluation gratuite, séance du jour) : UNE ligne sous le bouton
    principal, jamais à sa place. La vidéo est fabriquée et gardée sur le téléphone (public/js/session-recorder.js).
--}}
@props(['share' => null, 'teacher' => null])
@php
    // Professeur de la séance concernée : celui de l'apprenant (séance du jour), sinon le professeur par défaut.
    $teacherName = ($teacher ?? \App\Support\Teachers\Teachers::forUser(auth()->user()))->name();
@endphp

<p {{ $attributes->merge(['class' => 'recording-hint']) }}>
    <x-icon name="video" />
    <span>
        Pensez à toucher « Enregistrer ma séance » avant « Commencer » : vous gardez sur votre téléphone la vidéo de votre séance avec {{ $teacherName }}.
        @if ($share)<a href="#{{ $share }}">Comment partager ma vidéo</a>@endif
    </span>
</p>
