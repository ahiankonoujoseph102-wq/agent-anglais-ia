<x-layout :indexable="false" title="Page introuvable" narrow>
    @php($whatsapp = \App\Support\WhatsApp::communityUrl())
    <section class="empty-state">
        <x-teacher-face />
        <div class="code" aria-hidden="true">404</div>
        <h1>Page introuvable</h1>
        <p class="lead">L'adresse demandée n'existe pas ou n'est plus disponible. Voici où aller :</p>
        <div class="actions">
            <a class="btn btn-accent btn-block" href="{{ auth()->check() ? route('dashboard') : route('home') }}"><x-icon name="home" /> {{ auth()->check() ? 'Retour à mon espace' : 'Retour à l\'accueil' }}</a>
            <a class="btn btn-outline btn-block" href="{{ \App\Support\Navigation::assessmentUrl() }}"><x-icon name="mic" /> Passer mon évaluation gratuite</a>
            <a class="btn btn-outline btn-block" href="{{ route('plans') }}"><x-icon name="wallet" /> Voir les formules et les prix</a>
            @if ($whatsapp)
                <a class="btn btn-whatsapp btn-block" href="{{ $whatsapp }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Nous écrire sur WhatsApp</a>
            @endif
            <a class="btn btn-outline btn-block" href="{{ route('contact') }}">Nous contacter</a>
        </div>
    </section>
</x-layout>
