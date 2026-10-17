<x-simple-layout title="Erreur du serveur">
    <section class="empty-state">
        <div class="code" aria-hidden="true">500</div>
        <h1>Un problème est survenu</h1>
        <p class="lead">Le site a rencontré une erreur. Réessayez dans quelques instants. Si le problème continue, contactez-nous.</p>
        <p class="muted">{{ config('platform.contact.hours') }} · {{ config('platform.contact.phones')[0] ?? '' }}</p>
        <div class="actions">
            <a class="btn btn-block" href="{{ url('/') }}"><x-icon name="home" /> Retour à l'accueil</a>
        </div>
    </section>
</x-simple-layout>
