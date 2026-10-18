{{--
    Bienvenue de Teacher Joe : message vocal à la première ouverture d'un accès (formule active).
    Même technique que l'écran de conversation : professeur 3D (repli : visage simple) dont la bouche suit la
    voix (public/js/welcome.js), sous-titres du texte complet, boutons « Pause » et « Passer », puis le bouton
    de la prochaine étape. Sans JavaScript : le texte complet et le bouton sont affichés tout de suite.
--}}
@php
    use App\Support\Asset;

    $teacher = config('avatar.model.name');
    $audio = ['voice_gain' => (float) config('platform.live.voice_gain')];
@endphp
<x-layout title="Bienvenue" focus>
    <div id="welcome" class="welcome-page"
         data-teacher="svg"
         data-teacher-name="{{ $teacher }}"
         data-pieces='@json($pieces)'
         data-audio='@json($audio)'
         data-teacher-cues="{{ Asset::url('js/teacher-cues.js') }}"
         @if ($teacher3d)
             data-teacher-view="{{ \App\Support\Avatar::asset('js/teacher-view.js') }}"
             data-teacher3d='@json($teacher3d)'
         @endif>

        <div class="welcome-head">
            <h1>Bienvenue, {{ $firstName }} !</h1>
            <p class="welcome-lead">Félicitations pour votre formule {{ $planName }} !</p>
        </div>

        <div class="welcome-stage">
            <div class="teacher" aria-hidden="true">
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
                <div id="teacher3d" class="teacher3d-stage"></div>
                <div id="teacher3d-loading" class="teacher3d-loading" hidden>
                    Chargement du professeur 3D…
                    <progress max="100" value="0"></progress>
                </div>
            </div>
            <button type="button" id="teacher3d-toggle" class="btn btn-link teacher3d-toggle" hidden></button>
        </div>

        <div class="welcome-panel">
            <div id="welcome-start" class="welcome-start" hidden>
                <button type="button" id="welcome-play" class="btn btn-accent btn-lg btn-block"><x-icon name="play" /> Toucher pour écouter {{ $teacher }}</button>
                <p class="welcome-hint">Écoutez bien : il vous explique comment tout fonctionne.</p>
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

            {{-- Sous-titres : le texte complet, la phrase dite est mise en évidence. --}}
            <div id="welcome-subtitles" class="welcome-subtitles" role="region" tabindex="0" aria-label="Texte du message de {{ $teacher }}">
                @foreach ($pieces as $piece)
                    <p>{{ $piece['text'] }}</p>
                @endforeach
            </div>

            <a id="welcome-next" class="btn btn-accent btn-lg btn-block welcome-next" href="{{ $next['url'] }}"><x-icon :name="$next['icon']" /> {{ $next['label'] }}</a>
        </div>
    </div>

    <script type="module" src="{{ Asset::url('js/welcome.js') }}"></script>
</x-layout>
