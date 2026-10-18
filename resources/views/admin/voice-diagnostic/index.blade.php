<x-layout title="Diagnostic de la voix">
    @include('admin.nav')

    <h1>Diagnostic de la voix</h1>
    <p>
        Vérifiez que la synthèse vocale de Google fonctionne (messages de bienvenue et du résultat de l'évaluation),
        et préparez à l'avance les parties communes : elles sont alors prêtes avant la première écoute.
    </p>

    @if ($result = session('diagnostic'))
        <div @class(['alert', 'alert-success' => $result['ok'], 'alert-error' => ! $result['ok']]) role="status">
            <x-icon :name="$result['ok'] ? 'check-circle' : 'alert'" />
            <div>{{ $result['text'] }}</div>
        </div>
    @endif

    <section class="card">
        <h2>Réglages utilisés</h2>
        <ul>
            <li>Modèle de synthèse vocale : <strong>{{ $model }}</strong> <span class="muted">(variable GEMINI_TTS_MODEL)</span></li>
            <li>Voix : <strong>{{ $voice }}</strong></li>
            <li>Clé Gemini : <strong>{{ $hasKey ? 'présente' : 'absente' }}</strong> <span class="muted">(jamais affichée)</span></li>
            <li>Stockage privé : <strong>{{ $disk }}</strong></li>
        </ul>
        <form method="POST" action="{{ route('admin.voice-diagnostic.test') }}">
            @csrf
            <button type="submit" class="btn btn-accent"><x-icon name="play" /> Tester la synthèse vocale</button>
        </form>
        <p class="muted small">Génère une phrase courte, puis écrit et efface un petit fichier dans le stockage privé. Chaque essai compte dans la limite de demandes de Google.</p>
    </section>

    <section class="card">
        <h2>Parties communes</h2>
        <ul>
            <li>Message de bienvenue : <strong>{{ $welcome['ready'] }} paragraphes prêts sur {{ $welcome['total'] }}</strong></li>
            <li>Messages du résultat de l'évaluation : <strong>{{ $levels['ready'] }} prêts sur {{ $levels['total'] }}</strong></li>
        </ul>
        <form method="POST" action="{{ route('admin.voice-diagnostic.prepare') }}">
            @csrf
            <button type="submit" class="btn"><x-icon name="refresh" /> Préparer le message de bienvenue</button>
        </form>
        <p class="muted small">
            Génère les morceaux manquants un par un, en espaçant les demandes, pendant {{ \App\Support\Duration::formatPrecise((int) config('services.gemini.tts_web_budget')) }} au plus.
            S'il en reste, touchez le bouton de nouveau. Ils sont aussi préparés à chaque déploiement et chaque heure.
            Pendant l'écoute, seule la salutation avec le prénom est générée : ce qui n'est pas prêt est affiché en texte.
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
