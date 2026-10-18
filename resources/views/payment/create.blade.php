<x-layout :title="'Payer la formule '.$plan->name">
    @include('payment.steps', ['current' => $operator ? 3 : 2])

    <div class="step-panel" data-pay-flow>
        <div class="page-head">
            <h1>Formule {{ $plan->name }} : {{ $plan->formattedPrice() }}</h1>
            <p class="muted">
                {{ $plan->dailyMinutes }} minutes par jour, à partager entre la séance orale et « Mes cours », pendant {{ $plan->durationDays }} jours.
                <a href="{{ route('payment.plans') }}">Changer de formule</a>
            </p>
        </div>

        @if ($countries === [])
            {{-- Aucun numéro de réception saisi dans les Réglages : pas d'erreur, un message clair. --}}
            <section class="card card-accent" role="status">
                <div class="card-title">
                    <span class="icon-bubble icon-bubble-accent"><x-icon name="info" /></span>
                    <h2>Le paiement en ligne n'est pas encore ouvert</h2>
                </div>
                <p>Contactez notre équipe : nous vous expliquerons comment payer votre formule.</p>
                @if ($whatsappUrl)
                    <a class="btn btn-whatsapp btn-block btn-lg" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Écrire sur WhatsApp</a>
                @endif
                <a @class(['btn', 'btn-block', 'btn-light' => $whatsappUrl, 'next-step' => $whatsappUrl]) href="{{ route('contact') }}">Voir nos coordonnées</a>
            </section>
        @else
            {{-- 1. Le pays (choisi d'avance d'après l'indicatif du téléphone). --}}
            <section class="card pay-section" id="pays" aria-labelledby="pays-titre">
                <div class="card-title">
                    <span class="icon-bubble"><x-icon name="globe" /></span>
                    <h2 id="pays-titre" tabindex="-1">Dans quel pays payez-vous ?</h2>
                </div>
                @if ($country && $countryFromPhone)
                    <p class="muted small pay-hint">@if ($countryMatchesPhone)Choisi d'après votre numéro de téléphone.@else Choisi d'après le pays de votre compte.@endif Touchez un autre pays pour changer.</p>
                @endif
                <div class="choices">
                    @foreach ($countries as $option)
                        <a href="{{ route('payment.create', ['plan' => $plan->key, 'pays' => $option->code]) }}#{{ count($option->operators) === 1 ? 'paiement' : 'moyen' }}"
                           data-pay-country="{{ $option->code }}"
                           @class(['btn', 'btn-light' => $option->code !== $country?->code])
                           @if ($option->code === $country?->code) aria-current="true" @endif>{{ $option->name }}</a>
                    @endforeach
                </div>
            </section>

            {{-- 2. Le moyen de paiement du pays choisi (les autres pays restent cachés). --}}
            <section class="card pay-section" id="moyen" data-pay-methods @unless ($country) hidden @endunless>
                @foreach ($countries as $option)
                    <div data-pay-methods-for="{{ $option->code }}" @if ($option->code !== $country?->code) hidden @endif>
                        <p class="pay-choice"><x-icon name="pin" /> <strong>{{ $option->name }}</strong> · <a href="#pays" data-pay-edit="pays">Modifier</a></p>
                        <div class="card-title">
                            <span class="icon-bubble"><x-icon name="wallet" /></span>
                            <h2 tabindex="-1">Par quel moyen voulez-vous payer ?</h2>
                        </div>
                        <div class="choices">
                            @foreach ($option->operators as $method)
                                <a href="{{ route('payment.create', ['plan' => $plan->key, 'pays' => $option->code, 'moyen' => $method->key]) }}#paiement"
                                   data-pay-method="{{ $option->code }}.{{ $method->key }}"
                                   @class(['btn', 'btn-light' => ! ($option->code === $country?->code && $method->key === $operator?->key)])
                                   @if ($option->code === $country?->code && $method->key === $operator?->key) aria-current="true" @endif>{{ $method->name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </section>

            {{-- 3. Le paiement : montant, numéro, étapes, puis la capture. --}}
            <form method="POST" action="{{ route('payment.store', $plan->key) }}" enctype="multipart/form-data"
                  class="card card-accent form-card pay-section pay-form" id="paiement" novalidate data-proof-form
                  @unless ($operator) hidden @endunless>
                @csrf
                <input type="hidden" name="country" value="{{ $country?->code }}" data-pay-country-input>
                <input type="hidden" name="operator" value="{{ $operator?->key }}" data-pay-method-input>

                @foreach ($countries as $option)
                    @foreach ($option->operators as $method)
                        @php($selected = $option->code === $country?->code && $method->key === $operator?->key)
                        <div class="pay-block" data-pay-block="{{ $option->code }}.{{ $method->key }}" @unless ($selected) hidden @endunless>
                            <p class="pay-choice"><x-icon name="pin" /> <strong>{{ $option->name }} · {{ $method->name }}</strong> · <a href="{{ route('payment.create', ['plan' => $plan->key, 'pays' => $option->code]) }}#moyen" data-pay-edit="moyen">Modifier</a></p>
                            <h2 tabindex="-1">Payez avec {{ $method->name }}</h2>

                            <div class="pay-amount">
                                <span>Montant exact à envoyer</span>
                                <strong>{{ $plan->formattedPrice() }}</strong>
                            </div>

                            <div class="pay-number">
                                <div>
                                    <span class="pay-label">Numéro sur lequel envoyer l'argent</span>
                                    <span class="number">{{ $method->displayNumber() }}</span>
                                    @if ($method->holder)
                                        <span class="muted">Au nom de <strong>{{ $method->holder }}</strong></span>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-light" data-copy="{{ $method->localNumber() }}"
                                        aria-label="Copier le numéro {{ $method->name }}"><x-icon name="copy" /> Copier le numéro</button>
                            </div>

                            <ol class="steps steps-short">
                                <li>Ouvrez {{ $method->name }} sur votre téléphone (l'application ou le menu).</li>
                                <li>Choisissez l'envoi d'argent, puis entrez le numéro et le montant ci-dessus.</li>
                                <li>Vérifiez le nom affiché, puis validez avec votre code secret.</li>
                                <li>Faites une capture d'écran du message de confirmation.</li>
                            </ol>
                        </div>
                    @endforeach
                @endforeach

                @error('operator')
                    <div class="error field">{{ $message }}</div>
                @enderror

                <div class="field pay-proof">
                    {{-- Champ de fichier habillé en bouton français ; il ouvre directement la galerie du téléphone. --}}
                    <input id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp" required data-proof-input
                           class="file-pick-input"
                           data-max-bytes="{{ $maxMegabytes * 1024 * 1024 }}"
                           @error('proof') aria-invalid="true" aria-describedby="proof-error" @enderror>
                    <label for="proof" class="file-pick">
                        <span class="btn btn-accent btn-lg btn-block btn-plan pick-first"><x-icon name="upload" /> J'ai payé, j'envoie ma capture</span>
                        <span class="btn btn-outline btn-block pick-again"><x-icon name="refresh" /> Choisir une autre capture</span>
                        <span class="file-pick-name" data-proof-name>Aucune capture choisie</span>
                    </label>
                    <div class="hint">
                        Une image, {{ $maxMegabytes }} mégaoctets au maximum.
                        Le montant, la date et le numéro de transaction doivent être bien visibles.
                    </div>
                    @error('proof')
                        <div class="error" id="proof-error">{{ $message }}</div>
                    @enderror
                    <img class="proof-preview" alt="Aperçu de votre capture" hidden data-proof-preview>
                    <div class="hint" hidden data-proof-status aria-live="polite"></div>
                </div>

                <button type="submit" class="btn btn-accent btn-block btn-lg btn-plan pay-submit" data-proof-submit>Envoyer ma capture</button>
                <p class="muted small next-step">Votre capture reste privée : seule notre équipe peut la voir.</p>
            </form>

            {{-- Premier paiement en ligne : où trouver de l'aide. --}}
            <aside class="card pay-help" aria-labelledby="pay-help-title" data-pay-help @unless ($operator) hidden @endunless>
                <h2 id="pay-help-title">C'est votre premier paiement par téléphone ?</h2>
                <ul class="check-list">
                    <li><x-icon name="check-circle" /> Vous payez depuis votre propre compte Mobile Money, comme pour envoyer de l'argent à un proche.</li>
                    <li><x-icon name="check-circle" /> Avant de valider, vérifiez que le nom affiché est bien celui indiqué ci-dessus.</li>
                    <li><x-icon name="check-circle" /> Pour la capture d'écran : sur la plupart des téléphones Android, appuyez en même temps sur le bouton de marche et le bouton « volume bas » ; sur iPhone, sur le bouton latéral et le bouton « volume haut ».</li>
                </ul>
                @if ($whatsappUrl)
                    <a class="btn btn-whatsapp btn-block" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><x-icon name="whatsapp" /> Une question ? WhatsApp</a>
                @endif
            </aside>

            <script src="{{ \App\Support\Asset::url('js/copy.js') }}" defer></script>
            <script type="module" src="{{ \App\Support\Asset::url('js/proof-upload.js') }}"></script>
            <script type="module" src="{{ \App\Support\Asset::url('js/payment-flow.js') }}"></script>
        @endif
    </div>
</x-layout>
