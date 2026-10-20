{{-- Envoi plus lourd que ce que le serveur accepte (sans JavaScript ; avec, le message s'affiche sur la page même). --}}
<x-layout :indexable="false" title="Fichier trop lourd" narrow>
    <section class="empty-state">
        <div class="code" aria-hidden="true">413</div>
        <h1>Fichier trop lourd</h1>
        <p class="lead">Votre fichier est trop lourd pour être envoyé : envoyez seulement les pages utiles.</p>
        <div class="actions">
            <a class="btn btn-block" href="{{ route('courses.create') }}"><x-icon name="file-text" /> Choisir un autre fichier</a>
            <a class="btn btn-light btn-block" href="{{ route('dashboard') }}"><x-icon name="home" /> Retour à mon espace</a>
        </div>
    </section>
</x-layout>
