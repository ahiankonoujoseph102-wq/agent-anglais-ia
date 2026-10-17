{{--
    Photo du site (config/photos.php). Téléphone et tablette : toujours la version 480 pixels
    (légère, même sur un écran haute densité) ; ordinateur (900 pixels et plus) : 960 pixels.
    Chargée seulement quand elle approche de l'écran (loading="lazy").
--}}
@props(['name'])

@php
    $photo = config("photos.{$name}");
@endphp

<picture>
    <source media="(min-width: 900px)" srcset="{{ \App\Support\Asset::url("images/photos/{$name}-960.webp") }}">
    <img src="{{ \App\Support\Asset::url("images/photos/{$name}-480.webp") }}" width="480" height="360"
         alt="{{ $photo['alt'] }}" loading="lazy" decoding="async" {{ $attributes->class(['photo']) }}>
</picture>
