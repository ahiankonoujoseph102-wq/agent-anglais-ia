<x-layout title="Connexion" narrow>
    <h1>Connexion</h1>

    <form method="POST" action="{{ route('login') }}" class="card" novalidate>
        @csrf

        <x-input name="phone" label="Numéro de téléphone" type="tel" inputmode="tel" autocomplete="tel" required autofocus />
        <x-input name="password" label="Mot de passe" type="password" autocomplete="current-password" required />

        <div class="field">
            <label class="checkbox">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                Rester connecté sur ce téléphone
            </label>
        </div>

        <button type="submit" class="btn btn-block">Se connecter</button>
    </form>

    <p>Pas encore de compte ? <a href="{{ route('register') }}">Créer un compte</a></p>
</x-layout>
