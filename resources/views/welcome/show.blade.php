{{--
    Bienvenue du professeur : message vocal à la première ouverture d'un accès (formule active). Plusieurs professeurs
    proposés : « Avec qui voulez-vous apprendre ? » en haut, une carte par professeur (photo et nom). La page connaît
    les deux professeurs (data-teachers) : toucher l'autre carte change aussitôt la carte en vert, le personnage, le
    message et sa voix, et le bouton d'écoute ; le choix est enregistré en arrière-plan (sans JavaScript : la carte
    est un formulaire qui recharge la page).
    Même technique que l'écran de conversation : professeur 3D (repli : visage simple) dont la bouche suit la
    voix (public/js/welcome.js), sous-titres du texte complet, boutons « Pause » et « Passer », puis le bouton
    de la prochaine étape. Sans JavaScript : le texte complet et le bouton sont affichés tout de suite.
--}}
@php
    use App\Support\Asset;

    $teacherName = $teacher->name();
    $teacher3d = $current['teacher3d'];
    $pieces = $current['pieces'];
    $next = $current['next'];
    $anyTeacher3d = collect($teachers)->contains(fn ($data) => $data['teacher3d'] !== null);
    $audio = ['voice_gain' => (float) config('platform.speech.page_gain', 1.0)];
@endphp
<x-layout title="Bienvenue" focus>
    <div id="welcome" @class(['welcome-page', 'has-choice' => $hasChoice])
         data-teacher="{{ $teacher3d ? 'loading' : 'svg' }}"
         data-teacher-name="{{ $teacherName }}"
         data-current="{{ $teacher->key }}"
         data-teachers='@json($teachers)'
         data-choose-url="{{ route('teacher.choose') }}"
         data-audio='@json($audio)'
         data-teacher-cues="{{ Asset::url('js/teacher-cues.js') }}"
         @if ($anyTeacher3d)
             data-teacher-view="{{ \App\Support\Avatar::asset('js/teacher-view.js') }}"
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

        @if ($hasChoice)
            {{-- Choix du professeur : une carte par professeur, seulement sa photo et son nom. Le professeur choisi est
                 encadré de vert, avec « Votre choix ». Toucher l'autre carte change tout aussitôt (welcome.js). --}}
            <section class="welcome-choice" aria-labelledby="welcome-choice-title">
                <h2 id="welcome-choice-title">Avec qui voulez-vous apprendre ?</h2>
                <div class="welcome-cards">
                    @foreach ($teachers as $key => $who)
                        @php($chosen = $key === $teacher->key)
                        <form method="POST" action="{{ route('teacher.choose') }}" @class(['welcome-card', 'is-current' => $chosen]) style="--i: {{ $loop->index }}" data-card="{{ $key }}">
                            @csrf
                            <input type="hidden" name="teacher" value="{{ $key }}">
                            <input type="hidden" name="retour" value="/bienvenue">
                            <button type="submit" class="welcome-card-main" data-busy-keep aria-pressed="{{ $chosen ? 'true' : 'false' }}">
                                <span class="welcome-card-face"><img src="{{ $who['portrait'] }}" alt="" width="64" height="64"></span>
                                <span class="welcome-card-name">{{ $who['name'] }}</span>
                                <span class="welcome-card-state" data-state-chosen @if (! $chosen) hidden @endif><x-icon name="check-circle" /> Votre choix</span>
                                <span class="welcome-card-state welcome-card-choose" data-state-choose @if ($chosen) hidden @endif>Choisir</span>
                            </button>
                        </form>
                    @endforeach
                </div>
                <p id="welcome-switch" class="welcome-choice-note" role="status">Modifiable chaque jour, dans « Mon espace ».</p>
            </section>
        @endif

        <div class="welcome-stage">
            <div class="teacher" aria-hidden="true">
                @foreach ($teachers as $key => $who)
                    <div class="welcome-face" data-face-for="{{ $key }}" @if ($key !== $teacher->key) hidden @endif>
                        @if ($who['face'] === 'rose')
                            @include('live.face-rose', ['mouthId' => 'welcome-mouth-'.$key])
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
                                <ellipse id="welcome-mouth-{{ $key }}" cx="100" cy="142" rx="24" ry="3" class="face-mouth"/>
                            </svg>
                        @endif
                    </div>
                @endforeach
                <div id="teacher3d" class="teacher3d-stage"></div>
                <div id="teacher3d-loading" class="teacher3d-loading teacher-wait" role="status" hidden>
                    <span class="teacher-wait-dots" aria-hidden="true"><span></span><span></span><span></span></span>
                    <p class="teacher-wait-title" data-arriving>{{ $current['arriving'] }}</p>
                    <p class="teacher-wait-text">Patientez un instant.</p>
                    <progress max="100" value="0" aria-hidden="true"></progress>
                </div>
            </div>
            <button type="button" id="teacher3d-toggle" class="btn btn-link teacher3d-toggle" hidden></button>
        </div>

        <div class="welcome-panel">
            <div id="welcome-start" class="welcome-start" hidden>
                <button type="button" id="welcome-play" class="btn btn-accent btn-lg btn-block"><x-icon name="play" /> <span data-play-label>{{ $current['play'] }}</span></button>
                <p class="welcome-hint" data-hint>{{ $current['hint'] }}</p>
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
            <div id="welcome-subtitles" class="welcome-subtitles" role="region" tabindex="0" aria-label="{{ $current['subtitles'] }}">
                @foreach ($pieces as $piece)
                    <p>{{ $piece['text'] }}</p>
                @endforeach
            </div>

            <a id="welcome-next" class="btn btn-accent btn-lg btn-block welcome-next" href="{{ $next['url'] }}"><x-icon :name="$next['icon']" /> <span data-next-label>{{ $next['label'] }}</span></a>
        </div>
    </div>

    <script type="module" src="{{ Asset::url('js/welcome.js') }}"></script>
</x-layout>
