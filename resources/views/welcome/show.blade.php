{{--
    Bienvenue du professeur : message vocal à la première ouverture d'un accès (formule active). Plusieurs professeurs
    proposés : « Avec qui voulez-vous travailler ? » en haut (extrait de la voix de chacun, puis « Choisir »).
    Le message est dit par le professeur choisi.
    Même technique que l'écran de conversation : professeur 3D (repli : visage simple) dont la bouche suit la
    voix (public/js/welcome.js), sous-titres du texte complet, boutons « Pause » et « Passer », puis le bouton
    de la prochaine étape. Sans JavaScript : le texte complet et le bouton sont affichés tout de suite.
--}}
@php
    use App\Support\Asset;

    $teacherName = $teacher->name();
    $audio = ['voice_gain' => (float) config('platform.speech.page_gain', 1.0)];
@endphp
<x-layout title="Bienvenue" focus>
    <div id="welcome" @class(['welcome-page', 'has-choice' => $choices !== []])
         data-teacher="{{ $teacher3d ? 'loading' : 'svg' }}"
         data-teacher-name="{{ $teacherName }}"
         data-pieces='@json($pieces)'
         data-audio='@json($audio)'
         data-teacher-cues="{{ Asset::url('js/teacher-cues.js') }}"
         @if ($teacher3d)
             data-teacher-view="{{ \App\Support\Avatar::asset('js/teacher-view.js') }}"
             data-teacher3d='@json($teacher3d)'
         @endif>

        @if ($firstVisit)
            {{-- Petite fête de deux secondes (CSS seulement, transform et opacity ; rien avec « réduire les animations »). --}}
            <div class="welcome-confetti" aria-hidden="true">
                @for ($i = 0; $i < 18; $i++)
                    <span style="--x: {{ ($i * 37) % 100 }}%; --d: {{ 0.05 * ($i % 6) }}s; --r: {{ ($i * 53) % 360 }}deg; --s: {{ ($i % 2 ? 1 : -1) * (12 + ($i * 7) % 36) }}px; --c: {{ ['var(--c-accent)', 'var(--c-on-dark)', 'var(--c-accent-soft)', 'var(--c-primary-soft)'][$i % 4] }}"></span>
                @endfor
            </div>
        @endif

        <div class="welcome-head">
            <h1>Félicitations, {{ $firstName }} ! <span aria-hidden="true">🎉</span></h1>
            <p class="welcome-lead">Votre formule {{ $planName }} est activée</p>
        </div>

        @if ($choices !== [])
            {{-- Choix du professeur : une carte par professeur (visage, nom, sa voix). Le nom ne disparaît jamais :
                 au toucher, la carte se met en avant et « Un instant… » apparaît dessous, le temps de recharger. --}}
            <section class="welcome-choice" aria-labelledby="welcome-choice-title">
                <h2 id="welcome-choice-title">Avec qui voulez-vous travailler ?</h2>
                <div class="welcome-cards">
                    @foreach ($choices as $position => $choice)
                        @php($who = $choice['teacher'])
                        <div @class(['welcome-card', 'is-current' => $choice['current']]) style="--i: {{ $position }}">
                            @if ($choice['current'])
                                <div class="welcome-card-main" aria-current="true">
                                    <span class="welcome-card-face"><img src="{{ Asset::url(ltrim($who->portrait(), '/')) }}" alt="" width="64" height="64"></span>
                                    <span class="welcome-card-name">{{ $who->name() }}</span>
                                    <span class="welcome-card-state"><x-icon name="check-circle" /> Votre choix</span>
                                </div>
                            @else
                                <form method="POST" action="{{ route('teacher.choose') }}">
                                    @csrf
                                    <input type="hidden" name="teacher" value="{{ $who->key }}">
                                    <input type="hidden" name="retour" value="/bienvenue">
                                    <button type="submit" class="welcome-card-main welcome-card-pick" data-busy-keep aria-label="Choisir {{ $who->name() }}">
                                        <span class="welcome-card-face"><img src="{{ Asset::url(ltrim($who->portrait(), '/')) }}" alt="" width="64" height="64"></span>
                                        <span class="welcome-card-name">{{ $who->name() }}</span>
                                        <span class="welcome-card-state">Choisir</span>
                                        <span class="welcome-card-wait" aria-hidden="true">{{ $who->name() }} arrive…</span>
                                    </button>
                                </form>
                            @endif
                            @if ($choice['sample'])
                                <button type="button" class="welcome-card-voice" data-voice-sample="{{ $choice['sample'] }}" aria-label="Écouter la voix de {{ $who->name() }}"><x-icon name="play" /> Sa voix</button>
                            @endif
                        </div>
                    @endforeach
                </div>
                <p class="welcome-choice-note">Modifiable chaque jour, dans « Mon espace ».</p>
            </section>
        @endif

        <div class="welcome-stage">
            <div class="teacher" aria-hidden="true">
                @if ($teacher->face() === 'rose')
                    @include('live.face-rose', ['mouthId' => 'welcome-mouth'])
                @else
                <svg viewBox="0 0 200 250" class="face" role="img">
                    <path d="M16 250 Q20 206 72 194 L128 194 Q180 206 184 250 Z" class="face-vest"/>
                    <path d="M78 194 L100 236 L122 194 Z" class="face-shirt"/>
                    <path d="M94 200 L106 200 L110 236 L100 246 L90 236 Z" class="face-tie"/>
                    <circle cx="100" cy="100" r="88" class="face-skin"/>
                    <path d="M30 78 Q40 18 100 16 Q160 18 170 78 Q150 50 100 48 Q50 50 30 78 Z" class="face-hair"/>
                    <g class="face-eyes">
                        <ellipse cx="70" cy="92" rx="9" ry="10"/>
                        <ellipse cx="130" cy="92" rx="9" ry="10"/>
                    </g>
                    <path d="M56 72 Q70 64 84 72 M116 72 Q130 64 144 72" class="face-brows"/>
                    <ellipse id="welcome-mouth" cx="100" cy="142" rx="24" ry="3" class="face-mouth"/>
                </svg>
                @endif
                <div id="teacher3d" class="teacher3d-stage"></div>
                <div id="teacher3d-loading" class="teacher3d-loading teacher-wait" role="status" hidden>
                    <span class="teacher-wait-dots" aria-hidden="true"><span></span><span></span><span></span></span>
                    <p class="teacher-wait-title">{{ $teacherName }} arrive…</p>
                    <p class="teacher-wait-text">Patientez un instant.</p>
                    <progress max="100" value="0" aria-hidden="true"></progress>
                </div>
            </div>
            <button type="button" id="teacher3d-toggle" class="btn btn-link teacher3d-toggle" hidden></button>
        </div>

        <div class="welcome-panel">
            <div id="welcome-start" class="welcome-start" hidden>
                <button type="button" id="welcome-play" class="btn btn-accent btn-lg btn-block"><x-icon name="play" /> Toucher pour écouter {{ $teacherName }}</button>
                <p class="welcome-hint">Écoutez bien : {{ $teacher->pronoun() }} vous explique comment tout fonctionne.</p>
                <button type="button" id="welcome-skip-start" class="btn btn-link">Passer</button>
            </div>

            <div id="welcome-controls" class="welcome-controls" hidden>
                <button type="button" id="welcome-pause" class="btn btn-light" aria-pressed="false">
                    <span data-pause-label><x-icon name="pause" /> Pause</span>
                    <span data-resume-label hidden><x-icon name="play" /> Reprendre</span>
                </button>
                <button type="button" id="welcome-skip" class="btn btn-link">Passer</button>
            </div>

            <p id="welcome-status" class="welcome-status" role="status" hidden></p>
            <div id="welcome-error" class="alert alert-warning" role="alert" hidden>
                <x-icon name="info" />
                <div>Le message vocal n'est pas disponible pour le moment. Vous pouvez lire le message juste en dessous.</div>
            </div>
            <p id="welcome-partial" class="welcome-status" role="status" hidden>Une partie du message n'est pas encore disponible en voix : elle est écrite juste en dessous.</p>

            {{-- Sous-titres : le texte complet, la phrase dite est mise en évidence. --}}
            <div id="welcome-subtitles" class="welcome-subtitles" role="region" tabindex="0" aria-label="Texte du message de {{ $teacherName }}">
                @foreach ($pieces as $piece)
                    <p>{{ $piece['text'] }}</p>
                @endforeach
            </div>

            <a id="welcome-next" class="btn btn-accent btn-lg btn-block welcome-next" href="{{ $next['url'] }}"><x-icon :name="$next['icon']" /> {{ $next['label'] }}</a>
        </div>
    </div>

    <script type="module" src="{{ Asset::url('js/welcome.js') }}"></script>
</x-layout>
