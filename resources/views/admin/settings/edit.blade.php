{{--
    Chaque champ montre la valeur SAISIE (vide si rien n'est saisi) ; sous le champ,
    la valeur par défaut de la configuration, utilisée tant que le champ est vide.
--}}
@php
    $defaultHint = function (string $key, string $none) use ($fields): string {
        $field = $fields[$key];

        if ($field['default'] === null) {
            return $none;
        }

        $default = str_replace("\n", ' · ', $field['default']);

        return $field['saved'] === null
            ? 'Champ vide : la valeur par défaut est utilisée : '.$default
            : 'Valeur par défaut, utilisée si vous videz ce champ : '.$default;
    };
@endphp

<x-layout title="Réglages">
    @include('admin.nav')

    <h1>Réglages</h1>
    <p class="muted">
        Rien n'est obligatoire : chaque champ vide utilise la valeur par défaut indiquée en dessous.
        Une valeur saisie ici remplace la valeur par défaut, dès l'enregistrement.
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
                        @php
                            $numberKey = \App\Support\SiteSettings::operatorNumberKey($country->code, $operator->key);
                            $holderKey = \App\Support\SiteSettings::operatorHolderKey($country->code, $operator->key);
                        @endphp
                        <div class="settings-operator">
                            <h3>{{ $operator->name }}</h3>
                            <x-input :name="'operators['.$country->code.']['.$operator->key.'][number]'" :label="'Numéro de réception '.$operator->name"
                                     type="tel" inputmode="tel" :value="old('operators.'.$country->code.'.'.$operator->key.'.number', $fields[$numberKey]['saved'])"
                                     :placeholder="$fields[$numberKey]['default']"
                                     :hint="$defaultHint($numberKey, 'Aucune valeur par défaut : laissez vide pour ne pas proposer cet opérateur.')" />
                            @error('operators.'.$country->code.'.'.$operator->key.'.number')
                                <div class="error field">{{ $message }}</div>
                            @enderror
                            <x-input :name="'operators['.$country->code.']['.$operator->key.'][holder]'" :label="'Nom du titulaire '.$operator->name"
                                     :value="old('operators.'.$country->code.'.'.$operator->key.'.holder', $fields[$holderKey]['saved'])"
                                     :placeholder="$fields[$holderKey]['default']"
                                     :hint="$defaultHint($holderKey, 'Aucune valeur par défaut.')" />
                        </div>
                    @endforeach
                </fieldset>
            @endforeach
        </section>

        <section class="card">
            <h2>Coordonnées du centre</h2>
            <p class="muted small">Affichées dans le pied de page, sur la page Contact, les attestations et les boutons WhatsApp.</p>

            <x-input name="contact[whatsapp]" label="Numéro WhatsApp" type="tel" inputmode="tel" :value="old('contact.whatsapp', $fields['contact.whatsapp']['saved'])"
                     :placeholder="$fields['contact.whatsapp']['default']"
                     :hint="'Utilisé par tous les boutons WhatsApp du site (aide, paiement, mot de passe oublié). '.$defaultHint('contact.whatsapp', 'Aucune valeur par défaut.')" />
            @error('contact.whatsapp')
                <div class="error field">{{ $message }}</div>
            @enderror

            <div class="field">
                <label for="contact-phones">Numéros de contact</label>
                <textarea id="contact-phones" name="contact[phones]" rows="3" placeholder="{{ $fields['contact.phones']['default'] }}"
                          @class(['is-invalid' => $errors->has('contact.phones')])>{{ old('contact.phones', $fields['contact.phones']['saved']) }}</textarea>
                <div class="hint">Un numéro par ligne. {{ $defaultHint('contact.phones', 'Aucune valeur par défaut.') }}</div>
                @error('contact.phones')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <x-input name="contact[email]" label="Adresse e-mail" type="email" :value="old('contact.email', $fields['contact.email']['saved'])"
                     :placeholder="$fields['contact.email']['default']" :hint="$defaultHint('contact.email', 'Aucune valeur par défaut.')" />
            @error('contact.email')
                <div class="error field">{{ $message }}</div>
            @enderror
            <x-input name="contact[address]" label="Adresse" :value="old('contact.address', $fields['contact.address']['saved'])"
                     :placeholder="$fields['contact.address']['default']" :hint="$defaultHint('contact.address', 'Aucune valeur par défaut.')" />
        </section>

        <button type="submit" class="btn btn-accent btn-block btn-lg">Enregistrer les réglages</button>
    </form>
</x-layout>
