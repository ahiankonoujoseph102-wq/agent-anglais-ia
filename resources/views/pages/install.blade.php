{{--
    Installer l'application : la fenêtre du navigateur quand il la propose (bouton, public/js/app.js), sinon les
    explications pas à pas pour Android et pour iPhone. Page utile aussi sans JavaScript. Pas dans le référencement.
--}}
@php
    $platform = config('platform.name');
    $teacher = config('avatar.model.name');
@endphp

<x-layout title="Installer l'application" :description="'Installez '.$platform.' sur votre téléphone pour retrouver '.$teacher.' en un geste.'" narrow>
    <div class="auth-head rise">
        <span class="icon-bubble icon-bubble-primary"><x-icon name="download" /></span>
        <h1>Installer l'application</h1>
        <p>Installez {{ $platform }} pour retrouver {{ $teacher }} en un geste, depuis l'écran d'accueil de votre téléphone, comme une application.</p>
    </div>

    {{-- Application déjà installée (ouverte depuis l'écran d'accueil) : rien à faire. --}}
    <div class="alert alert-success install-done" role="status">
        <x-icon name="check-circle" />
        <div>{{ $platform }} est déjà installée sur ce téléphone : vous l'avez ouverte depuis votre écran d'accueil.</div>
    </div>

    <section class="card rise install-steps" style="--i: 1" aria-labelledby="install-android" data-install-link-item>
        <h2 id="install-android">Sur Android (Chrome, Samsung Internet)</h2>
        {{-- Affiché seulement quand le navigateur propose l'installation (public/js/app.js). --}}
        <button type="button" class="btn btn-accent btn-block btn-lg" data-install-button data-install-now hidden><x-icon name="download" /> Installer maintenant</button>
        <p class="muted">En trois gestes :</p>
        <ol class="install-list">
            <li>Touchez le menu du navigateur <strong>⋮</strong>, en haut à droite.</li>
            <li>Touchez « Installer l'application » ou « Ajouter à l'écran d'accueil ».</li>
            <li>Confirmez : l'icône de {{ $platform }} apparaît sur votre écran d'accueil.</li>
        </ol>
    </section>

    <section class="card rise install-steps" style="--i: 2" aria-labelledby="install-iphone" data-install-link-item>
        <h2 id="install-iphone">Sur iPhone et iPad (Safari)</h2>
        <ol class="install-list">
            <li>Touchez « Partager » <x-icon name="share" label="Partager" />, en bas de l'écran.</li>
            <li>Touchez « Sur l'écran d'accueil ».</li>
            <li>Touchez « Ajouter » : l'icône de {{ $platform }} apparaît sur votre écran d'accueil.</li>
        </ol>
    </section>

    <p class="muted small next-step">L'application ne prend presque pas de place : c'est le même site, ouvert en plein écran, sans barre d'adresse. Vos séances, votre programme et vos cours restent les mêmes.</p>
</x-layout>
