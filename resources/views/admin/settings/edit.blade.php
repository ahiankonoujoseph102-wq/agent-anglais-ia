<x-layout title="Réglages">
    @include('admin.nav')

    <h1>Réglages</h1>
    <p class="muted">
        Ces informations sont enregistrées dans la base de données, jamais dans le code du site.
        Un opérateur sans numéro n'est pas proposé aux apprenants.
    </p>

    @if ($errors->any())
        <div class="alert alert-error" role="alert"><x-icon name="alert" /><div>Certains champs sont à corriger, voir ci-dessous.</div></div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" novalidate>
        @csrf
        @method('PUT')

        <section class="card">
            <h2>Numéros de paiement Mobile Money</h2>
            <p class="muted small">Le numéro sur lequel les apprenants envoient l'argent, et le nom du titulaire tel qu'il apparaît chez l'opérateur.</p>

            @foreach ($countries as $country)
                <fieldset class="settings-country">
                    <legend>{{ $country->name }}</legend>
                    @foreach ($country->operators as $operator)
                        <div class="settings-operator">
                            <h3>{{ $operator->name }}</h3>
                            <x-input :name="'operators['.$country->code.']['.$operator->key.'][number]'" :label="'Numéro de réception '.$operator->name"
                                     type="tel" inputmode="tel" :value="old('operators.'.$country->code.'.'.$operator->key.'.number', $operator->number)"
                                     :hint="'Exemple : +'.$country->dialCode.' 90 00 00 00. Laissez vide pour ne pas proposer cet opérateur.'" />
                            @error('operators.'.$country->code.'.'.$operator->key.'.number')
                                <div class="error field">{{ $message }}</div>
                            @enderror
                            <x-input :name="'operators['.$country->code.']['.$operator->key.'][holder]'" :label="'Nom du titulaire '.$operator->name"
                                     :value="old('operators.'.$country->code.'.'.$operator->key.'.holder', $operator->holder)" />
                        </div>
                    @endforeach
                </fieldset>
            @endforeach
        </section>

        <section class="card">
            <h2>Coordonnées du centre</h2>
            <p class="muted small">Affichées dans le pied de page, sur la page Contact et sur les boutons WhatsApp.</p>

            <x-input name="contact[whatsapp]" label="Numéro WhatsApp" type="tel" inputmode="tel" :value="old('contact.whatsapp', $contact['whatsapp'])"
                     hint="Utilisé par tous les boutons WhatsApp du site (aide, paiement, mot de passe oublié)." />
            @error('contact.whatsapp')
                <div class="error field">{{ $message }}</div>
            @enderror

            <div class="field">
                <label for="contact-phones">Numéros de contact</label>
                <textarea id="contact-phones" name="contact[phones]" rows="3" @class(['is-invalid' => $errors->has('contact.phones')])>{{ old('contact.phones', implode("\n", $contact['phones'])) }}</textarea>
                <div class="hint">Un numéro par ligne.</div>
                @error('contact.phones')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <x-input name="contact[email]" label="Adresse e-mail" type="email" :value="old('contact.email', $contact['email'])" />
            @error('contact.email')
                <div class="error field">{{ $message }}</div>
            @enderror
            <x-input name="contact[address]" label="Adresse" :value="old('contact.address', $contact['address'])" />
        </section>

        <button type="submit" class="btn btn-accent btn-block btn-lg">Enregistrer les réglages</button>
    </form>
</x-layout>
