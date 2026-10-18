<x-layout title="Inscription et évaluation d'anglais gratuite" narrow :description="'Créez votre compte '.config('platform.name').' avec votre nom et votre numéro de téléphone, puis passez votre évaluation d\'anglais orale gratuite avec '.config('avatar.model.name').'.'">
    <div class="auth-head rise">
        <span class="icon-bubble icon-bubble-primary"><x-icon name="user-plus" /></span>
        <h1>Créer mon compte</h1>
        <p>Gratuit. Votre évaluation orale vous attend juste après.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="card form-card rise" style="--i: 1" novalidate>
        @csrf

        <x-input name="name" label="Nom complet" autocomplete="name" required autofocus />
        <x-input name="phone" label="Numéro de téléphone" type="tel" inputmode="tel" autocomplete="tel" required
                 hint="Exemple : 90 12 34 56 ou +228 90 12 34 56" />
        <x-input name="email" label="Adresse e-mail" type="email" inputmode="email" autocomplete="email"
                 :hint="\App\Support\EmailAddress::HINT" />
        <x-input name="password" label="Mot de passe" type="password" autocomplete="new-password" required
                 hint="8 caractères minimum." />
        <x-input name="password_confirmation" label="Confirmer le mot de passe" type="password" autocomplete="new-password" required />

        <button type="submit" class="btn btn-accent btn-block btn-lg">Créer mon compte</button>
        <p class="muted small register-privacy">En créant un compte, vous acceptez notre <a href="{{ route('privacy') }}">politique de confidentialité</a>.</p>
    </form>

    <div class="form-footer">
        <p>Déjà inscrit ? <a href="{{ route('login') }}">Se connecter</a></p>
    </div>
</x-layout>
