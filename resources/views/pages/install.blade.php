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

    {{-- Juste installée (événement « appinstalled », public/js/app.js) : où la trouver. --}}
    <div class="alert alert-success install-success" role="status">
        <x-icon name="check-circle" />
        <div>{{ $platform }} est installée : vous la trouverez parmi les applications de votre téléphone, avec son icône.</div>
    </div>

    <section class="card rise install-steps" style="--i: 1" aria-labelledby="install-android" data-install-link-item>
        <h2 id="install-android">Sur Android (Chrome, Samsung Internet)</h2>
        {{-- Affiché seulement quand le navigateur propose l'installation (public/js/app.js) : il ouvre sa fenêtre. --}}
        <button type="button" class="btn btn-accent btn-block btn-lg" data-install-button data-install-now hidden><x-icon name="download" /> Installer maintenant</button>
        {{-- En attendant la proposition du navigateur (avec JavaScript seulement). --}}
        <p class="install-wait muted" data-install-wait role="status">
            <span class="install-wait-soon">Touchez la page et patientez quelques secondes : le bouton « Installer maintenant » va apparaître.</span>
            <span class="install-wait-late">Votre navigateur ne propose pas l'installation directe : suivez les étapes ci-dessous.</span>
        </p>
        <p class="muted">Ou par le menu du navigateur :</p>
        <ol class="install-list">
            <li>Touchez le menu du navigateur <strong>⋮</strong>, en haut à droite.</li>
            <li>Touchez « Installer l'application ». Si vous voyez seulement « Ajouter à l'écran d'accueil », touchez-le, puis choisissez « Installer », jamais « Créer un raccourci ».</li>
            <li>Confirmez avec « Installer » : après quelques secondes, {{ $platform }} apparaît parmi les applications de votre téléphone.</li>
        </ol>
        <h3 class="install-sub">Vous avez déjà une icône {{ $platform }} avec le petit logo de Chrome ?</h3>
        <p class="muted">C'est un simple raccourci vers le site, pas l'application. Retirez-le d'abord :</p>
        <ol class="install-list">
            <li>Appuyez longuement sur l'icône, puis touchez « Supprimer » (ou faites-la glisser vers « Supprimer »).</li>
            <li>Revenez sur cette page et touchez « Installer maintenant ».</li>
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

    <p class="muted small next-step">L'application ne prend presque pas de place : c'est le même site, ouvert en plein écran, sans barre d'adresse, avec son icône parmi vos applications. Vos séances, votre programme et vos cours restent les mêmes.</p>
</x-layout>
