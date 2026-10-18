<x-layout :indexable="false" title="Page expirée" narrow>
    <section class="empty-state">
        <span class="icon-bubble"><x-icon name="clock" /></span>
        <h1>Page expirée</h1>
        <p class="lead">Cette page est restée ouverte trop longtemps. Rechargez-la, puis recommencez votre action.</p>
        <div class="actions">
            <a class="btn btn-block" href="{{ url()->previous() }}"><x-icon name="refresh" /> Recharger la page</a>
            <a class="btn btn-light btn-block" href="{{ route('home') }}">Retour à l'accueil</a>
        </div>
    </section>
</x-layout>
