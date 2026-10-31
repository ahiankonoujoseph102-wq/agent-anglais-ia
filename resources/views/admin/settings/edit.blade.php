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

        <section class="card" id="nouvelle-evaluation">
            <h2>Nouvelle évaluation</h2>
            <p class="muted small">
                Nouvelle façon de mener l'évaluation gratuite (accueil, hésitation remarquée, français quand la personne bloque,
                vrai niveau). Les prospects gardent l'évaluation actuelle tant que « Tout le monde » n'est pas choisi.
                Un compte de test se marque dans la fiche d'un inscrit (case « Compte de test »).
            </p>
            <fieldset>
                <legend>Qui passe la nouvelle évaluation ?</legend>
                @foreach (\App\Support\Live\AssessmentVersion::MODES as $value => $label)
                    <label><input type="radio" name="assessment[new_version]" value="{{ $value }}" @checked(old('assessment.new_version', \App\Support\Live\AssessmentVersion::mode()) === $value)> {{ $label }}</label>
                @endforeach
            </fieldset>
            @error('assessment.new_version')
                <div class="error field">{{ $message }}</div>
            @enderror
        </section>

        <section class="card" id="voix-plus-nette">
            <h2>Voix plus nette</h2>
            <p class="muted small">
                Même voix et mêmes paroles du professeur ; seul le rendu dans le téléphone change : grave sourd retiré,
                consonnes plus nettes, volume régulier et jamais saturé. Rien ne coûte en plus. Pour comparer : ouvrez
                « Ma séance du jour » avec votre compte, puis touchez « Essayer le son plus net » ou « Essayer le son actuel »
                sous « Commencer » (lien visible par l'équipe seulement).
            </p>
            <fieldset>
                <legend>Qui entend la voix plus nette ?</legend>
                @foreach (\App\Support\Live\VoiceClarity::MODES as $value => $label)
                    <label><input type="radio" name="voice[clarity]" value="{{ $value }}" @checked(old('voice.clarity', \App\Support\Live\VoiceClarity::mode()) === $value)> {{ $label }}</label>
                @endforeach
            </fieldset>
            @error('voice.clarity')
                <div class="error field">{{ $message }}</div>
            @enderror
        </section>

        <section class="card" id="alerte-cout">
            <h2>Alerte de coût par abonné</h2>
            <p class="muted small">
                Quand les séances d'un abonné ont déjà coûté plus que ce pourcentage du prix de sa formule (estimation au tarif
                payant des fournisseurs), il apparaît dans « Consommation » et vous êtes prévenu une fois.
            </p>
            <x-input name="cost_alert[percent]" label="Seuil, en pour cent du prix de la formule" type="number" inputmode="numeric"
                     :value="old('cost_alert.percent', $fields['cost_alert.percent']['saved'])" :placeholder="$fields['cost_alert.percent']['default']"
                     :hint="$defaultHint('cost_alert.percent', 'Aucune valeur par défaut.')" />
            @error('cost_alert.percent')
                <div class="error field">{{ $message }}</div>
            @enderror
            <x-input name="cost_alert[xof_per_usd]" label="Francs CFA pour un dollar US" type="number" inputmode="numeric"
                     :value="old('cost_alert.xof_per_usd', $fields['cost_alert.xof_per_usd']['saved'])" :placeholder="$fields['cost_alert.xof_per_usd']['default']"
                     :hint="'Pour comparer le coût (en dollars) au prix (en francs CFA). '.$defaultHint('cost_alert.xof_per_usd', 'Aucune valeur par défaut.')" />
            @error('cost_alert.xof_per_usd')
                <div class="error field">{{ $message }}</div>
            @enderror
        </section>

        <section class="card" id="club-des-abonnes">
            <h2>Club des abonnés</h2>
            <p class="muted small">
                Montré seulement aux abonnés payants actifs (paiement validé, formule en cours) : dans « Mon espace », sous la prochaine étape,
                et sur la page de leur paiement validé. Jamais aux accès offerts, aux ambassadeurs, aux accès provisoires ni aux abonnements terminés.
                Champ vide : l'encadré n'est plus montré.
            </p>
            <x-input name="club[whatsapp_link]" label="Lien du groupe WhatsApp des abonnés" type="url" inputmode="url"
                     :value="old('club.whatsapp_link', $fields['club.whatsapp_link']['saved'])" placeholder="https://chat.whatsapp.com/…" />
            @error('club.whatsapp_link')
                <div class="error field">{{ $message }}</div>
            @enderror
        </section>

        <section class="card" id="ambassadeurs">
            <h2>Programme d'ambassadeurs</h2>
            <p class="muted small">
                Nombre de places : un ambassadeur actif prend une place, un ambassadeur retiré la libère.
                Quand toutes les places sont prises, « Faire ambassadeur » est refusé.
            </p>
            <x-input name="ambassadors[places]" label="Nombre de places" type="number" inputmode="numeric"
                     :value="old('ambassadors.places', $fields['ambassadors.places']['saved'])" :placeholder="$fields['ambassadors.places']['default']"
                     :hint="$defaultHint('ambassadors.places', 'Aucune valeur par défaut.')" />
            @error('ambassadors.places')
                <div class="error field">{{ $message }}</div>
            @enderror
        </section>

        <button type="submit" class="btn btn-accent btn-block btn-lg">Enregistrer les réglages</button>
    </form>
</x-layout>
