<x-layout title="Essai des voix">
    @include('admin.nav')

    <h1>Essai des voix</h1>

    @if (count($teachers) > 1)
        <nav class="tabs voice-teachers" aria-label="Professeur">
            @foreach ($teachers as $item)
                <a href="{{ route('admin.voices.index', $item->key === \App\Support\Teachers\Teachers::default()->key ? [] : ['professeur' => $item->key]) }}" @class(['active' => $item->key === $teacher->key]) @if ($item->key === $teacher->key) aria-current="page" @endif>{{ $item->name() }}</a>
            @endforeach
        </nav>
    @endif

    <p>
        Écoutez chaque voix dire la même phrase, puis choisissez celle de {{ $teacher->name() }}.
        Elle sera utilisée pour toutes les conversations suivantes.
    </p>
    @unless ($teacher->isReady())
        <p class="alert">{{ $teacher->name() }} n'est pas encore proposée aux apprenants : son choix apparaîtra dans « Mon espace » quand son visage sera prêt. Vous pouvez déjà choisir sa voix et l'essayer dans « Diagnostic des fournisseurs » (conversation de 30 secondes).</p>
    @endunless
    <p class="muted small">
        Phrase : <span lang="en">« {{ $script }} »</span><br>
        Chaque extrait est créé une seule fois puis gardé ; au plus {{ config('services.gemini.voice_trial_per_hour') }} nouveaux extraits par heure.
        « Écouter » : extrait du modèle de synthèse vocale (la voix des vidéos et des messages).
        « Écouter en séance » : la même phrase dite par une vraie séance avec cette voix (sans micro), telle que les apprenants l'entendent ;
        raccrochée par le serveur après {{ \App\Support\Live\LiveVoiceSample::SECONDS }} secondes, aucune minute comptée, extrait gardé pour les écoutes suivantes.
    </p>

    <div id="voice-error" class="alert alert-error" role="alert" hidden></div>

    @if ($teacher->speechTrials() !== [])
        @php
            $speechChosen = $teacher->speechVoice();
            $speechLabels = ['gemini' => 'Gemini', 'openai' => 'OpenAI'];
            // Clarté mesurée de chaque extrait (retranscription comparée au texte) et la meilleure en français.
            $clarity = [];
            foreach ($teacher->speechTrials() as $provider => $voices) {
                foreach (array_keys($voices) as $voice) {
                    $clarity[$provider][$voice] = \App\Support\Speech\VoiceClarity::result($provider, $voice, $teacher);
                }
            }
            $ranked = collect($clarity)->flatMap(fn ($voices, $provider) => collect($voices)->filter()->map(fn ($r, $voice) => $r + ['provider' => $provider, 'voice' => $voice])->values())
                ->sortByDesc(fn ($r) => [$r['french'], $r['english']])->values();
        @endphp
        <section class="card" id="voix-francais" aria-labelledby="voix-francais-titre">
            <h2 id="voix-francais-titre">Voix des vidéos et des messages : test en français et en anglais</h2>
            <p>
                Écoutez chaque voix dire la même phrase en français puis en anglais, avec des chiffres et des mots difficiles,
                puis choisissez celle des vidéos et des messages vocaux de {{ $teacher->name() }}. Sa voix de séance (plus bas) ne change pas.
            </p>
            <p class="muted small">Phrase : « {{ \App\Support\Gemini\VoiceSampler::bilingualScript($teacher)['text'] }} »</p>
            <p>
                Voix actuelle des vidéos et des messages :
                @if ($speechChosen)
                    <strong>{{ $speechChosen['voice'] }}</strong> ({{ $speechLabels[$speechChosen['provider']] ?? $speechChosen['provider'] }}, choisie ici)
                @else
                    <strong>{{ $teacher->geminiVoice() }}</strong> (Gemini, sa voix de séance : rien de choisi ici)
                @endif
            </p>
            <form method="POST" action="{{ route('admin.voices.speech.prepare') }}">
                @csrf
                <input type="hidden" name="teacher" value="{{ $teacher->key }}">
                <button type="submit" class="btn btn-light" data-busy-label="Préparation en cours…"><x-icon name="download" /> Préparer les extraits manquants</button>
                <span class="muted small">Gemini : la deuxième clé d'abord, pour garder la clé principale aux vidéos du jour.</span>
            </form>
            <form method="POST" action="{{ route('admin.voices.speech.measure') }}">
                @csrf
                <input type="hidden" name="teacher" value="{{ $teacher->key }}">
                <button type="submit" class="btn btn-light" data-busy-label="Mesure en cours…"><x-icon name="check-circle" /> Mesurer la clarté des extraits prêts</button>
                <span class="muted small">Chaque extrait est retranscrit, puis comparé mot à mot à la phrase : un mot déformé est mal reconnu. Utile pour écarter une voix ; l'écoute décide.</span>
            </form>
            @if ($ranked->isNotEmpty())
                <p class="alert alert-info">
                    <x-icon name="info" />
                    <span>Voix la plus claire mesurée en français : <strong>{{ $ranked[0]['voice'] }}</strong> ({{ $speechLabels[$ranked[0]['provider']] ?? $ranked[0]['provider'] }}) : {{ $ranked[0]['french'] }} % des mots français et {{ $ranked[0]['english'] }} % des mots anglais bien reconnus.</span>
                </p>
            @endif

            @foreach ($teacher->speechTrials() as $provider => $voices)
                <h3>{{ $speechLabels[$provider] ?? $provider }}</h3>
                <ul class="voice-list">
                    @foreach ($voices as $voice => $description)
                        @php $isCurrent = $speechChosen && $speechChosen['provider'] === $provider && $speechChosen['voice'] === $voice; @endphp
                        <li @class(['voice-item', 'is-current' => $isCurrent])>
                            <div>
                                <strong>{{ $voice }}</strong>
                                <span class="muted">— « {{ $description }} » {{ $provider === 'gemini' ? '(description officielle)' : '(notre description)' }}</span>
                                @if (app(\App\Support\Gemini\VoiceSampler::class)->cachedBilingual($provider, $voice, $teacher) !== null)
                                    <span class="badge badge-muted">extrait prêt</span>
                                @endif
                                @if ($isCurrent)
                                    <span class="badge">voix actuelle</span>
                                @endif
                                @if ($result = $clarity[$provider][$voice] ?? null)
                                    <br><span class="small">Clarté : français {{ $result['french'] }} %, anglais {{ $result['english'] }} %</span>
                                    <details class="small"><summary>Ce qui a été entendu</summary><p>{{ $result['transcript'] }}</p></details>
                                @endif
                            </div>
                            <div class="voice-actions">
                                <button type="button" class="btn btn-light" data-voice-sample="{{ route('admin.voices.speech.sample', ['provider' => $provider, 'voice' => $voice, 'professeur' => $teacher->key]) }}">
                                    <x-icon name="play" /> Écouter
                                </button>
                                @unless ($isCurrent)
                                    <form method="POST" action="{{ route('admin.voices.speech.choose') }}">
                                        @csrf
                                        <input type="hidden" name="teacher" value="{{ $teacher->key }}">
                                        <input type="hidden" name="provider" value="{{ $provider }}">
                                        <input type="hidden" name="voice" value="{{ $voice }}">
                                        <button type="submit" class="btn">Choisir pour les vidéos et les messages</button>
                                    </form>
                                @endunless
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </section>
    @endif

    @php
        $sections = [['provider' => 'gemini', 'title' => count($openaiTrials) ? 'Voix Gemini (séances en direct)' : null, 'trials' => $trials, 'current' => $current, 'chosen' => $chosen, 'default' => $default, 'official' => true]];

        if (count($openaiTrials)) {
            $sections[] = ['provider' => 'openai', 'title' => 'Voix OpenAI (secours des séances en direct)', 'trials' => $openaiTrials, 'current' => $openaiCurrent, 'chosen' => $openaiChosen, 'default' => $openaiDefault, 'official' => false];
        }

        $isDefaultTeacher = $teacher->key === \App\Support\Teachers\Teachers::default()->key;
    @endphp

    @foreach ($sections as $section)
        <section class="card" @if ($section['title']) aria-labelledby="voix-{{ $section['provider'] }}" @endif>
            @if ($section['title'])
                <h2 id="voix-{{ $section['provider'] }}">{{ $section['title'] }}</h2>
            @endif
            <p>
                Voix actuelle : <strong>{{ $section['current'] }}</strong>
                @if ($section['chosen'])
                    (choisie ici)
                @elseif ($isDefaultTeacher && $section['provider'] === 'gemini')
                    (valeur par défaut GEMINI_VOICE_NAME)
                @else
                    (valeur par défaut)
                @endif
            </p>

            <ul class="voice-list">
                @foreach ($section['trials'] as $voice => $description)
                    @php
                        $query = $isDefaultTeacher && $section['provider'] === 'gemini' ? [] : ['professeur' => $teacher->key, 'fournisseur' => $section['provider']];
                    @endphp
                    <li @class(['voice-item', 'is-current' => $voice === $section['current']])>
                        <div>
                            <strong>{{ $voice }}</strong>
                            <span class="muted">— « {{ $description }} » {{ $section['official'] ? '(description officielle)' : '(notre description)' }}</span>
                            @if ($voice === $section['default'])
                                <span class="badge badge-muted">par défaut</span>
                            @endif
                            @if ($voice === $section['current'])
                                <span class="badge">voix actuelle</span>
                            @endif
                        </div>
                        <div class="voice-actions">
                            <button type="button" class="btn btn-light" data-voice-sample="{{ route('admin.voices.sample', ['voice' => $voice] + $query) }}">
                                <x-icon name="play" /> Écouter
                            </button>
                            <button type="button" class="btn btn-light" data-voice-live="{{ route('admin.voices.live', ['voice' => $voice, 'professeur' => $teacher->key, 'fournisseur' => $section['provider']]) }}" data-voice-live-finish="{{ route('admin.voices.live.finish') }}">
                                <x-icon name="headphones" /> Écouter en séance
                            </button>
                            @if ($voice !== $section['current'])
                                <form method="POST" action="{{ route('admin.voices.choose') }}">
                                    @csrf
                                    <input type="hidden" name="voice" value="{{ $voice }}">
                                    @unless ($isDefaultTeacher && $section['provider'] === 'gemini')
                                        <input type="hidden" name="teacher" value="{{ $teacher->key }}">
                                        <input type="hidden" name="provider" value="{{ $section['provider'] }}">
                                    @endunless
                                    <button type="submit" class="btn">Choisir cette voix</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    <script type="module" src="{{ \App\Support\Asset::url('js/voice-trial.js') }}"></script>
    <script type="module" src="{{ \App\Support\Asset::url('js/voice-live-sample.js') }}"></script>
</x-layout>
