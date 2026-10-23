<x-layout :indexable="false" title="Trop de demandes" narrow>
    <section class="empty-state">
        <div class="code" aria-hidden="true">429</div>
        <h1>Trop de demandes en peu de temps</h1>
        <p class="lead">Par sécurité, le site fait une courte pause. Patientez une minute, puis réessayez.</p>
        <div class="actions">
            <a class="btn btn-accent btn-block" href="{{ url()->previous() !== url()->current() ? url()->previous() : (auth()->check() ? route('dashboard') : route('home')) }}"><x-icon name="refresh" /> Réessayer</a>
            <a class="btn btn-outline btn-block" href="{{ auth()->check() ? route('dashboard') : route('home') }}"><x-icon name="home" /> {{ auth()->check() ? 'Retour à mon espace' : 'Retour à l\'accueil' }}</a>
        </div>
    </section>
</x-layout>
