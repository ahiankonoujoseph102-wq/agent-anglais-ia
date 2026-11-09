<x-layout title="Connexion à votre compte" :description="'Connectez-vous à '.config('platform.name').' avec votre numéro de téléphone pour retrouver '.config('avatar.model.name').', votre séance du jour et votre programme d\'anglais.'" narrow>
    <x-form-aside :label="'Votre professeur, '.config('avatar.model.name')">
        <div class="auth-head rise">
            <span class="icon-bubble icon-bubble-primary"><x-icon name="login" /></span>
            <h1>Connexion</h1>
            <p>Retrouvez votre professeur et votre séance du jour.</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="card form-card rise" style="--i: 1" novalidate>
            @csrf

            <x-input name="phone" label="Numéro de téléphone" type="tel" inputmode="tel" autocomplete="tel" required autofocus
                     hint="Hors du Togo : avec l'indicatif de votre pays, par exemple +237 6 77 12 34 56." />
            <x-input name="password" label="Mot de passe" type="password" autocomplete="current-password" required />

            <div class="field">
                <label class="checkbox">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    Rester connecté sur ce téléphone
                </label>
            </div>

            <button type="submit" class="btn btn-accent btn-block btn-lg">Se connecter</button>
            <p class="center next-step"><a href="{{ route('password.forgot') }}">Mot de passe oublié ?</a></p>
        </form>

        <div class="form-footer">
            <p>Pas encore de compte ?</p>
            <a class="btn btn-outline btn-block" href="{{ route('register') }}">Créer un compte</a>
        </div>

        <x-slot:aside><x-auth-aside /></x-slot:aside>
    </x-form-aside>
</x-layout>
