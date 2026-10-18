<x-layout title="Mot de passe oublié" narrow>
    <div class="auth-head rise">
        <span class="icon-bubble icon-bubble-primary"><x-icon name="lock" /></span>
        <h1>Mot de passe oublié ?</h1>
        <p>Pas de panique. Voici comment retrouver l'accès à votre compte.</p>
    </div>

    <div class="card form-card rise" style="--i: 1">
        <ol class="steps">
            <li>Indiquez ci-dessous le numéro de téléphone de votre compte.</li>
            <li>Appuyez sur « Demander sur WhatsApp » : un message déjà rédigé s'ouvre, envoyez-le.</li>
            <li>Nous vous répondons avec un mot de passe provisoire ({{ \Illuminate\Support\Str::lcfirst(config('platform.contact.hours')) }}).</li>
            <li>Connectez-vous avec : le site vous demandera aussitôt d'en choisir un nouveau.</li>
        </ol>

        <form method="GET" action="{{ route('password.forgot') }}" class="next-step" novalidate>
            <div class="field">
                <label for="telephone">Numéro de téléphone du compte</label>
                <input id="telephone" name="telephone" type="tel" inputmode="tel" autocomplete="tel" value="{{ $input }}" required
                       @class(['is-invalid' => $invalid]) @if ($invalid) aria-invalid="true" aria-describedby="telephone-error" @endif>
                @if ($invalid)
                    <div class="error" id="telephone-error">Ce numéro de téléphone n'est pas valide. Hors du Togo, écrivez-le avec l'indicatif de votre pays, par exemple +237 6 77 12 34 56.</div>
                @endif
            </div>
            <button type="submit" class="btn btn-light btn-block">Préparer le message</button>
        </form>

        @if ($whatsappUrl)
            <div class="alert alert-success next-step rise">
                <x-icon name="check-circle" />
                <div>Message prêt pour le compte <strong>{{ $phone }}</strong>.</div>
            </div>
            <a class="btn btn-whatsapp btn-block btn-lg" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Demander sur WhatsApp</a>
        @endif
    </div>

    <div class="form-footer">
        <p>Vous pouvez aussi nous appeler : <a href="{{ route('contact') }}">nos coordonnées</a>.</p>
        <p><a class="back-link" href="{{ route('login') }}"><x-icon name="arrow-left" /> Retour à la connexion</a></p>
    </div>
</x-layout>
