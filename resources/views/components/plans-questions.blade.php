{{--
    Une ligne de texte sous les cartes des formules (pages /formules et /abonnement) vers la vidéo
    « Vos questions, mes réponses » de la page « Comment ça marche » (clé videos.presentations.site.plans_link).
    Montrée seulement quand cette vidéo a sa version « site » (sinon l'ancre n'existerait pas) ; rien n'est chargé ici.
--}}
@php
    $key = config('videos.presentations.site.plans_link');
    $video = $key ? app(\App\Support\Videos\SiteVideos::class)->find($key) : null;
@endphp

@if ($video)
    <p class="plans-questions">
        <a href="{{ route('how') }}#{{ $video->key }}"><x-icon name="video" /> <span>Des questions avant de choisir ? Regardez « {{ $video->title }} ».</span></a>
    </p>
@endif
