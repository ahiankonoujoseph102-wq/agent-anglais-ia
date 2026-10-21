{{-- Écran de conversation vocale, commun à l'évaluation et à la séance du jour. --}}
@php
    // Réglages audio lus par public/js/live-session.js (config/platform.php → live).
    $audio = [
        'echo_cancellation' => (bool) config('platform.live.echo_cancellation'),
        'voice_gain' => (float) config('platform.live.voice_gain'),
        'silent_mic_seconds' => (int) config('platform.live.silent_mic_seconds'),
        'end_of_turn_seconds' => (float) config('platform.live.end_of_turn_seconds'),
        'instant_seconds' => (float) config('platform.live.instant_seconds'),
        'no_answer_seconds' => (float) config('platform.live.no_answer_seconds'),
        'stuck_seconds' => (float) config('platform.live.stuck_seconds'),
        'voice_stuck_seconds' => (float) config('platform.live.voice_stuck_seconds'),
        'messages' => array_map(fn ($text) => str_replace(':teacher', config('avatar.model.name'), $text), config('platform.live.messages')),
    ];

    // LA SUITE à la fin de la conversation (<x-next-step>, montrée par live-session.js, avant le résumé) :
    // UN bouton orange, une phrase qui dit ce qui attend l'élève, les retours en liens discrets.
    $teacherName = config('avatar.model.name');
    $next = match ($mode) {
        'assessment' => auth()->user()?->activeSubscription
            ? ['title' => 'Évaluation terminée', 'text' => 'Votre niveau est enregistré : retrouvez votre programme dans votre espace.', 'href' => route('dashboard'), 'label' => 'Aller à mon espace', 'icon' => null]
            : ['title' => 'Évaluation terminée', 'text' => 'Choisissez votre formule pour commencer votre programme avec '.$teacherName.'.', 'href' => route('payment.plans'), 'label' => 'Choisir ma formule', 'icon' => null],
        'lesson' => ['title' => 'Bravo, séance terminée !', 'text' => 'Votre résumé est juste en dessous. Votre prochaine leçon vous attend dans votre programme.', 'href' => route('program.show').'#suite', 'label' => 'Voir ma prochaine leçon', 'icon' => null],
        'course' => ['title' => 'Séance terminée', 'text' => 'Votre document de révision se prépare : vous le trouverez sur la page de la leçon dans quelques minutes.', 'href' => $urls['done'].'#documents', 'label' => 'Voir mon document de révision', 'icon' => 'file-text'],
        'level_test' => ['title' => 'Bilan terminé', 'text' => 'Votre résultat est enregistré : la suite vous attend dans votre programme.', 'href' => route('program.show').'#suite', 'label' => 'Voir mon programme', 'icon' => null],
        default => null,
    };
    $nextBack = match ($mode) {
        'assessment' => ['href' => $urls['done'], 'label' => 'Voir mon résultat complet'],
        'course' => ['href' => route('dashboard'), 'label' => 'Aller à mon espace'],
        'lesson', 'level_test' => ['href' => route('dashboard'), 'label' => 'Retour à mon espace'],
        default => null,
    };
    // Invitation à installer l'application : à la fin de l'évaluation et de la toute première séance.
    $installHint = $mode === 'assessment'
        || ($mode === 'lesson' && ! auth()->user()?->lessons()->where('used_seconds', '>=', (int) config('teacher.working_together.held_seconds', 120))->exists());
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
         data-live-gemini="{{ \App\Support\Asset::url('js/live-gemini.js') }}"
         data-live-openai="{{ \App\Support\Asset::url('js/live-openai.js') }}"
         data-transcript="{{ \App\Support\Asset::url('js/transcript.js') }}"
         data-turn-watch="{{ \App\Support\Asset::url('js/turn-watch.js') }}"
         data-lesson-board="{{ \App\Support\Asset::url('js/lesson-board.js') }}"
         data-audio='@json($audio)'
         @if (\App\Support\Avatar::enabled())
             data-teacher-view="{{ \App\Support\Avatar::asset('js/teacher-view.js') }}"
             data-teacher3d='@json(\App\Support\Avatar::clientConfig())'
         @endif
         data-teacher="svg"
         data-phase="intro"
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

            @if ($mode === 'lesson')
                {{-- Changement de temps de la séance (pratique, conversation, bilan) : bref bandeau, aucun bouton. --}}
                <p id="phase-note" class="phase-note" role="status" hidden><x-icon name="arrow-right" /> <span data-phase-note-text></span></p>
            @endif

            {{-- Séance du jour : le tableau, sous Teacher Joe sur téléphone (replié), déplacé à droite sur grand écran. --}}
            @if (! empty($board))
                <div id="board-home"><x-live-board :board="$board" :translations="$boardTranslations ?? 'show'" /></div>
            @else
                {{-- Évaluation : le tableau de la mini-leçon découverte y apparaît après le verdict (live-session.js). --}}
                <div id="board-home"></div>
            @endif

            {{-- Ordinateur (900 pixels et plus) : sous Teacher Joe, les conseils avant le début, puis la transcription
                 (live-session.js y déplace #subtitles). Caché sur téléphone et en paysage : rien n'y change. --}}
            <div id="stage-notes" class="live-stage-notes">
                <ul class="intro-tips stage-tips">@include('live.tips')</ul>
            </div>
        </div>

        {{-- Colonne des commandes. Avant le début : « Commencer » tout de suite sous le professeur. --}}
        <div class="live-panel">
            <div id="error" class="alert alert-error" role="alert" hidden></div>
            {{-- Professeur injoignable (aucun fournisseur) : écrire à l'équipe. --}}
            @php($helpWhatsapp = \App\Support\SiteSettings::contact()['whatsapp'] ?? null)
            @if ($helpWhatsapp)
                <a id="whatsapp-help" class="btn btn-whatsapp btn-block" href="{{ \App\Support\WhatsApp::url($helpWhatsapp) }}" target="_blank" rel="noopener" hidden><x-icon name="whatsapp" /> Écrire sur WhatsApp</a>
            @endif

            @if ($next)
                <x-next-step hidden :title="$next['title']" :text="$next['text']" :href="$next['href']" :label="$next['label']" :icon="$next['icon']">
                    @if ($nextBack)<a href="{{ $nextBack['href'] }}">{{ $nextBack['label'] }}</a>@endif
                    @if ($installHint)<x-install-hint />@endif
                </x-next-step>
            @endif

            <div id="intro" class="card">
                <p class="intro-text">{{ $intro }}</p>
                <button type="button" id="start" class="btn btn-accent btn-block btn-lg"><x-icon name="play" /> Commencer</button>
                {{-- Pendant la connexion : « Teacher Joe arrive… », indicateur discret, conseils toujours visibles. --}}
                <p id="arriving" class="live-arriving" role="status"><span class="live-arriving-dots" aria-hidden="true"><span></span><span></span><span></span></span> {{ config('avatar.model.name') }} arrive…</p>
                <ul class="intro-tips">@include('live.tips')</ul>
            </div>

            <div class="live-bar">
                <div>
                    {{-- « Vos questions » après l'annonce du niveau (évaluation), avec le temps restant de ce moment. --}}
                    <div class="label"><x-icon name="clock" /> <span id="timer-label">Temps restant</span></div>
                    <div id="timer" class="timer" aria-live="off">{{ sprintf('%d:%02d', intdiv($remainingSeconds, 60), $remainingSeconds % 60) }}</div>
                </div>
                <div id="status" class="live-status" role="status">Prêt</div>
            </div>

            {{-- Tour de parole : « Teacher Joe parle… », « À vous de parler », « Un instant… ». --}}
            <div id="turn" class="live-turn" data-turn="listening" role="status" aria-live="polite" hidden>
                <span class="live-turn-dot" aria-hidden="true"></span>
                <span id="turn-text"></span>
            </div>

            {{-- Aucune réponse 8 secondes après la fin de la parole : connexion lente, puis « Relancer » (turn-watch.js). --}}
            <div id="wait-note" class="alert alert-warning live-wait" role="status" hidden>
                <x-icon name="hourglass" />
                <div>
                    <p id="wait-text"></p>
                    <button type="button" id="nudge" class="btn btn-accent" hidden><x-icon name="refresh" /> Relancer {{ config('avatar.model.name') }}</button>
                </div>
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

            {{-- Grand écran (et téléphone couché) : le tableau vient ici, à côté de Teacher Joe. --}}
            <div id="board-wide"></div>

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


            <div id="subtitles-home"><div id="subtitles" class="subtitles" aria-live="polite" hidden></div></div>

            <div id="outcome" class="card" hidden></div>

            @if ($mode === 'assessment')
                {{-- Affiché sous le résultat de l'évaluation : le professeur dit d'y toucher le bouton orange. --}}
                <section id="plans-offer" class="plans-offer" hidden>
                    <h2>Continuez avec {{ config('avatar.model.name') }} : choisissez votre formule</h2>
                    @include('assessment.plans')
                </section>
            @endif

            <div class="live-actions">
                <button type="button" id="resume" class="btn btn-block btn-lg" hidden><x-icon name="refresh" /> Reprendre la séance</button>
                <button type="button" id="stop" class="btn btn-light btn-block" hidden>Terminer</button>
                <a id="back" href="{{ $urls['done'] }}" @class(['btn btn-block', 'btn-light' => $mode !== 'assessment']) hidden>{{ match ($mode) { 'assessment' => 'Voir mon résultat complet', 'course' => 'Retour à ma leçon', default => 'Retour à mon espace' } }}</a>
            </div>

            {{-- Évaluation, séance du jour, « Mes cours » : ce que l'élève peut dire pour demander de l'aide. En bas de la
                 colonne : sur téléphone, les sous-titres, le résultat et les formules passent avant. --}}
            @if (in_array($mode, ['assessment', 'lesson', 'course'], true))
                <x-useful-phrases :open="$usefulPhrasesOpen ?? false" />
            @endif
        </div>
    </div>

    <script type="module" src="{{ \App\Support\Asset::url('js/live-session.js') }}"></script>
</x-layout>
