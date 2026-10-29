<x-layout title="Inscription et évaluation d'anglais gratuite" narrow :description="'Créez votre compte '.config('platform.name').' avec votre nom et votre numéro de téléphone, puis passez votre évaluation d\'anglais orale gratuite avec '.config('avatar.model.name').'.'">
    <x-form-aside :label="'Votre professeur, '.config('avatar.model.name')">
        <div class="auth-head register-head rise">
            <span class="icon-bubble icon-bubble-primary"><x-icon name="user-plus" /></span>
            <h1>Créer mon compte gratuit</h1>
        </div>

        <form method="POST" action="{{ route('register') }}" class="card form-card register-form rise" style="--i: 1" novalidate>
            @csrf

            <x-input name="name" label="Nom complet" autocomplete="name" required autofocus />
            {{-- Pays : tous les pays du monde (pays de paiement d'abord, puis pays francophones), ou « Autre pays ».
                 Replié sur le Togo (public d'abord togolais) pour que « Créer mon compte » reste visible sans défiler ;
                 ouvert dès qu'un autre pays est choisi ou en cas d'erreur. Fermé, le champ est tout de même envoyé. --}}
            @php($country = old('country', 'TG'))
            <details class="optional-field country-field" @if ($country !== 'TG' || $errors->has('country')) open @endif>
                <summary>Pas au Togo ? Choisir votre pays</summary>
                <div class="field">
                    <label for="country">Pays</label>
                    <select id="country" name="country" autocomplete="country" required
                            @error('country') aria-describedby="country-error" aria-invalid="true" class="is-invalid" @enderror>
                        @foreach (\App\Support\Countries::choices() as $group => $options)
                            <optgroup label="{{ $group }}">
                                @foreach ($options as $code => $name)
                                    <option value="{{ $code }}" @selected($country === $code)>{{ $name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('country')
                        <p id="country-error" class="error">{{ $message }}</p>
                    @enderror
                </div>
            </details>
            <x-input name="phone" label="Numéro de téléphone" type="tel" inputmode="tel" autocomplete="tel" required
                     hint="Exemple : 90 12 34 56" />
            {{-- Un seul champ : le bouton « œil » permet de vérifier ce qui a été tapé. --}}
            <x-input name="password" label="Mot de passe" type="password" autocomplete="new-password" required
                     hint="8 caractères minimum." />

            {{-- Facultatif et replié : e-mail (documents de révision) et code ambassadeur. Ouvert et rempli quand la
                 personne est arrivée par le lien d'un ambassadeur (elle le voit et peut le changer), ou en cas d'erreur. --}}
            @php($ambassadorCode = old('ambassador_code', $suggestedCode ?? null))
            <details class="optional-field ambassador-field" @if (filled($ambassadorCode) || filled(old('email')) || $errors->hasAny(['email', 'ambassador_code'])) open @endif>
                <summary>Facultatif : e-mail, code ambassadeur</summary>
                <x-input name="email" label="Adresse e-mail" type="email" inputmode="email" autocomplete="email"
                         :hint="\App\Support\EmailAddress::HINT" />
                <x-input name="ambassador_code" label="Code ambassadeur" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="20"
                         :value="$ambassadorCode" hint="Facultatif. Tapez le code que vous a donné la personne qui vous a parlé de nous." />
            </details>

            <button type="submit" class="btn btn-accent btn-block btn-lg">Créer mon compte</button>
            <p class="muted small register-privacy">En créant un compte, vous acceptez notre <a href="{{ route('privacy') }}">politique de confidentialité</a>.</p>
        </form>

        <div class="form-footer">
            <p>Déjà inscrit ? <a href="{{ route('login') }}">Se connecter</a></p>
        </div>

        <x-slot:aside><x-auth-aside /></x-slot:aside>
    </x-form-aside>
</x-layout>
