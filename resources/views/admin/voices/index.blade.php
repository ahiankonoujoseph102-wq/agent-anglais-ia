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
        L'extrait est produit par le modèle de synthèse vocale : la voix en conversation peut sonner un peu différemment.
    </p>

    <div id="voice-error" class="alert alert-error" role="alert" hidden></div>

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
</x-layout>
