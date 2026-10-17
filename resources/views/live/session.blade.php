{{-- Écran de conversation vocale, commun à l'évaluation et à la séance du jour. --}}
@php
    // Réglages audio lus par public/js/live-session.js (config/platform.php → live).
    $audio = [
        'echo_cancellation' => (bool) config('platform.live.echo_cancellation'),
        'voice_gain' => (float) config('platform.live.voice_gain'),
        'silent_mic_seconds' => (int) config('platform.live.silent_mic_seconds'),
    ];
@endphp
<x-layout :title="$title" focus>
    <div id="live" class="live"
         data-mode="{{ $mode }}"
         data-stop-confirm='@json(\App\Support\Confirmation::for('conversation.stop'))'
         data-urls='@json($urls)'
         data-worklet="{{ \App\Support\Asset::url('js/pcm-capture-worklet.js') }}"
         data-mic-gate="{{ \App\Support\Asset::url('js/mic-gate.js') }}"
         data-pcm-audio="{{ \App\Support\Asset::url('js/pcm-audio.js') }}"
         data-mic-input="{{ \App\Support\Asset::url('js/mic-input.js') }}"
         data-conversation-end="{{ \App\Support\Asset::url('js/conversation-end.js') }}"
         data-teacher-cues="{{ \App\Support\Asset::url('js/teacher-cues.js') }}"
         data-audio='@json($audio)'
         @if (\App\Support\Avatar::enabled())
             data-teacher-view="{{ \App\Support\Avatar::asset('js/teacher-view.js') }}"
             data-teacher3d='@json(\App\Support\Avatar::clientConfig())'
         @endif
         data-teacher="svg"
         data-teacher-name="{{ config('avatar.model.name') }}">

        <div class="live-head">
            <h1>{{ $title }}</h1>
        </div>

        {{-- Colonne du professeur (à gauche en paysage et sur ordinateur). --}}
        <div class="live-stage">
            <div class="teacher" aria-hidden="true">
                <svg viewBox="0 0 200 250" class="face" role="img">
                    {{-- Tenue de Teacher Joe : gilet bleu nuit, chemise blanche, cravate orange. --}}
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
        </div>

        {{-- Colonne des commandes. Avant le début : « Commencer » tout de suite sous le professeur. --}}
        <div class="live-panel">
            <div id="error" class="alert alert-error" role="alert" hidden></div>

            <div id="intro" class="card">
                <p class="intro-text">{{ $intro }}</p>
                <button type="button" id="start" class="btn btn-accent btn-block btn-lg"><x-icon name="play" /> Commencer</button>
                <ul class="intro-tips">
                    <li><x-icon name="pin" /> Installez-vous dans un endroit calme.</li>
                    <li><x-icon name="headphones" /> Avec des écouteurs, le professeur vous entendra mieux.</li>
                    <li><x-icon name="bubble" /> Quand {{ config('avatar.model.name') }} parle, votre micro est coupé : attendez « À vous de parler » pour répondre.</li>
                    <li><x-icon name="mic" /> Votre navigateur va vous demander l'accès au micro : acceptez-le.</li>
                </ul>
            </div>

            <div class="live-bar">
                <div>
                    {{-- « Vos questions » après l'annonce du niveau (évaluation), avec le temps restant de ce moment. --}}
                    <div class="label"><x-icon name="clock" /> <span id="timer-label">Temps restant</span></div>
                    <div id="timer" class="timer" aria-live="off">{{ sprintf('%d:%02d', intdiv($remainingSeconds, 60), $remainingSeconds % 60) }}</div>
                </div>
                <div id="status" class="live-status" role="status">Prêt</div>
            </div>

            {{-- Tour de parole : « Teacher Joe parle… », « À vous de parler », « Teacher Joe réfléchit… ». --}}
            <div id="turn" class="live-turn" data-turn="listening" role="status" aria-live="polite" hidden>
                <span class="live-turn-dot" aria-hidden="true"></span>
                <span id="turn-text"></span>
            </div>

            {{-- Micro : témoin de niveau et choix du micro (plusieurs micros : téléphone, Bluetooth, écouteurs). --}}
            <div id="mic-panel" class="mic-panel" hidden>
                <div class="mic-meter" title="Niveau de votre micro" aria-hidden="true">
                    <x-icon name="mic" />
                    <span class="mic-meter-track"><span id="mic-level" class="mic-meter-bar"></span></span>
                </div>
                <label id="mic-choice" class="mic-choice" hidden>
                    <span>Micro utilisé</span>
                    <select id="mic-select"></select>
                </label>
            </div>

            <div id="mic-warning" class="alert alert-warning" role="alert" hidden>
                <x-icon name="mic-off" />
                <div>Nous ne vous entendons pas. Vérifiez votre micro. Si des écouteurs ou une enceinte Bluetooth sont connectés, portez-les ou déconnectez-les.</div>
            </div>

            <div id="pause-note" class="alert alert-warning" hidden>
                <x-icon name="clock" />
                <div><strong>Le temps continue pendant la pause.</strong> Au-delà de {{ \App\Support\Duration::format((int) config('platform.live.pause_max_seconds')) }}, la conversation se termine.</div>
            </div>

            <div id="controls" class="live-controls" hidden>
                <button type="button" id="interrupt" class="btn btn-accent btn-lg live-control live-control-wide" hidden>
                    <x-icon name="hand" /> Interrompre le professeur
                </button>
                <button type="button" id="pause" class="btn btn-lg live-control" aria-pressed="false">
                    <span class="live-control-off"><x-icon name="pause" /> Pause</span>
                    <span class="live-control-on" hidden><x-icon name="play" /> Reprendre</span>
                </button>
                <button type="button" id="mute" class="btn btn-outline btn-lg live-control" aria-pressed="false">
                    <span class="live-control-off"><x-icon name="mic-off" /> Couper le micro</span>
                    <span class="live-control-on" hidden><x-icon name="mic" /> Réactiver le micro</span>
                </button>
            </div>


            <div id="subtitles" class="subtitles" aria-live="polite" hidden></div>

            <div id="outcome" class="card" hidden></div>

            @if ($mode === 'assessment')
                {{-- Affiché sous le résultat de l'évaluation : le professeur dit d'y toucher le bouton orange. --}}
                <section id="plans-offer" class="plans-offer" hidden>
                    <h2>Continuez avec {{ config('avatar.model.name') }} : choisissez votre formule</h2>
                    @include('assessment.plans')
                </section>
            @endif

            <div class="live-actions">
                <button type="button" id="resume" class="btn btn-block btn-lg" hidden><x-icon name="refresh" /> Se reconnecter</button>
                <button type="button" id="stop" class="btn btn-light btn-block" hidden>Terminer</button>
                <a id="back" href="{{ $urls['done'] }}" @class(['btn btn-block', 'btn-light' => $mode !== 'assessment']) hidden>{{ match ($mode) { 'assessment' => 'Voir mon résultat complet', 'course' => 'Retour à ma leçon', default => 'Retour à mon espace' } }}</a>
            </div>
        </div>
    </div>

    <script type="module" src="{{ \App\Support\Asset::url('js/live-session.js') }}"></script>
</x-layout>
