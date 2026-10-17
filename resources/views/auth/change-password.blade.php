<x-layout title="Nouveau mot de passe" narrow>
    <div class="auth-head rise">
        <span class="icon-bubble icon-bubble-primary"><x-icon name="lock" /></span>
        <h1>Choisissez un nouveau mot de passe</h1>
        <p>Vous vous êtes connecté avec un mot de passe provisoire. Choisissez-en un nouveau pour continuer.</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="card form-card rise" style="--i: 1" novalidate>
        @csrf
        @method('PUT')

        <x-input name="password" label="Nouveau mot de passe" type="password" autocomplete="new-password" hint="8 caractères au minimum." required autofocus />
        <x-input name="password_confirmation" label="Confirmez le mot de passe" type="password" autocomplete="new-password" required />

        <button type="submit" class="btn btn-block btn-lg">Enregistrer</button>
    </form>
</x-layout>
