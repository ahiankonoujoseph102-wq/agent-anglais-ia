{{-- Écran de conversation vocale, commun à l'évaluation et à la séance du jour. --}}
<x-layout :title="$title">
    <div id="live" class="live"
         data-mode="{{ $mode }}"
         data-urls='@json($urls)'
         data-worklet="{{ asset('js/pcm-capture-worklet.js') }}?v={{ @filemtime(public_path('js/pcm-capture-worklet.js')) }}"
         @if (\App\Support\Avatar::enabled())
             data-teacher-view="{{ \App\Support\Avatar::asset('js/teacher-view.js') }}"
             data-teacher3d='@json(\App\Support\Avatar::clientConfig())'
         @endif
         data-teacher="svg">

        <h1>{{ $title }}</h1>

        <div class="teacher" aria-hidden="true">
            <svg viewBox="0 0 200 200" class="face" role="img">
                <circle cx="100" cy="100" r="88" class="face-skin"/>
                <path d="M30 78 Q40 18 100 16 Q160 18 170 78 Q150 50 100 48 Q50 50 30 78 Z" class="face-hair"/>
                <g class="face-eyes">
                    <ellipse cx="70" cy="92" rx="9" ry="10"/>
                    <ellipse cx="130" cy="92" rx="9" ry="10"/>
                </g>
                <path d="M56 72 Q70 64 84 72 M116 72 Q130 64 144 72" class="face-brows"/>
                <ellipse id="mouth" cx="100" cy="142" rx="24" ry="3" class="face-mouth"/>
            </svg>
            {{-- Professeur 3D : chargé en arrière-plan, remplace le visage SVG une fois prêt. --}}
            <div id="teacher3d" class="teacher3d-stage"></div>
            <div id="teacher3d-loading" class="teacher3d-loading" hidden>
                Chargement du professeur 3D…
                <progress max="100" value="0"></progress>
            </div>
        </div>
        <button type="button" id="teacher3d-toggle" class="btn btn-link teacher3d-toggle" hidden></button>

        <div class="live-bar">
            <div>
                <div class="label">Temps restant</div>
                <div id="timer" class="timer" aria-live="off">{{ sprintf('%d:%02d', intdiv($remainingSeconds, 60), $remainingSeconds % 60) }}</div>
            </div>
            <div id="status" class="live-status" role="status">Prêt</div>
        </div>

        <div id="error" class="alert alert-error" role="alert" hidden></div>

        <div id="intro" class="card">
            <p>{{ $intro }}</p>
            <p class="muted small">Installez-vous dans un endroit calme. Avec des écouteurs, le professeur vous entendra mieux. Votre navigateur va vous demander l'accès au micro : acceptez-le.</p>
            <button type="button" id="start" class="btn btn-block">Commencer</button>
        </div>

        <div id="subtitles" class="subtitles" aria-live="polite" hidden></div>

        <div id="outcome" class="card" hidden></div>

        <div class="live-actions">
            <button type="button" id="resume" class="btn btn-block" hidden>Reprendre</button>
            <button type="button" id="stop" class="btn btn-light btn-block" hidden>Terminer</button>
            <a id="back" href="{{ $urls['done'] }}" class="btn btn-light btn-block" hidden>Retour à mon espace</a>
        </div>
    </div>

    <script type="module" src="{{ asset('js/live-session.js') }}?v={{ @filemtime(public_path('js/live-session.js')) }}"></script>
</x-layout>
