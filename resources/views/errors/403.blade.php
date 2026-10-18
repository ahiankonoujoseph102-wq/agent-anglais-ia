<x-layout :indexable="false" title="Accès refusé" narrow>
    <section class="empty-state">
        <div class="code" aria-hidden="true">403</div>
        <h1>Accès refusé</h1>
        <p class="lead">Vous n'avez pas accès à cette page.</p>
        <div class="actions">
            <a class="btn btn-block" href="{{ auth()->check() ? route('dashboard') : route('home') }}"><x-icon name="home" /> {{ auth()->check() ? 'Retour à mon espace' : 'Retour à l\'accueil' }}</a>
        </div>
    </section>
</x-layout>
