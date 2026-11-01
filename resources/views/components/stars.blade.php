{{-- Note d'un avis en étoiles (1 à 5), lue « 4 sur 5 » par les lecteurs d'écran. --}}
@props(['rating'])
@php($rating = max(0, min(5, (int) $rating)))
<span {{ $attributes->class('stars') }} role="img" aria-label="{{ $rating }} sur 5">
    @for ($i = 1; $i <= 5; $i++)
        <x-icon name="star" @class(['star', 'is-on' => $i <= $rating]) />
    @endfor
</span>
