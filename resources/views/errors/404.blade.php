<x-layout :indexable="false" title="Page introuvable" narrow>
    <section class="empty-state">
        <div class="code" aria-hidden="true">404</div>
        <h1>Page introuvable</h1>
        <p class="lead">L'adresse demandée n'existe pas ou n'est plus disponible.</p>
        <div class="actions">
            <a class="btn btn-block" href="{{ auth()->check() ? route('dashboard') : route('home') }}"><x-icon name="home" /> {{ auth()->check() ? 'Retour à mon espace' : 'Retour à l\'accueil' }}</a>
            <a class="btn btn-light btn-block" href="{{ route('contact') }}">Nous contacter</a>
        </div>
    </section>
</x-layout>
