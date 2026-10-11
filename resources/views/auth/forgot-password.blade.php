<x-layout title="Mot de passe oublié" narrow>
    <h1>Mot de passe oublié ?</h1>

    <div class="card">
        <p>Pas de panique. Pour retrouver l'accès à votre compte :</p>
        <ol>
            <li>Indiquez ci-dessous le numéro de téléphone de votre compte.</li>
            <li>Appuyez sur « Demander sur WhatsApp » : un message déjà rédigé s'ouvre, envoyez-le.</li>
            <li>Nous vous répondons avec un mot de passe provisoire ({{ \Illuminate\Support\Str::lcfirst(config('platform.contact.hours')) }}).</li>
            <li>Connectez-vous avec : le site vous demandera aussitôt d'en choisir un nouveau.</li>
        </ol>

        <form method="GET" action="{{ route('password.forgot') }}" novalidate>
            <div class="field">
                <label for="telephone">Numéro de téléphone du compte</label>
                <input id="telephone" name="telephone" type="tel" inputmode="tel" autocomplete="tel" value="{{ $input }}" required
                       @class(['is-invalid' => $invalid]) @if ($invalid) aria-invalid="true" aria-describedby="telephone-error" @endif>
                @if ($invalid)
                    <div class="error" id="telephone-error">Ce numéro de téléphone n'est pas valide. Exemple : 90 12 34 56 ou +228 90 12 34 56.</div>
                @endif
            </div>
            <button type="submit" class="btn btn-light btn-block">Préparer le message</button>
        </form>

        @if ($whatsappUrl)
            <p class="next-step">Message prêt pour le compte <strong>{{ $phone }}</strong> :</p>
            <a class="btn btn-whatsapp btn-block" href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Demander sur WhatsApp</a>
        @endif
    </div>

    <p>Vous pouvez aussi nous appeler : <a href="{{ route('contact') }}">nos coordonnées</a>.</p>
    <p><a href="{{ route('login') }}">← Retour à la connexion</a></p>
</x-layout>
