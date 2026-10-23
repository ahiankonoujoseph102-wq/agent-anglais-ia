<x-simple-layout title="Hors connexion">
    <section class="empty-state">
        <span class="icon-bubble icon-bubble-accent"><x-icon name="wifi-off" /></span>
        <h1>Vous êtes hors connexion</h1>
        <p class="lead">Cette page a besoin d'internet. Vérifiez vos données mobiles ou votre Wi-Fi, puis réessayez.</p>
        <p class="muted">Vos séances avec le professeur demandent une connexion pendant toute leur durée.</p>
        <div class="actions">
            <a class="btn btn-accent btn-lg btn-block" href="{{ route('dashboard') }}" onclick="location.reload(); return false;"><x-icon name="refresh" /> Réessayer</a>
        </div>
    </section>
</x-simple-layout>
