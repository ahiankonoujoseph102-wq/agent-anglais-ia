<x-layout title="Inscription" narrow>
    <h1>Créer mon compte</h1>
    <p class="muted">Gratuit. Votre évaluation orale vous attend juste après.</p>

    <form method="POST" action="{{ route('register') }}" class="card" novalidate>
        @csrf

        <x-input name="name" label="Nom complet" autocomplete="name" required autofocus />
        <x-input name="phone" label="Numéro de téléphone" type="tel" inputmode="tel" autocomplete="tel" required
                 hint="Exemple : 90 12 34 56 ou +228 90 12 34 56" />
        <x-input name="password" label="Mot de passe" type="password" autocomplete="new-password" required
                 hint="8 caractères minimum." />
        <x-input name="password_confirmation" label="Confirmer le mot de passe" type="password" autocomplete="new-password" required />

        <button type="submit" class="btn btn-block">Créer mon compte</button>
    </form>

    <p>Déjà inscrit ? <a href="{{ route('login') }}">Se connecter</a></p>
</x-layout>
