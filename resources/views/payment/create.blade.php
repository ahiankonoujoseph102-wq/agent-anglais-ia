<x-layout :title="'Payer la formule '.$plan->name">
    @include('payment.steps', ['current' => $country ? 3 : 2])

    <h1>Formule {{ $plan->name }} : {{ $plan->formattedPrice() }}</h1>
    <p class="muted">
        {{ $plan->dailyMinutes }} minutes par jour pendant {{ $plan->durationDays }} jours.
        <a href="{{ route('payment.plans') }}">Changer de formule</a>
    </p>

    <section class="card">
        <h2>Dans quel pays payez-vous ?</h2>
        <div class="choices">
            @foreach ($countries as $option)
                <a href="{{ route('payment.create', ['plan' => $plan->key, 'pays' => $option->code]) }}"
                   @class(['btn', 'btn-light' => $option->code !== $country?->code])
                   @if ($option->code === $country?->code) aria-current="true" @endif>{{ $option->name }}</a>
            @endforeach
        </div>
    </section>

    @if ($country)
        <section class="card">
            <h2>Envoyez exactement {{ $plan->formattedPrice() }}</h2>
            <p>Au nom de <strong>{{ $country->holder }}</strong>, sur l'un de ces numéros :</p>

            <ul class="operators">
                @foreach ($country->operators as $operator)
                    <li>
                        <div>
                            <strong>{{ $operator->name }}</strong><br>
                            <span class="number">{{ $operator->number }}</span>
                        </div>
                        <button type="button" class="btn btn-light" data-copy="{{ $operator->localNumber() }}"
                                aria-label="Copier le numéro {{ $operator->name }}">Copier le numéro</button>
                    </li>
                @endforeach
            </ul>
            <p class="muted small">Gardez le message de confirmation de votre opérateur : il contient la référence de la transaction.</p>
        </section>

        <form method="POST" action="{{ route('payment.store', $plan->key) }}" enctype="multipart/form-data" class="card" novalidate>
            @csrf
            <input type="hidden" name="country" value="{{ $country->code }}">

            <h2>Après avoir payé, remplissez ce formulaire</h2>

            <fieldset class="field">
                <legend>Opérateur utilisé</legend>
                @foreach ($country->operators as $operator)
                    <label class="checkbox">
                        <input type="radio" name="operator" value="{{ $operator->key }}" @checked(old('operator') === $operator->key) required>
                        {{ $operator->name }}
                    </label>
                @endforeach
                @error('operator')
                    <div class="error">{{ $message }}</div>
                @enderror
            </fieldset>

            <x-input name="payer_phone" label="Numéro qui a payé" type="tel" inputmode="tel" autocomplete="tel"
                     :value="auth()->user()->phone" required />
            <x-input name="reference" label="Référence de la transaction" autocomplete="off"
                     hint="Elle figure dans le SMS de confirmation de votre opérateur." required />
            @error('reference_key')
                <div class="error field">{{ $message }}</div>
            @enderror

            <div class="field">
                <label for="proof">Capture d'écran du paiement</label>
                <input id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp" required
                       @error('proof') aria-invalid="true" aria-describedby="proof-error" @enderror>
                <div class="hint">Image JPG, PNG ou WebP, {{ $maxMegabytes }} Mo au maximum.</div>
                @error('proof')
                    <div class="error" id="proof-error">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-block">Envoyer mon paiement</button>
        </form>

        <script src="{{ asset('js/copy.js') }}?v={{ @filemtime(public_path('js/copy.js')) }}" defer></script>
    @endif
</x-layout>
