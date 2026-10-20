{{--
    Formulaire étroit avec un petit panneau d'aide à côté, sur grand écran seulement (900 pixels et plus).
    Téléphone et tablette : le panneau est caché, la page reste exactement la même.
    <x-form-aside> … formulaire … <x-slot:aside> … panneau … </x-slot:aside> </x-form-aside>
--}}
@props(['label' => 'Aide'])

<div class="with-aside">
    <div class="with-aside-main">{{ $slot }}</div>
    <aside class="with-aside-panel card" aria-label="{{ $label }}">{{ $aside }}</aside>
</div>
