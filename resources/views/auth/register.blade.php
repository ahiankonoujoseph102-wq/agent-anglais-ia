<x-layout title="Inscription" narrow>
    <div class="auth-head rise">
        <span class="icon-bubble icon-bubble-accent"><x-icon name="user" /></span>
        <h1>Créer mon compte</h1>
        <p>Gratuit. Votre évaluation orale vous attend juste après.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="card form-card rise" style="--i: 1" novalidate>
        @csrf

        <x-input name="name" label="Nom complet" autocomplete="name" required autofocus />
        <x-input name="phone" label="Numéro de téléphone" type="tel" inputmode="tel" autocomplete="tel" required
                 hint="Exemple : 90 12 34 56 ou +228 90 12 34 56" />
        <x-input name="password" label="Mot de passe" type="password" autocomplete="new-password" required
                 hint="8 caractères minimum." />
        <x-input name="password_confirmation" label="Confirmer le mot de passe" type="password" autocomplete="new-password" required />

        <button type="submit" class="btn btn-accent btn-block btn-lg">Créer mon compte</button>
    </form>

    <div class="form-footer">
        <p>Déjà inscrit ? <a href="{{ route('login') }}">Se connecter</a></p>
    </div>
</x-layout>
