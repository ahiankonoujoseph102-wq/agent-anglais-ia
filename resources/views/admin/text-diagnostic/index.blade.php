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

    {{-- « Mes cours » : le site accepte-t-il un PDF de 20 mégaoctets et une lecture d'une minute ? Valeurs de PHP pour
         le site, puis deux essais réels (public/js/upload-check.js, Admin\UploadCheckController). --}}
    <section class="card" id="envoi" aria-labelledby="envoi-title" data-upload-check
             data-size-url="{{ route('admin.upload-check.size') }}" data-wait-url="{{ route('admin.upload-check.wait') }}"
             data-bytes="{{ (int) config('platform.courses.pdf_max_kilobytes') * 1024 }}">
        <h2 id="envoi-title">Limites d'envoi (« Mes cours »)</h2>
        <p class="muted">Un PDF peut peser {{ \App\Support\UploadLimits::label((int) config('platform.courses.pdf_max_kilobytes') * 1024) }} et sa lecture durer jusqu'à {{ \App\Support\UploadLimits::readingSeconds() }} secondes. Si une limite bloque, l'élève voit un message clair (« Votre fichier est trop lourd pour être envoyé… »), jamais une page d'erreur.</p>
        <ul class="check-list">
            <li><x-icon :name="$uploadLimits['file']['ok'] ? 'check' : 'alert'" /> Taille d'un fichier : {{ \App\Support\UploadLimits::label($uploadLimits['file']['value']) }} ({{ $uploadLimits['file']['ok'] ? 'suffisant' : 'il faut au moins '.\App\Support\UploadLimits::label($uploadLimits['file']['needed']) }})</li>
            <li><x-icon :name="$uploadLimits['post']['ok'] ? 'check' : 'alert'" /> Taille d'un envoi : {{ \App\Support\UploadLimits::label($uploadLimits['post']['value']) }} ({{ $uploadLimits['post']['ok'] ? 'suffisant' : 'il faut au moins '.\App\Support\UploadLimits::label($uploadLimits['post']['needed']) }})</li>
            <li><x-icon :name="$uploadLimits['time']['ok'] ? 'check' : 'alert'" /> Durée d'une page : {{ $uploadLimits['time']['value'] === 0 ? 'aucune limite' : $uploadLimits['time']['value'].' secondes' }} ({{ $uploadLimits['time']['ok'] ? 'suffisant' : 'il faut au moins '.$uploadLimits['time']['needed'].' secondes' }})</li>
        </ul>
        <div class="actions actions-row">
            <button type="button" class="btn" data-upload-check-size><x-icon name="file-text" /> Essayer un envoi de {{ \App\Support\UploadLimits::label((int) config('platform.courses.pdf_max_kilobytes') * 1024) }}</button>
            <button type="button" class="btn btn-light" data-upload-check-wait><x-icon name="clock" /> Essayer une attente de {{ (int) config('platform.courses.check_wait_seconds') }} secondes</button>
        </div>
        <p class="hint" data-upload-check-result role="status" hidden></p>
        <p class="muted small">Les essais passent par le vrai chemin du site (proxy compris). Rien n'est gardé, aucun fournisseur n'est appelé.</p>
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
    <script type="module" src="{{ \App\Support\Asset::url('js/upload-check.js') }}"></script>
</x-layout>
