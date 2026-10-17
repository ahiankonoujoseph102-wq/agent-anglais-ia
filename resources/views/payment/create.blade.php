<x-layout :title="'Payer la formule '.$plan->name">
    @include('payment.steps', ['current' => $operator ? 3 : 2])

    <div class="step-panel">
        <div class="page-head">
            <h1>Formule {{ $plan->name }} : {{ $plan->formattedPrice() }}</h1>
            <p class="muted">
                {{ $plan->dailyMinutes }} minutes par jour pendant {{ $plan->durationDays }} jours.
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
            <section class="card">
                <div class="card-title">
                    <span class="icon-bubble"><x-icon name="globe" /></span>
                    <h2>Dans quel pays payez-vous ?</h2>
                </div>
                <div class="choices">
                    @foreach ($countries as $option)
                        <a href="{{ route('payment.create', ['plan' => $plan->key, 'pays' => $option->code]) }}"
                           @class(['btn', 'btn-light' => $option->code !== $country?->code])
                           @if ($option->code === $country?->code) aria-current="true" @endif>{{ $option->name }}</a>
                    @endforeach
                </div>
            </section>

            @if ($country)
                <section class="card rise">
                    <div class="card-title">
                        <span class="icon-bubble"><x-icon name="mobile" /></span>
                        <h2>Avec quel opérateur payez-vous ?</h2>
                    </div>
                    <div class="choices">
                        @foreach ($country->operators as $option)
                            <a href="{{ route('payment.create', ['plan' => $plan->key, 'pays' => $country->code, 'operateur' => $option->key]) }}"
                               @class(['btn', 'btn-light' => $option->key !== $operator?->key])
                               @if ($option->key === $operator?->key) aria-current="true" @endif>{{ $option->name }}</a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($operator)
                <section class="card card-accent rise">
                    <div class="card-title">
                        <span class="icon-bubble icon-bubble-accent"><x-icon name="money" /></span>
                        <h2>Payez avec {{ $operator->name }}</h2>
                    </div>
                    <ol class="steps">
                        <li>Ouvrez le menu {{ $operator->name }} sur votre téléphone.</li>
                        <li>
                            Envoyez exactement <span class="amount">{{ $plan->formattedPrice() }}</span> à ce numéro :
                            <div class="pay-number">
                                <div>
                                    <span class="number">{{ $operator->displayNumber() }}</span>
                                    @if ($operator->holder)
                                        <br><span class="muted">Au nom de <strong>{{ $operator->holder }}</strong></span>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-light" data-copy="{{ $operator->localNumber() }}"
                                        aria-label="Copier le numéro {{ $operator->name }}"><x-icon name="copy" /> Copier le numéro</button>
                            </div>
                        </li>
                        <li>Attendez le message de confirmation de {{ $operator->name }}.</li>
                        <li>Faites une capture d'écran de ce message, puis ajoutez-la ci-dessous.</li>
                    </ol>
                </section>

                <form method="POST" action="{{ route('payment.store', $plan->key) }}" enctype="multipart/form-data" class="card form-card rise" style="--i: 1" novalidate data-proof-form>
                    @csrf
                    <input type="hidden" name="country" value="{{ $country->code }}">
                    <input type="hidden" name="operator" value="{{ $operator->key }}">

                    <div class="card-title">
                        <span class="icon-bubble"><x-icon name="upload" /></span>
                        <h2>Envoyez la capture de votre paiement</h2>
                    </div>

                    @error('operator')
                        <div class="error field">{{ $message }}</div>
                    @enderror

                    <div class="field">
                        <label for="proof">Capture d'écran de la confirmation</label>
                        <input id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp" required data-proof-input
                               data-max-bytes="{{ $maxMegabytes * 1024 * 1024 }}"
                               @error('proof') aria-invalid="true" aria-describedby="proof-error" @enderror>
                        <div class="hint">
                            Une image (JPEG, PNG ou WebP), {{ $maxMegabytes }} mégaoctets au maximum.
                            Le montant, la date et le numéro de transaction doivent être bien visibles.
                        </div>
                        @error('proof')
                            <div class="error" id="proof-error">{{ $message }}</div>
                        @enderror
                        <img class="proof-preview" alt="Aperçu de votre capture" hidden data-proof-preview>
                        <div class="hint" hidden data-proof-status aria-live="polite"></div>
                    </div>

                    <button type="submit" class="btn btn-accent btn-block btn-lg" data-proof-submit>Envoyer ma capture</button>
                    <p class="muted small next-step">Votre capture reste privée : seule notre équipe peut la voir.</p>
                </form>

                <script src="{{ \App\Support\Asset::url('js/copy.js') }}" defer></script>
                <script type="module" src="{{ \App\Support\Asset::url('js/proof-upload.js') }}"></script>
            @endif
        @endif
    </div>
</x-layout>
