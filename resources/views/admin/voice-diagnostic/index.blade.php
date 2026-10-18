<x-layout title="Diagnostic de la voix">
    @include('admin.nav')

    <h1>Diagnostic de la voix</h1>
    <p>
        Vérifiez que la synthèse vocale fonctionne chez chaque fournisseur (messages de bienvenue et du résultat de
        l'évaluation), et préparez à l'avance les parties communes : elles sont alors prêtes avant la première écoute.
    </p>

    @if ($result = session('diagnostic'))
        <div @class(['alert', 'alert-success' => $result['ok'], 'alert-error' => ! $result['ok']]) role="status">
            <x-icon :name="$result['ok'] ? 'check-circle' : 'alert'" />
            <div>{{ $result['text'] }}</div>
        </div>
    @endif

    <section class="card">
        <h2>Fournisseurs de synthèse vocale</h2>
        <p>
            Ordre utilisé :
            <strong>{{ $order === [] ? 'aucun' : implode(', puis ', array_map(fn (string $name) => \App\Support\Speech\SpeechProviders::find($name)->label(), $order)) }}</strong>
            <span class="muted">(variable TTS_PROVIDERS)</span>.
            Le suivant sert de secours ; un fournisseur sans clé est ignoré. Stockage privé : <strong>{{ $disk }}</strong>.
        </p>
        @foreach ($providers as $provider)
            @php
                $position = array_search($provider->name(), $order, true);
            @endphp
            <div class="card">
                <h3>{{ $provider->label() }}</h3>
                <ul>
                    <li>Place dans l'ordre : <strong>{{ $position === false ? 'non utilisé (absent de TTS_PROVIDERS)' : ($position === 0 ? 'premier' : 'secours n° '.$position) }}</strong></li>
                    <li>Clé {{ $provider->keyVariable() }} : <strong>{{ $provider->hasKey() ? 'présente' : 'absente' }}</strong> <span class="muted">(jamais affichée)</span></li>
                    <li>Modèle : <strong>{{ $provider->model() }}</strong></li>
                    <li>Voix : <strong>{{ $provider->voice() }}</strong></li>
                </ul>
                <form method="POST" action="{{ route('admin.voice-diagnostic.test', $provider->name()) }}">
                    @csrf
                    <button type="submit" class="btn btn-accent"><x-icon name="play" /> Tester {{ $provider->label() }}</button>
                </form>
            </div>
        @endforeach
        <p class="muted small">Chaque essai génère une phrase courte, puis écrit et efface un petit fichier dans le stockage privé. Il compte dans la limite de demandes (et le crédit) du fournisseur.</p>
    </section>

    <section class="card">
        <h2>Parties communes</h2>
        <ul>
            <li>Messages de bienvenue : <strong>{{ $status['welcome']['ready'] }} prêts sur {{ $status['welcome']['total'] }}</strong></li>
            <li>Messages du résultat de l'évaluation : <strong>{{ $status['levels']['ready'] }} prêts sur {{ $status['levels']['total'] }}</strong></li>
        </ul>
        <p class="muted small">Une seule voix par message : chaque message est joué en entier par un seul fournisseur.</p>
        <table class="table">
            <thead>
                <tr>
                    <th>Message</th>
                    @foreach ($providers as $provider)
                        <th>{{ $provider->label() }}</th>
                    @endforeach
                    <th>Fournisseur utilisé</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($status['messages'] as $message)
                    <tr>
                        <td data-label="Message">{{ $message['label'] }}</td>
                        @foreach ($providers as $provider)
                            <td data-label="{{ $provider->label() }}">{{ $message['ready'][$provider->name()] }} sur {{ $message['total'] }}</td>
                        @endforeach
                        <td data-label="Fournisseur utilisé">
                            @if ($message['provider'] === null)
                                aucun (texte seulement)
                            @else
                                <strong>{{ \App\Support\Speech\SpeechProviders::find($message['provider'])->label() }}</strong>@unless ($message['complete']) <span class="muted">(incomplet : ce qui manque reste en texte)</span>@endunless
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <form method="POST" action="{{ route('admin.voice-diagnostic.prepare') }}">
            @csrf
            <button type="submit" class="btn"><x-icon name="refresh" /> Préparer le message de bienvenue</button>
        </form>
        <p class="muted small">
            Complète les messages un par un, en espaçant les demandes, pendant {{ \App\Support\Duration::formatPrecise((int) config('services.gemini.tts_web_budget')) }} au plus :
            d'abord chez le premier fournisseur ; s'il ne peut pas finir un message (limite du jour, crédit épuisé, erreur), tout ce message est préparé chez le suivant.
            S'il en reste, touchez le bouton de nouveau. Ils sont aussi préparés à chaque déploiement et chaque heure.
            Pendant l'écoute, seule la salutation avec le prénom est générée, par le fournisseur du message : ce qui n'est pas prêt est affiché en texte.
        </p>
    </section>

    <section class="card">
        <h2>Dernières erreurs</h2>
        @if ($failures === [])
            <p class="muted">Aucune erreur enregistrée.</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Fournisseur</th>
                        <th>Morceau</th>
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
                            <td data-label="Fournisseur">{{ $failure['provider_label'] ?? 'Gemini (Google)' }}</td>
                            <td data-label="Morceau">{{ $failure['label'] }}</td>
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
