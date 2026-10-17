<x-layout title="Essai des voix">
    @include('admin.nav')

    <h1>Essai des voix</h1>
    <p>
        Écoutez chaque voix dire la même phrase, puis choisissez celle de {{ config('avatar.model.name') }}.
        Elle sera utilisée pour toutes les conversations suivantes.
    </p>
    <p class="muted small">
        Phrase : <span lang="en">« {{ $script }} »</span><br>
        Chaque extrait est créé une seule fois puis gardé ; au plus {{ config('services.gemini.voice_trial_per_hour') }} nouveaux extraits par heure.
        L'extrait est produit par le modèle de synthèse vocale : la voix en conversation peut sonner un peu différemment.
    </p>

    <section class="card">
        <p>
            Voix actuelle : <strong>{{ $current }}</strong>
            @if ($chosen)
                (choisie ici)
            @else
                (valeur par défaut GEMINI_VOICE_NAME)
            @endif
        </p>

        <div id="voice-error" class="alert alert-error" role="alert" hidden></div>

        <ul class="voice-list">
            @foreach ($trials as $voice => $description)
                <li @class(['voice-item', 'is-current' => $voice === $current])>
                    <div>
                        <strong>{{ $voice }}</strong>
                        <span class="muted">— « {{ $description }} » (description officielle)</span>
                        @if ($voice === $default)
                            <span class="badge badge-muted">par défaut</span>
                        @endif
                        @if ($voice === $current)
                            <span class="badge">voix actuelle</span>
                        @endif
                    </div>
                    <div class="voice-actions">
                        <button type="button" class="btn btn-light" data-voice-sample="{{ route('admin.voices.sample', $voice) }}">
                            <x-icon name="play" /> Écouter
                        </button>
                        @if ($voice !== $current)
                            <form method="POST" action="{{ route('admin.voices.choose') }}">
                                @csrf
                                <input type="hidden" name="voice" value="{{ $voice }}">
                                <button type="submit" class="btn">Choisir cette voix</button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    <script type="module" src="{{ \App\Support\Asset::url('js/voice-trial.js') }}"></script>
</x-layout>
