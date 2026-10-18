<x-layout title="Diagnostic des fournisseurs">
    @include('admin.nav')

    <h1>Diagnostic des fournisseurs</h1>
    <p>
        Vérifiez que l'analyse de texte et d'images fonctionne chez chaque fournisseur (lecture des captures de paiement,
        des leçons de « Mes cours », estimation du niveau et rédaction des messages), ainsi que les <a href="#direct">séances en direct</a>.
    </p>

    @if ($result = session('diagnostic'))
        <div @class(['alert', 'alert-success' => $result['ok'], 'alert-error' => ! $result['ok']]) role="status">
            <x-icon :name="$result['ok'] ? 'check-circle' : 'alert'" />
            <div>{{ $result['text'] }}</div>
        </div>
    @endif

    <section class="card">
        <h2>Fournisseurs du modèle texte</h2>
        <p>
            Ordre utilisé :
            <strong>{{ $order === [] ? 'aucun' : implode(', puis ', array_map(fn (string $name) => \App\Support\Text\TextProviders::find($name)->label(), $order)) }}</strong>
            <span class="muted">(variable TEXT_PROVIDERS)</span>.
            Si le premier échoue (limite atteinte, limite du jour, erreur, trop lent), la même demande est refaite chez le suivant ;
            un fournisseur sans clé est ignoré.
        </p>
        @foreach ($providers as $provider)
            @php
                $position = array_search($provider->name(), $order, true);
            @endphp
            <div class="card" data-text-provider="{{ $provider->name() }}">
                <h3>{{ $provider->label() }}</h3>
                <ul>
                    <li>Place dans l'ordre : <strong>{{ $position === false ? 'non utilisé (absent de TEXT_PROVIDERS)' : ($position === 0 ? 'premier' : 'secours n° '.$position) }}</strong></li>
                    <li>Clé {{ $provider->name() === 'openai' ? 'OPENAI_API_KEY' : 'GEMINI_API_KEY' }} : <strong>{{ $provider->hasKey() ? 'présente' : 'absente' }}</strong> <span class="muted">(jamais affichée)</span></li>
                    <li>Modèle : <strong>{{ $provider->model() }}</strong></li>
                    @if (\App\Support\Text\TextProviders::isPaused($provider))
                        <li><strong>Mis de côté quelques minutes</strong> après une limite ou une clé refusée : les demandes vont d'abord au suivant.</li>
                    @endif
                </ul>
                <form method="POST" action="{{ route('admin.text-diagnostic.test', $provider->name()) }}">
                    @csrf
                    <button type="submit" class="btn btn-accent"><x-icon name="image" /> Tester {{ $provider->label() }}</button>
                </form>
            </div>
        @endforeach
        <p class="muted small">Chaque essai envoie une petite image orange et demande sa couleur dans le format de réponse imposé. Il compte dans la limite de demandes (et le crédit) du fournisseur.</p>
    </section>

    <section class="card" id="direct" aria-labelledby="direct-title">
        <h2 id="direct-title">Séances en direct</h2>
        <p>
            Ordre utilisé :
            <strong>{{ $liveOrder === [] ? 'aucun' : implode(', puis ', array_map(fn (string $name) => \App\Support\Live\LiveProviders::label($name), $liveOrder)) }}</strong>
            <span class="muted">(variable LIVE_PROVIDERS)</span>.
            Le fournisseur est choisi au début de chaque séance (évaluation, séance du jour, « Mes cours », bilan de niveau) et ne change jamais pendant la séance.
        </p>
        @foreach (\App\Support\Live\LiveProviders::all() as $name)
            @php
                $position = array_search($name, $liveOrder, true);
            @endphp
            <div class="card" data-live-provider="{{ $name }}">
                <h3>{{ \App\Support\Live\LiveProviders::label($name) }}</h3>
                <ul>
                    <li>Place dans l'ordre : <strong>{{ $position === false ? 'non utilisé (absent de LIVE_PROVIDERS)' : ($position === 0 ? 'premier' : 'secours n° '.$position) }}</strong></li>
                    <li>Clé {{ $name === 'openai' ? 'OPENAI_API_KEY' : 'GEMINI_API_KEY' }} : <strong>{{ \App\Support\Live\LiveProviders::hasKey($name) ? 'présente' : 'absente' }}</strong> <span class="muted">(jamais affichée)</span></li>
                    <li>Modèle : <strong>{{ \App\Support\Live\LiveProviders::model($name) }}</strong></li>
                    <li>Voix : <strong>{{ \App\Support\Live\LiveProviders::voice($name) }}</strong></li>
                    @if (\App\Support\Live\LiveProviders::isPaused($name))
                        <li><strong>Mis de côté quelques minutes</strong> après une limite ou une clé refusée : les séances commencent chez le suivant.</li>
                    @endif
                </ul>
                <div class="actions">
                    <form method="POST" action="{{ route('admin.live-trial.test', $name) }}">
                        @csrf
                        <button type="submit" class="btn btn-accent"><x-icon name="mic" /> Tester {{ \App\Support\Live\LiveProviders::label($name) }}</button>
                    </form>
                    <a class="btn btn-light" href="{{ route('admin.live-trial.show', $name) }}"><x-icon name="headphones" /> Essayer une conversation de 30 secondes</a>
                </div>
            </div>
        @endforeach
        <p class="muted small">« Tester » crée seulement un jeton de quelques secondes (jamais gardé ni affiché). L'essai de conversation se fait chez ce fournisseur seul, sans secours, et compte dans sa consommation.</p>

        <h3>Dernières erreurs des séances en direct</h3>
        @if ($liveFailures === [])
            <p class="muted">Aucune erreur enregistrée.</p>
        @else
            <table class="table">
                <thead>
                    <tr><th>Date</th><th>Fournisseur</th><th>Étape</th><th>Cause</th><th>Code HTTP</th><th>Détail</th></tr>
                </thead>
                <tbody>
                    @foreach ($liveFailures as $failure)
                        <tr>
                            <td data-label="Date">{{ \Illuminate\Support\Carbon::parse($failure['at'])->timezone(config('app.timezone'))->translatedFormat('j F Y à H:i') }}</td>
                            <td data-label="Fournisseur">{{ $failure['provider_label'] }}</td>
                            <td data-label="Étape">{{ $failure['step'] }}</td>
                            <td data-label="Cause">{{ $failure['reason'] }}</td>
                            <td data-label="Code HTTP">{{ $failure['status'] ?? 'aucun' }}</td>
                            <td data-label="Détail" class="small">{{ $failure['message'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="card">
        <h2>Dernières erreurs de l'analyse de texte et d'images</h2>
        @if ($failures === [])
            <p class="muted">Aucune erreur enregistrée.</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Fournisseur</th>
                        <th>Usage</th>
                        <th>Cause</th>
                        <th>Code HTTP</th>
                        <th>Durée</th>
                        <th>Détail</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($failures as $failure)
                        <tr>
                            <td data-label="Date">{{ \Illuminate\Support\Carbon::parse($failure['at'])->timezone(config('app.timezone'))->translatedFormat('j F Y à H:i') }}</td>
                            <td data-label="Fournisseur">{{ $failure['provider_label'] }}</td>
                            <td data-label="Usage">{{ $failure['label'] }}</td>
                            <td data-label="Cause">{{ $failure['reason'] }}</td>
                            <td data-label="Code HTTP">{{ $failure['status'] ?? 'aucun' }}</td>
                            <td data-label="Durée">{{ $failure['duration_ms'] !== null ? number_format($failure['duration_ms'] / 1000, 1, ',', ' ').($failure['duration_ms'] >= 2000 ? ' secondes' : ' seconde') : 'aucune' }}</td>
                            <td data-label="Détail" class="small">{{ $failure['message'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</x-layout>
