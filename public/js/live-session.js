/*
 * Conversation vocale avec le professeur (évaluation, séance du jour, bilan de niveau et « Mes cours »).
 *
 * Deux fournisseurs, choisis par le serveur au DÉBUT de la séance (jamais au milieu) : Gemini Live
 * (live-gemini.js : WebSocket, jeton éphémère) ou, en secours, OpenAI Realtime (live-openai.js : WebRTC,
 * appel ouvert par le serveur). Tout ce qui est propre à un fournisseur est dans son adaptateur ; cet écran
 * reçoit les mêmes événements dans les deux cas. La clé API ne quitte jamais le serveur, qui reste maître
 * du temps : le jeton Gemini expire à la limite, l'appel OpenAI est raccroché par le serveur, et le
 * navigateur envoie un signal régulier qui enregistre le temps consommé.
 *
 * Audio : Gemini reçoit le micro en PCM 16 bits 16 kHz et renvoie la voix en PCM 16 bits 24 kHz (tampons
 * programmés ici) ; OpenAI échange le son par WebRTC (piste branchée sur la même chaîne de lecture). La
 * bouche du visage suit le volume de cette voix.
 *
 * Le professeur 3D (teacher-view.js, teacher-3d.js) est facultatif : il se
 * charge en arrière-plan et reçoit la voix, le micro et les expressions.
 *
 * Anti-écho : DEMI-DUPLEX sur tous les appareils. Tant que la voix du
 * professeur est jouée (et echo_guard_ms après), le son du micro n'est pas
 * envoyé à Gemini : sa propre voix, revenue par le haut-parleur, ne peut plus
 * l'interrompre. Les écouteurs ne sont pas détectables de façon fiable dans
 * Chrome sur Android (pas de liste des sorties audio, noms des appareils non
 * normalisés, Bluetooth invisible) : on ne fait donc aucune exception. Pour
 * parler pendant sa voix, l'apprenant touche « Interrompre le professeur ».
 */

const root = document.getElementById('live');
// Porte du micro (URL versionnée par la page, comme les autres fichiers).
const { MicGate, voiceEndsAt, voiceLooksOver, heldVoiceEnd, sendsTurnSilence } = await import(root.dataset.micGate);
// Jetons consommés, répartis par nature (texte, audio, cache), pour une estimation juste du coût.
const { TokenTally, geminiUsage, openAiUsage } = await import(root.dataset.tokenUsage);
// Lecture propre de la voix (décodage, programmation bout à bout, rééchantillonnage continu).
const { VOICE_RATE, decodePcm16, StreamResampler, VoiceScheduler, fadeIn } = await import(root.dataset.pcmAudio);
// Micro : liste « Micro utilisé », témoin de niveau, alerte de silence.
const { listMicrophones, rms, meterLevel, SilenceWatch } = await import(root.dataset.micInput);
// Réglages audio de la page (config/platform.php → live).
const audioConfig = JSON.parse(root.dataset.audio);
// Fin automatique après le verdict ; expressions du professeur 3D d'après ses paroles.
const { ConversationEnd, SessionClosing, mentionsLevel, levelRevealDue } = await import(root.dataset.conversationEnd);
const { CueTracker } = await import(root.dataset.teacherCues);
// Adaptateurs des fournisseurs (même langage d'événements pour l'écran).
const { GeminiTransport } = await import(root.dataset.liveGemini);
const { OpenAiTransport } = await import(root.dataset.liveOpenai);
// Texte de la conversation (jamais l'audio), envoyé au serveur par petits lots.
const { TranscriptRecorder, cleanSpeech } = await import(root.dataset.transcript);
// Mesure des tours de parole (délai de réponse), fin de tour de secours, attente d'une réponse.
const { VoiceActivity, TurnWatch, learnerMetrics, teacherMetrics } = await import(root.dataset.turnWatch);
// Tableau de la séance du jour : élément expliqué et étape en cours, d'après la voix du professeur.
const { BoardTracker, BoardView, phaseNote } = root.dataset.lessonBoard ? await import(root.dataset.lessonBoard) : {};
// Bandeau « À vous de parler » : l'exemple suit la dernière question réellement posée par le professeur.
const { listeningText } = await import(root.dataset.answerHint);
// Secondes, pour la fin automatique.
const clock = () => performance.now() / 1000;
const MIC_PREFERENCE = 'mic-device';
const urls = JSON.parse(root.dataset.urls);
const mode = root.dataset.mode;
const csrf = document.querySelector('meta[name="csrf-token"]').content;

const $ = (id) => document.getElementById(id);
const ui = {
    start: $('start'), stop: $('stop'), resume: $('resume'), back: $('back'),
    intro: $('intro'), status: $('status'), timer: $('timer'), timerLabel: $('timer-label'), error: $('error'),
    subtitles: $('subtitles'), outcome: $('outcome'), mouth: $('mouth'),
    turn: $('turn'), turnText: $('turn-text'), controls: $('controls'), plans: $('plans-offer'),
    micPanel: $('mic-panel'), micLevel: $('mic-level'), micChoice: $('mic-choice'), micSelect: $('mic-select'), micWarning: $('mic-warning'),
    waitNote: $('wait-note'), waitText: $('wait-text'), nudge: $('nudge'),
    interrupt: $('interrupt'), pause: $('pause'), mute: $('mute'), pauseNote: $('pause-note'),
    stageNotes: $('stage-notes'), subtitlesHome: $('subtitles-home'),
};
const teacherName = root.dataset.teacherName || 'Le professeur';

/**
 * Étape de l'écran (attribut data-phase, lu par le CSS) : intro (avant « Commencer »), connecting
 * (« Teacher Joe arrive… », conseils visibles), live, done.
 */
function setPhase(phase) {
    root.dataset.phase = phase;
}

// Ordinateur (900 pixels de large et plus, hors téléphone couché) : la transcription passe sous Teacher Joe ; téléphone : à sa place d'origine.
const wideScreen = window.matchMedia?.('(min-width: 900px) and (min-height: 561px)');

function placeSubtitles() {
    const target = wideScreen?.matches && ui.stageNotes ? ui.stageNotes : ui.subtitlesHome;

    if (target && ui.subtitles.parentElement !== target) {
        target.append(ui.subtitles);
        ui.subtitles.scrollTop = ui.subtitles.scrollHeight;
    }
}

placeSubtitles();
wideScreen?.addEventListener?.('change', placeSubtitles);

// Tableau (séance du jour) : sous Teacher Joe et replié sur téléphone ; à droite et ouvert sur grand écran et téléphone couché.
let boardEl = $('board');
let board = boardEl && BoardView ? { view: new BoardView(boardEl), tracker: null, nudged: false } : null;
const wideBoard = window.matchMedia?.('(min-width: 900px), (orientation: landscape) and (max-height: 560px)');

function placeBoard() {
    if (!board) {
        return;
    }

    const wide = wideBoard?.matches;
    const target = wide ? $('board-wide') : $('board-home');

    if (target && boardEl.parentElement !== target) {
        target.append(boardEl);
    }

    if (wide) {
        boardEl.open = true;
    }
}

placeBoard();
wideBoard?.addEventListener?.('change', placeBoard);
boardEl?.querySelector('[data-board-translations]')?.addEventListener('change', (event) => {
    boardEl.classList.toggle('show-translations', event.target.checked);
});

/**
 * Évaluation : après le verdict, le tableau de la mini-leçon (les vraies erreurs de l'apprenant et leur bonne forme,
 * ou, en secours, quelques phrases de la première leçon du niveau trouvé ; envoyées par /resultat). Jamais avant le verdict : il trahirait le niveau. Même suivi que la séance
 * du jour (la phrase dite par le professeur s'allume), sans étapes.
 */
function showDiscoveryBoard(discovery) {
    if (board || !BoardView || !discovery?.phrases?.length) {
        return;
    }

    const items = discovery.phrases.map((phrase, i) => {
        const meaning = phrase.fr ?? phrase.note;

        return `<li class="board-item" data-board-item="p${i}" data-kind="phrase" data-say="${escapeHtml(phrase.en)}">`
            + `<span class="board-en" lang="en">${escapeHtml(phrase.en)}</span>`
            + (meaning ? `<span class="board-fr">${escapeHtml(meaning)}</span>` : '')
            + '</li>';
    }).join('');

    boardEl = document.createElement('details');
    boardEl.id = 'board';
    boardEl.className = 'board board-discovery';
    boardEl.open = true;
    boardEl.dataset.translations = 'show';
    boardEl.innerHTML = '<summary class="board-head"><span class="board-label">Tableau</span>'
        + '<span class="board-phase" data-board-phase>Découverte</span><span class="board-now" data-board-now aria-live="polite"></span></summary>'
        + `<div class="board-body"><p class="board-title">${escapeHtml(discovery.title ?? '')}</p>`
        + `<p class="board-heading">${escapeHtml(discovery.heading ?? 'Votre première phrase')}</p><ul class="board-list">${items}</ul></div>`;

    board = { view: new BoardView(boardEl), tracker: null, nudged: false };
    board.tracker = new BoardTracker(board.view.items());
    placeBoard();
    // Vidéo en cours : le tableau de la mini-leçon y apparaît aussi (écran partagé).
    recording.recorder?.setBoard(boardEl);

    if (!boardEl.isConnected) {
        $('controls')?.before(boardEl);
    }
}

/** Secondes écoulées depuis le début de la séance (temps du serveur). */
function lessonElapsed() {
    return Math.max(0, (state.config?.phases?.total ?? 0) - remainingSeconds());
}

/** Étape en cours du tableau (ou null hors séance du jour). */
function boardPhase() {
    return board?.tracker ? board.tracker.phase(lessonElapsed()) : null;
}

/**
 * Indication de reprise (coupure ou pause) : celle de l'étape en cours, avec le dernier élément du tableau
 * expliqué, pour que Teacher Joe ne recommence ni l'accueil ni la leçon.
 */
function resumeCue(kind) {
    const phases = state.config?.cues?.[kind === 'pause' ? 'pause_phases' : 'resume_phases'];
    const phase = boardPhase();
    const fallback = kind === 'pause' ? state.config.cues.pause_resume : state.config.cues.resume;

    if (!phases || !phase || !phases[phase]) {
        return fallback;
    }

    const last = board.tracker.current ? board.view.label(board.tracker.current) : null;

    return phases[phase] + (last ? ` The last board item you were working on: "${last}".` : '');
}

// Professeur 3D, chargé en arrière-plan ; un emplacement de chargement (data-teacher="loading") est affiché en
// attendant, jamais le visage simple, qui n'apparaît qu'en repli (data-teacher="svg").
let teacher = null;

if (root.dataset.teacher3d && root.dataset.teacherView) {
    import(root.dataset.teacherView)
        .then(({ createTeacherView }) => {
            teacher = createTeacherView(root, JSON.parse(root.dataset.teacher3d));
            linkTeacherToRecording();

            if (state.audio) {
                teacher.attachAudio(state.audio.context, state.audio.output, state.audio.micBus);
            }
        })
        .catch((error) => {
            console.warn('Professeur 3D indisponible :', error);
            root.dataset.teacher = 'svg';
        });
}

// Repli sur le visage simple (téléphone incompatible, échec, choix de l'apprenant) : la vidéo en attente de la
// première image 3D commence avec lui.
new MutationObserver(() => {
    if (root.dataset.teacher === 'svg') {
        recording.recorder?.useFace();
    }

    placeRecorder();
}).observe(root, { attributes: true, attributeFilter: ['data-teacher', 'data-phase'] });

/* ---------- « Enregistrer ma séance » (vidéo fabriquée dans le navigateur, jamais envoyée) ---------- */

const recording = { module: null, mimeType: null, armed: false, recorder: null, timer: null, url: null, downloaded: false, guard: null };
const rec = {
    box: $('recorder'), start: $('record-start'), armed: $('record-armed'), live: $('record-live'),
    time: $('record-time'), stop: $('record-stop'), download: $('record-download'), size: $('record-size'),
    unsupported: $('record-unsupported'), ready: $('record-ready'), saved: $('record-saved'),
};

/** Vidéo en jeu (enregistrement en cours, ou prête et pas téléchargée) : confirmation avant de quitter la page. */
function guardVideo() {
    recording.guard?.update({ recording: Boolean(recording.recorder), ready: Boolean(recording.url), downloaded: recording.downloaded });
}

// Chargé à part, sans retarder l'écran de la séance.
if (rec.box && root.dataset.recorder) {
    import(root.dataset.recorder)
        .then((module) => {
            recording.module = module;
            recording.mimeType = module.supportInfo();

            return recording.mimeType && root.dataset.videoGuard ? import(root.dataset.videoGuard) : null;
        })
        .then((guard) => {
            if (!guard) {
                return;
            }

            const texts = JSON.parse(root.dataset.videoLeave || '{}');
            recording.guard = new guard.VideoGuard({
                ask: () => (window.askConfirmation
                    ? window.askConfirmation({ title: texts.title, message: texts.message, confirm: texts.confirm })
                    : Promise.resolve(null)),
            });
            guardVideo();
        })
        .catch((error) => console.warn('Enregistrement indisponible :', error))
        .finally(() => {
            if (!recording.mimeType) {
                rec.unsupported.hidden = false;

                return;
            }

            rec.box.hidden = false;
            rec.start.addEventListener('click', () => {
                if (state.audio) {
                    startRecording();

                    return;
                }

                // Avant « Commencer » : l'enregistrement démarrera avec la séance.
                recording.armed = !recording.armed;
                rec.start.setAttribute('aria-pressed', String(recording.armed));
                rec.armed.hidden = !recording.armed;
            });
            rec.stop.addEventListener('click', () => stopRecording());
            // Toucher « Télécharger ma vidéo » : la vidéo est gardée sur le téléphone, plus rien à protéger.
            rec.download.addEventListener('click', () => {
                recording.downloaded = true;
                rec.ready.hidden = true;
                rec.saved.hidden = false;
                rec.box.classList.remove('is-ready');
                guardVideo();
            });
        });
}

/**
 * Avant « Commencer » : « Enregistrer ma séance » juste sous le bouton et sa ligne (#start-block), avant
 * « Avant de commencer », sur l'évaluation comme sur la séance du jour ; ensuite, à sa place sous les commandes.
 */
function placeRecorder() {
    if (!rec.box) {
        return;
    }

    const before = root.dataset.phase === 'intro' ? ($('start-block') ?? $('start')) : null;
    const anchor = before ?? $('subtitles-home');

    if (!anchor) {
        return;
    }

    // Vidéo prête après la fin de la séance : juste sous « La suite », là où la page descend, tant qu'elle n'est pas téléchargée.
    const next = $('suite');

    if (root.dataset.phase === 'done' && recording.url && !recording.downloaded && next && !next.hidden) {
        if (next.nextElementSibling !== rec.box) {
            next.after(rec.box, rec.unsupported);
        }

        return;
    }

    if (before) {
        if (rec.box.previousElementSibling !== before) {
            before.after(rec.box, rec.unsupported);
        }
    } else if (rec.box.nextElementSibling !== rec.unsupported || rec.unsupported.nextElementSibling !== anchor) {
        anchor.before(rec.box, rec.unsupported);
    }
}

placeRecorder();

function linkTeacherToRecording() {
    if (teacher) {
        teacher.onRender = recording.recorder ? (teacher3d) => recording.recorder?.captureTeacher(teacher3d) : null;
    }
}

function startRecording() {
    if (!recording.mimeType || recording.recorder || !state.audio || state.ending) {
        return;
    }

    const config = JSON.parse(root.dataset.recording);
    const recorder = new recording.module.SessionRecorder(config, {
        svg: root.querySelector('.teacher svg.face'),
        mouth: ui.mouth,
        mimeType: recording.mimeType,
        // Tableau de la séance, dessiné dans la vidéo même quand il est replié à l'écran.
        board: board ? boardEl : null,
        // Professeur 3D en chargement : la vidéo ne commence qu'avec sa première vraie image (couverture).
        waitForTeacher: root.dataset.teacher !== 'svg',
        // Évaluation : le temps de l'écran, recopié dans le tableau neutre de la vidéo.
        clock: () => ({ label: ui.timerLabel?.textContent ?? '', value: ui.timer?.textContent ?? '' }),
    });

    // Verdict déjà affiché (enregistrement lancé pendant les questions) : la vidéo montre aussi le niveau.
    if (state.revealed) {
        recorder.revealLevel(state.revealed.level, state.revealed.label);
    }

    try {
        recorder.start({ context: state.audio.context, voiceNode: state.audio.analyser, micNode: state.audio.micBus });
        recorder.learnerOpen(state.gateOpen !== false);
    } catch (error) {
        // Jamais bloquant : la séance continue, sans vidéo.
        console.warn('Enregistrement impossible :', error);
        recorder.stop();
        rec.box.hidden = true;
        rec.unsupported.hidden = false;

        return;
    }

    recording.recorder = recorder;
    recording.armed = false;
    linkTeacherToRecording();
    rec.start.hidden = true;
    rec.armed.hidden = true;
    rec.live.hidden = false;
    guardVideo();
    const render = () => { rec.time.textContent = recording.module.formatElapsed(recorder.elapsedSeconds); };
    render();
    recording.timer = setInterval(render, 1000);
}

/** Arrête l'enregistrement (bouton, ou fin de la séance) et propose la vidéo. */
async function stopRecording() {
    const recorder = recording.recorder;

    if (!recorder || recording.stopping) {
        return;
    }

    recording.stopping = true;
    clearInterval(recording.timer);
    rec.live.hidden = true;
    linkTeacherToRecording();

    let blob = null;

    try {
        blob = await recorder.stop();
    } catch (error) {
        console.warn('Enregistrement interrompu :', error);
    }

    recording.recorder = null;
    linkTeacherToRecording();
    guardVideo();

    if (!blob) {
        rec.unsupported.hidden = false;
        rec.unsupported.textContent = 'La vidéo n\'a pas pu être enregistrée. Votre séance continue normalement.';

        return;
    }

    const config = JSON.parse(root.dataset.recording);
    recording.url = URL.createObjectURL(blob);
    rec.download.href = recording.url;
    rec.download.download = recording.module.fileName(config.file_prefix, new Date(), recording.module.extensionFor(recording.mimeType));
    rec.size.textContent = recording.module.formatSize(blob.size);
    rec.download.hidden = false;
    rec.ready.hidden = false;
    rec.box.classList.add('is-ready');
    guardVideo();
    placeRecorder();
}

const INPUT_RATE = 16000;
// Fondu à l'arrêt de la voix (interruption, pause), en secondes : pas de clic.
const STOP_FADE = 0.015;
const MAX_AUTO_RECONNECTS = 2;
// Retard ajouté à la voix du professeur (lecture labiale du professeur 3D).
const OUTPUT_DELAY = 0.08;
// Textes de l'attente et des coupures (config/platform.php → live.messages).
const WAIT = audioConfig.messages ?? {};
// Après la fin de parole de l'apprenant : au-delà, « Un instant… » et un léger geste du professeur.
const THINKING_AFTER_MS = (audioConfig.instant_seconds ?? 2) * 1000;
// Sans réponse du professeur pendant ce temps, on rend la parole à l'apprenant.
const THINKING_MAX_MS = 12000;
// OpenAI : voix encore dans le tampon de gigue du navigateur après la fin de l'envoi, en secondes.
const STREAM_TAIL = 0.15;
// OpenAI : volume au-dessus duquel la voix reçue est « audible » (fin réelle de la lecture).
const AUDIBLE_LEVEL = 0.01;
// OpenAI : après un arrêt de la voix, la fin déjà envoyée reste muette ce temps, en secondes.
const STREAM_MUTE = 0.4;

const TURN_TEXTS = {
    speaking: `${teacherName} parle…`,
    listening: 'À vous de parler',
    thinking: WAIT.instant ?? 'Un instant…',
    paused: 'Conversation en pause',
    muted: 'Micro coupé',
    ending: 'Fin de la conversation…',
};

const MESSAGES = {
    micDenied: 'Vous avez refusé l\'accès au micro. Autorisez-le dans les réglages du navigateur (icône à gauche de l\'adresse du site), puis rechargez la page.',
    micMissing: 'Aucun micro n\'a été trouvé sur cet appareil. Branchez des écouteurs avec micro ou essayez depuis votre téléphone.',
    micInsecure: 'Le micro ne peut pas être utilisé sur cette page. Ouvrez le site dans Chrome, Firefox ou Safari à jour.',
    micOther: 'Le micro n\'a pas pu être activé. Fermez les autres applications qui l\'utilisent, puis réessayez.',
    network: 'La connexion n\'a pas abouti. Vérifiez votre connexion internet, puis réessayez.',
    cut: WAIT.cut,
    cutAgain: WAIT.cut_again,
    unavailable: WAIT.unavailable,
    reconnecting: WAIT.reconnecting,
    quota: `${teacherName} est très demandé en ce moment. Réessayez dans quelques minutes : votre temps non utilisé reste disponible.`,
    pauseTooLong: 'La pause a duré trop longtemps : la conversation est terminée. Le temps déjà utilisé est décompté.',
    slow: WAIT.slow,
    stuck: WAIT.stuck,
    nudged: WAIT.nudged,
    superseded: WAIT.superseded,
};

// Identifiant de CETTE page de séance : la dernière page connectée est celle de la séance (App\Support\Live\LivePage).
// Une autre page encore ouverte (autre onglet, autre téléphone) s'arrête : deux Teacher Joe ne se parlent plus.
const PAGE_ID = globalThis.crypto?.randomUUID?.() ?? `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
const pages = 'BroadcastChannel' in globalThis ? new BroadcastChannel('talkami-live') : null;

const state = {
    // Évaluation : niveau reçu, en attente d'être montré quand le professeur le dit (scheduleLevelReveal).
    pendingReveal: null,
    // Niveau montré (pour une vidéo lancée après le verdict).
    revealed: null,
    config: null,
    // Textes de la carte « La suite » selon le résultat (niveau trouvé, bilan réussi ou à repasser).
    next: null,
    // Adaptateur du fournisseur en cours (live-gemini.js ou live-openai.js).
    transport: null,
    // Une connexion a été prête au moins une fois (sinon : rien n'a commencé).
    everReady: false,
    // Une parole a été échangée : plus jamais de changement de fournisseur.
    spoke: false,
    // Changement de fournisseur déjà demandé (une seule fois, avant la première parole).
    switched: false,
    connected: false,
    ending: false,
    reconnecting: false,
    autoReconnects: 0,
    resumeHandle: null,
    deadline: 0,
    warningSent: false,
    tokens: new TokenTally(),
    // Gemini : réponse en cours (jusqu'à turnComplete), heure du dernier morceau de voix, accueil attendu.
    voicePending: false,
    // L'élève a déjà parlé au moins une fois (aide du premier tour de l'évaluation).
    learnerAnswered: false,
    // Ce que le professeur a dit depuis la dernière parole de l'élève (dernière question, pour le bandeau).
    teacherSaid: '',
    teacherTurnDone: false,
    lastChunkAt: 0,
    expectingVoiceUntil: 0,
    timers: [],
    audio: null,
    teacherLine: null,
    learnerLine: null,
    wakeLock: null,
    // Tour de parole et contrôles de l'apprenant.
    paused: false,
    pausedAt: 0,
    muted: false,
    gate: null,
    generating: false,
    discardTurn: false,
    awaitingSince: 0,
    learnerHeardAt: 0,
    // Fin d'évaluation (questions et phrase fixe) : réglée à la première réponse du serveur.
    end: new ConversationEnd(),
    // Séance du jour et « Mes cours » : annonce d'une minute, conclusion, fermeture (d'après le temps restant).
    closing: null,
    tokenExpiresAt: 0,
    cues: new CueTracker(),
    // Sous-titres de l'évaluation pas encore envoyés au serveur (repli si le temps finit sans verdict).
    transcript: '',
    transcriptSpeaker: null,
    // Derniers échanges (toutes les conversations) : contexte d'un nouvel appel OpenAI après une coupure.
    history: '',
    historySpeaker: null,
    // OpenAI : phrases de l'apprenant par élément de conversation (la transcription arrive après sa phrase).
    learnerLines: new Map(),
    voiceSource: null,
    lastAudibleAt: 0,
    // Texte de la conversation : une réplique par ligne de sous-titres.
    recorder: new TranscriptRecorder(),
    utterances: new WeakMap(),
    sendingTranscript: false,
    // Tours de parole : voix de l'apprenant mesurée par le niveau du micro (aucun audio gardé), délai de réponse.
    vad: new VoiceActivity(),
    turns: new TurnWatch({
        endOfTurnMs: (audioConfig.end_of_turn_seconds ?? 3) * 1000,
        noAnswerMs: (audioConfig.no_answer_seconds ?? 8) * 1000,
        stuckMs: (audioConfig.stuck_seconds ?? 20) * 1000,
    }),
    // Répliques de l'apprenant par tour (heure réelle et mesures connues à la réponse du professeur).
    turnLines: new Map(),
    // Voix du professeur en cours (premier son déjà mesuré) et réplique de cette réponse.
    teacherSounding: false,
    teacherSound: null,
    teacherUtterance: null,
    // Porte du micro : dernier état connu (événements ouvert / fermé).
    gateOpen: null,
    nudgedAt: 0,
};

/* ---------- Échanges avec le serveur Laravel ---------- */

async function post(url, body = {}) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Live-Page': PAGE_ID },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        const data = await response.json().catch(() => ({}));

        // La séance continue sur une autre page : celle-ci s'arrête, sans clore la séance.
        if (response.status === 409 && data.superseded) {
            superseded();
        }

        return { ok: response.ok, status: response.status, data };
    } catch {
        return { ok: false, status: 0, data: { message: MESSAGES.network } };
    }
}

function takeTokens() {
    return state.tokens.take();
}

/**
 * Sous-titres de l'évaluation depuis le dernier envoi (« Learner: … », « Teacher: … »).
 */
function takeTranscript() {
    if (mode !== 'assessment' || state.transcript === '') {
        return {};
    }

    const transcript = state.transcript.slice(0, 7900);
    state.transcript = state.transcript.slice(transcript.length);

    return { transcript };
}

function recordTranscript(speaker, text) {
    if (mode !== 'assessment') {
        return;
    }

    if (state.transcriptSpeaker !== speaker) {
        state.transcript += `\n${speaker === 'teacher' ? 'Teacher' : 'Learner'}: `;
        state.transcriptSpeaker = speaker;
    }

    state.transcript += text;
}

async function heartbeat() {
    if (state.ending) {
        return;
    }

    flushTranscript();

    const tokens = takeTokens();
    const transcript = takeTranscript();
    // Connexion avec le professeur coupée : le serveur met le temps de séance en pause.
    const link = state.everReady ? { connected: state.connected } : {};
    const result = await post(urls.signal, { ...tokens, ...transcript, ...link });

    if (!result.ok) {
        // Le signal sera renvoyé avec les jetons (et les sous-titres) au prochain passage.
        state.tokens.restore(tokens);
        state.transcript = (transcript.transcript ?? '') + state.transcript;

        return;
    }

    setRemaining(result.data.remaining_seconds);

    if (!result.data.active) {
        // Pendant les questions ou la conclusion, la phrase en cours peut finir (tick → timeUp).
        if (state.end.questions || state.closing) {
            setRemaining(0);
        } else {
            finish('time');
        }
    }
}

/* ---------- Interface ---------- */

function setStatus(text) {
    ui.status.textContent = text;
}

function showError(text) {
    ui.error.textContent = text;
    ui.error.hidden = !text;
}

function setRemaining(seconds) {
    state.deadline = Date.now() + Math.max(0, seconds) * 1000;
    renderTimer();
}

function remainingSeconds() {
    return Math.max(0, Math.round((state.deadline - Date.now()) / 1000));
}

function renderTimer() {
    const seconds = remainingSeconds();

    if (ui.timerLabel) {
        // Après le verdict de l'évaluation : le temps restant est celui du moment des questions.
        ui.timerLabel.textContent = state.end.questions && !state.pendingReveal ? 'Vos questions' : 'Temps restant';
    }

    ui.timer.textContent = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
    ui.timer.classList.toggle('timer-low', state.config !== null && seconds <= state.config.warning_seconds);
}

function makeLine(speaker) {
    const line = document.createElement('p');
    line.className = `line line-${speaker}`;
    line.dataset.speaker = speaker === 'teacher' ? 'Professeur' : 'Vous';
    // Heure de la réplique : quand sa ligne apparaît (la transcription peut arriver après la phrase).
    line.dataset.startedAt = String(Date.now());
    ui.subtitles.append(line);

    return line;
}

/**
 * Ligne de sous-titre : la réplique en cours, ou (OpenAI) la phrase de l'apprenant identifiée par son élément,
 * placée avant la réponse du professeur même si sa transcription arrive après.
 */
function lineFor(speaker, key = null) {
    if (speaker === 'learner' && key) {
        let line = state.learnerLines.get(key);

        if (!line) {
            line = makeLine('learner');
            line.hidden = true;
            state.learnerLines.set(key, line);

            while (state.learnerLines.size > 30) {
                state.learnerLines.delete(state.learnerLines.keys().next().value);
            }
        }

        return line;
    }

    const name = speaker === 'teacher' ? 'teacherLine' : 'learnerLine';

    if (!state[name]) {
        state[name] = makeLine(speaker);
    }

    return state[name];
}

function recordHistory(speaker, text) {
    if (state.historySpeaker !== speaker) {
        state.history += `\n${speaker === 'teacher' ? 'Teacher' : 'Learner'}: `;
        state.historySpeaker = speaker;
    }

    state.history = (state.history + text).slice(-4000);
}

function subtitle(speaker, text, key = null) {
    if (!text) {
        return;
    }

    const line = lineFor(speaker, key);
    line.hidden = false;
    line.textContent += text;
    recordUtterance(line, speaker, text);
    ui.subtitles.scrollTop = ui.subtitles.scrollHeight;
    recordTranscript(speaker, text);
    recordHistory(speaker, text);

    // Expression du professeur 3D au moment où il dit « Great answer! », « You said… », « Goodbye! »…
    if (speaker === 'teacher') {
        const lit = board?.tracker?.feed(text);

        if (lit) {
            board.view.light(lit);
        }

        const expression = state.cues.feed(text);

        if (expression) {
            teacher?.expression(expression);
        }
    }

    // On garde les 30 dernières répliques : la page reste légère.
    while (ui.subtitles.children.length > 30) {
        ui.subtitles.firstElementChild.remove();
    }
}

/**
 * La réplique de cette ligne de sous-titres (créée au premier mot), qui partira au serveur.
 */
function recordUtterance(line, speaker, text) {
    let utterance = state.utterances.get(line);

    if (!utterance) {
        utterance = state.recorder.start(speaker, Number(line.dataset.startedAt) || Date.now());
        state.utterances.set(line, utterance);
        placeUtterance(utterance, speaker);
    }

    state.recorder.append(utterance, text);
}

/**
 * Heure réelle d'une nouvelle réplique : début de la parole de l'apprenant (micro, sinon fournisseur), ou premier
 * son de la réponse du professeur ; les mesures du tour suivent quand elles sont connues.
 */
function placeUtterance(utterance, speaker) {
    if (speaker === 'learner') {
        const turn = state.turns.turnFor(Date.now());

        if (!turn) {
            return;
        }

        if (turn.start) {
            utterance.at = Math.min(utterance.at, turn.start);
        }

        if (turn.record) {
            applyLearnerRecord(utterance, turn.record);
        } else {
            state.turnLines.set(turn.id, [...(state.turnLines.get(turn.id) ?? []), utterance]);

            while (state.turnLines.size > 20) {
                state.turnLines.delete(state.turnLines.keys().next().value);
            }
        }

        return;
    }

    state.teacherUtterance = utterance;

    if (state.teacherSound) {
        utterance.sounded = true;
        state.recorder.update(utterance, state.teacherSound);
        state.teacherSound = null;
    }
}

function applyLearnerRecord(utterance, record) {
    const start = record.mic_start ?? record.provider_start;

    state.recorder.update(utterance, {
        at: start ? Math.min(utterance.at, start) : null,
        end: record.mic_end ?? record.provider_end,
        metrics: learnerMetrics(record),
    });
}

/**
 * Premier son d'une réponse de Teacher Joe (heure où il sort du haut-parleur) : le tour de l'apprenant se ferme,
 * son délai de réponse est calculé et rangé avec les deux répliques. Jamais bloquant.
 */
function teacherSoundStarted(at, audioAt = Date.now()) {
    if (state.teacherSounding) {
        return;
    }

    state.teacherSounding = true;
    hideWaitNote();
    state.nudgedAt = 0;
    const record = state.turns.teacherSound(at);

    if (record) {
        for (const utterance of state.turnLines.get(record.id) ?? []) {
            applyLearnerRecord(utterance, record);
        }

        state.turnLines.delete(record.id);
    }

    const sound = { at, metrics: teacherMetrics(record, audioAt) };

    // Le sous-titre est déjà là : il reçoit l'heure du son ; sinon, la prochaine réplique du professeur.
    if (state.teacherUtterance && !state.teacherUtterance.sounded) {
        state.teacherUtterance.sounded = true;
        state.recorder.update(state.teacherUtterance, sound);
    } else {
        state.teacherSound = sound;
    }
}

/** Fin de la réponse (tour fini, interruption, pause) : la prochaine voix sera une nouvelle réponse. */
function teacherResponseOver() {
    state.teacherSounding = false;
    state.teacherUtterance = null;
    state.teacherSound = null;
}

function logEvent(kind, detail = '') {
    state.recorder.event(kind, detail);
}

/**
 * Envoie un lot de répliques. Jamais bloquant : en cas d'échec (réseau), le lot repartira au prochain envoi ;
 * un refus du serveur (séance trop ancienne, envoi non valable) ne se répète pas.
 */
async function sendTranscript() {
    const url = state.config?.transcript_url;
    const batch = url ? state.recorder.takeBatch() : [];
    const events = url ? state.recorder.takeEvents() : [];

    if (batch.length === 0 && events.length === 0) {
        return false;
    }

    const body = {};

    if (batch.length > 0) {
        body.lines = TranscriptRecorder.payload(batch);
    }

    if (events.length > 0) {
        body.events = TranscriptRecorder.eventsPayload(events);
    }

    const result = await post(url, body);

    if (!result.ok && (result.status === 0 || result.status === 429 || result.status >= 500)) {
        state.recorder.restore(batch, events);

        return false;
    }

    return true;
}

/** Envoi régulier (à chaque signal), un lot à la fois. */
function flushTranscript() {
    if (state.sendingTranscript) {
        return;
    }

    state.sendingTranscript = true;
    sendTranscript().finally(() => { state.sendingTranscript = false; });
}

/** Dernier envoi, à la fin de la séance : tout ce qui reste. */
async function flushAllTranscript() {
    for (let i = 0; i < 20 && state.recorder.hasPending; i++) {
        if (!await sendTranscript()) {
            break;
        }
    }
}

/**
 * Séance du jour : bref bandeau sous le professeur quand la séance passe à un autre temps (pratique, conversation,
 * bilan). Visible sans faire défiler, annoncé aux lecteurs d'écran, jamais de bouton : c'est le professeur qui mène.
 */
let phaseNoteTimer = null;

function showPhaseNote(phase) {
    const text = phaseNote?.(phase, teacherName);
    const note = $('phase-note');

    if (!note || !text || state.ending) {
        return;
    }

    note.querySelector('[data-phase-note-text]').textContent = text;
    note.hidden = false;
    clearTimeout(phaseNoteTimer);
    phaseNoteTimer = setTimeout(() => { note.hidden = true; }, 10000);
}

/**
 * Fin de l'étape : la carte « La suite » (phrase courte + UN bouton orange) apparaît en haut de la colonne des
 * commandes, AVANT le résumé, et la page y descend doucement (saut direct si l'élève a demandé moins d'animations),
 * focus sur son titre. Jamais pendant que le professeur parle : seulement quand la conversation est finie.
 */
function showNext({ title, text, href, label } = {}) {
    const next = $('suite');

    if (!next) {
        ui.back.hidden = false;

        return;
    }

    if (title) {
        next.querySelector('h2').textContent = title;
    }

    if (text) {
        next.querySelector('.next-step-text').textContent = text;
    }

    const button = next.querySelector('.btn-next');

    if (href) {
        button.href = href;
    }

    if (label) {
        button.querySelector('[data-next-label]').textContent = label;
    }

    next.hidden = false;
    $('phase-note')?.setAttribute('hidden', '');
    placeRecorder();
    window.goTo ? window.goTo(next) : next.scrollIntoView();
}

function showOutcome(html) {
    ui.outcome.innerHTML = html;
    ui.outcome.hidden = false;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';

    return div.innerHTML;
}

/* ---------- Audio : micro, lecture et bouche ---------- */

async function setupAudio() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.AudioWorkletNode) {
        throw Object.assign(new Error('insecure'), { name: 'InsecureContext' });
    }

    const stream = await openMicrophone(savedMicrophone(), false);

    const { context, source } = createAudioContext(stream);
    await context.audioWorklet.addModule(root.dataset.worklet);
    console.debug('Audio : contexte à', context.sampleRate, 'Hz, latence de sortie', context.outputLatency ?? context.baseLatency, 'seconde(s)');

    // Bus du micro : la capture, le témoin et le professeur 3D y restent branchés
    // quand l'apprenant change de micro (seule la source change).
    const micBus = context.createGain();
    source.connect(micBus);
    const micAnalyser = context.createAnalyser();
    micAnalyser.fftSize = 1024;
    micBus.connect(micAnalyser);

    const capture = new AudioWorkletNode(context, 'pcm-capture', { processorOptions: { targetRate: INPUT_RATE } });
    const silent = context.createGain();
    silent.gain.value = 0;
    micBus.connect(capture).connect(silent).connect(context.destination);
    capture.port.onmessage = (event) => sendAudio(event.data);

    // La voix est un peu retardée : la lecture labiale du professeur 3D
    // (qui écoute avant ce retard) a le temps de préparer la forme de la bouche.
    const output = context.createGain();
    // Fondu de la voix (arrêt sans clic), puis volume (GEMINI_VOICE_GAIN) et limiteur :
    // plus fort, sans jamais saturer.
    const voice = context.createGain();
    const boost = context.createGain();
    boost.gain.value = audioConfig.voice_gain;
    const limiter = context.createDynamicsCompressor();
    limiter.threshold.value = -3;
    limiter.knee.value = 0;
    limiter.ratio.value = 20;
    limiter.attack.value = 0.002;
    limiter.release.value = 0.15;
    voice.connect(boost).connect(limiter).connect(output);
    // Volume de la voix reçue (OpenAI) : fin réelle de la lecture, en plus de la fin de l'envoi.
    const voiceAnalyser = context.createAnalyser();
    voiceAnalyser.fftSize = 512;
    voice.connect(voiceAnalyser);
    const delay = context.createDelay(0.5);
    delay.delayTime.value = OUTPUT_DELAY;
    const analyser = context.createAnalyser();
    analyser.fftSize = 512;
    output.connect(delay).connect(analyser).connect(context.destination);

    state.audio = {
        context, stream, source, micBus, micAnalyser, output, voice, analyser, voiceAnalyser,
        micSamples: new Float32Array(micAnalyser.fftSize),
        voiceSamples: new Float32Array(voiceAnalyser.fftSize),
        silence: new SilenceWatch({ seconds: audioConfig.silent_mic_seconds }),
        scheduler: new VoiceScheduler({ sampleRate: context.sampleRate }),
        // Contexte à une autre fréquence que la voix : rééchantillonnage continu par nos soins.
        resampler: context.sampleRate === VOICE_RATE ? null : new StreamResampler(VOICE_RATE, context.sampleRate),
        sources: new Set(),
    };
    teacher?.attachAudio(context, output, micBus);
    animateMouth();
    refreshMicrophones();
    navigator.mediaDevices.addEventListener?.('devicechange', refreshMicrophones);
}

/* ---------- Choix du micro (Bluetooth, écouteurs…) ---------- */

function savedMicrophone() {
    try {
        return localStorage.getItem(MIC_PREFERENCE);
    } catch {
        return null;
    }
}

function saveMicrophone(id) {
    try {
        localStorage.setItem(MIC_PREFERENCE, id);
    } catch { /* stockage indisponible : le choix vaut pour cette page */ }
}

// Annulation d'écho : coupée par défaut sur Android (elle y passe le téléphone en mode appel, voix bien plus basse).
const ECHO_CANCELLATION = /Android/i.test(navigator.userAgent ?? '')
    ? audioConfig.echo_cancellation_android ?? false
    : audioConfig.echo_cancellation;

/**
 * Ouvre le micro. L'annulation d'écho est réglable (config platform.live.echo_cancellation, et
 * echo_cancellation_android sur Android) ;
 * réduction de bruit et gain automatique toujours demandés. Le micro choisi est
 * « préféré » au démarrage (s'il a disparu, un autre est pris) et « exigé » quand
 * l'apprenant le choisit dans la liste.
 */
async function openMicrophone(deviceId, exact) {
    const audio = {
        channelCount: 1,
        echoCancellation: ECHO_CANCELLATION,
        noiseSuppression: true,
        autoGainControl: true,
    };

    if (deviceId) {
        audio.deviceId = exact ? { exact: deviceId } : { ideal: deviceId };
    }

    const stream = await navigator.mediaDevices.getUserMedia({ audio });
    const settings = stream.getAudioTracks()[0]?.getSettings?.() ?? {};
    console.debug('Micro : réglages obtenus', settings);

    if (ECHO_CANCELLATION && settings.echoCancellation === false) {
        console.warn('Micro : annulation d\'écho indisponible, le demi-duplex reste seul actif.');
    }

    return stream;
}

/**
 * Liste « Micro utilisé », affichée seulement s'il y a plusieurs micros.
 */
async function refreshMicrophones() {
    if (!state.audio || !navigator.mediaDevices.enumerateDevices) {
        return;
    }

    const microphones = listMicrophones(await navigator.mediaDevices.enumerateDevices());

    // La conversation a pu se terminer pendant l'attente de la liste des micros.
    if (!state.audio) {
        return;
    }

    const currentId = state.audio.stream.getAudioTracks()[0]?.getSettings?.().deviceId;

    ui.micSelect.replaceChildren(...microphones.map(({ id, label }) => new Option(label, id, false, id === currentId)));
    ui.micChoice.hidden = microphones.length < 2;
}

/**
 * Change de micro sans couper la conversation : seule la source du bus change.
 * La porte du micro (demi-duplex) agit sur ce qui sort du bus : elle n'est pas touchée.
 */
async function switchMicrophone(id) {
    if (!state.audio || state.ending) {
        return;
    }

    let stream;

    try {
        stream = await openMicrophone(id, true);
    } catch (error) {
        console.warn('Micro indisponible', error);
        showError('Ce micro ne peut pas être utilisé. Choisissez-en un autre.');
        refreshMicrophones();

        return;
    }

    const { context, micBus } = state.audio;
    const old = state.audio.stream;
    const source = context.createMediaStreamSource(stream);

    state.audio.source.disconnect();
    source.connect(micBus);
    state.audio.source = source;
    state.audio.stream = stream;
    stream.getAudioTracks().forEach((track) => { track.enabled = !state.muted; });
    old.getTracks().forEach((track) => track.stop());

    saveMicrophone(id);
    showError('');
    ui.micWarning.hidden = true;
    state.audio.silence.quietSince = null;
    refreshMicrophones();
}

/**
 * Contexte audio réglé à la fréquence de la voix de Gemini (24 kHz) : aucun
 * rééchantillonnage morceau par morceau ; le navigateur convertit d'un seul
 * flux continu vers le haut-parleur, et le micro de même. « playback » : tampon
 * de sortie plus large, moins de craquements quand le téléphone est occupé (3D).
 * Repli sur la fréquence du téléphone si le navigateur refuse.
 */
function createAudioContext(stream) {
    for (const options of [{ sampleRate: VOICE_RATE, latencyHint: 'playback' }, { latencyHint: 'playback' }]) {
        let context = null;

        try {
            context = new AudioContext(options);

            return { context, source: context.createMediaStreamSource(stream) };
        } catch (error) {
            console.warn('Audio : réglage refusé', options, error);
            context?.close();
        }
    }

    const context = new AudioContext();

    return { context, source: context.createMediaStreamSource(stream) };
}

/**
 * Instant (horloge audio) où le haut-parleur se tait vraiment : fin programmée
 * du dernier tampon de voix, retard ajouté et latence de sortie de l'appareil.
 */
function voiceEnd() {
    const { context, scheduler } = state.audio;
    const latency = context.outputLatency || context.baseLatency || 0;

    // OpenAI (piste WebRTC) : la voix parle de « started » à « stopped », puis le tampon de gigue du
    // navigateur se vide ; on prend la plus tardive des deux fins (envoi terminé, dernier son audible).
    if (state.transport?.streamsAudio) {
        // Sécurité : fin de voix annoncée mais perdue (plus rien d'audible depuis quelques secondes) : la porte
        // du micro se rouvre quand même, l'apprenant ne parle jamais dans le vide.
        if (state.transport.speaking && voiceLooksOver({
            now: context.currentTime,
            speakingSince: state.transport.speakingSince,
            lastAudibleAt: state.lastAudibleAt,
            after: audioConfig.voice_stuck_seconds ?? 4,
        })) {
            state.transport.voiceEnded(Math.max(state.lastAudibleAt, state.transport.speakingSince));
            logEvent('gate_safety', 'fin de la voix non reçue');
        }

        if (state.transport.speaking) {
            return context.currentTime + 1;
        }

        return voiceEndsAt(Math.max(state.transport.stoppedAt + STREAM_TAIL, state.lastAudibleAt), OUTPUT_DELAY, latency);
    }

    // Gemini : réponse pas encore finie ou accueil attendu, la porte du micro reste fermée (mic-gate.js).
    return heldVoiceEnd({
        scheduledEnd: voiceEndsAt(scheduler.endTime, OUTPUT_DELAY, latency),
        now: context.currentTime,
        pending: state.voicePending,
        lastChunkAt: state.lastChunkAt,
        hold: audioConfig.voice_gap_hold_seconds ?? 3,
        expectingUntil: state.expectingVoiceUntil,
    });
}

/**
 * Vrai tant que la voix du professeur sort du haut-parleur.
 */
function teacherSpeaking() {
    return state.audio !== null && state.audio.context.currentTime < voiceEnd();
}

/**
 * Vrai pendant la voix du professeur et le court moment qui la suit (echo_guard_ms) : sa voix peut encore revenir
 * par le haut-parleur.
 */
function teacherAudible() {
    if (state.audio === null) {
        return false;
    }

    const now = state.audio.context.currentTime;

    return state.gate ? !state.gate.isOpen(now, voiceEnd(), false) : now < voiceEnd();
}

/**
 * Chaque paquet du micro passe par la porte : envoyé seulement micro ouvert
 * (ni voix du professeur, ni echo_guard_ms après, ni pause, ni micro coupé).
 * Micro fermé plus d'une seconde (tout de suite en pause ou micro coupé) :
 * « audioStreamEnd », comme le demande Google ; le flux reprend au paquet suivant.
 */
function sendAudio(buffer) {
    const transport = state.transport;

    if (!state.connected || state.ending || !state.audio || !state.gate || !transport?.isOpen) {
        return;
    }

    // Après le verdict, micro fermé, sauf pendant les questions libres de l'évaluation (jusqu'à la conclusion).
    // Fin de tour signalée par le navigateur : micro fermé tant que l'apprenant ne reparle pas.
    const endingTurn = state.turns.endingTurn;

    // Gemini : la fin de tour signalée par le navigateur part en vrai silence (mic-gate.js, sendsTurnSilence), puis
    // audioStreamEnd. Rien d'autre n'est envoyé au professeur : aucune indication, aucune relance.
    if (endingTurn && !transport.streamsAudio && !state.paused && !state.muted && micOpen()
        && sendsTurnSilence({ now: Date.now(), forcedAt: state.turns.turn?.forcedAt ?? null, seconds: audioConfig.end_of_turn_silence_seconds ?? 3 })) {
        noteGate(false, true);

        if (buffer) {
            transport.sendAudio(new ArrayBuffer(buffer.byteLength));
        }

        return;
    }

    const action = state.gate.decide(state.audio.context.currentTime, voiceEnd(), state.paused || state.muted || !micOpen() || endingTurn);
    noteGate(action === 'send', endingTurn);

    // OpenAI : le son part par la piste WebRTC ; la même porte l'ouvre ou la ferme.
    if (transport.streamsAudio) {
        transport.setMicOpen(action === 'send');

        return;
    }

    if (action === 'send' && buffer) {
        transport.sendAudio(buffer);
    } else if (action === 'end') {
        transport.endAudio();
    }
}

/**
 * Ouverture et fermeture de la porte du micro, gardées comme événements de la séance (avec la raison).
 */
function noteGate(open, endingTurn) {
    if (state.gateOpen === open) {
        return;
    }

    state.gateOpen = open;
    recording.recorder?.learnerOpen(open);

    if (open) {
        logEvent('gate_opened');

        return;
    }

    const reason = state.paused ? 'pause' : state.muted ? 'micro coupé'
        : !micOpen() ? 'fin de la conversation' : endingTurn ? 'fin de tour' : 'voix du professeur';
    logEvent('gate_closed', reason);
}

/**
 * Pause ou micro coupé : fermeture immédiate du flux, sans attendre le prochain paquet.
 */
/** Micro ouvert : avant le verdict (ou pendant les questions), et jusqu'à la phrase de fin d'une séance. */
function micOpen() {
    return state.end.micOpen && (state.closing?.micOpen ?? true);
}

function endMicStream() {
    sendAudio(null);
}

function playAudio(base64) {
    // En pause, ou après « Interrompre le professeur » : la fin de sa réplique n'est pas jouée.
    if (!state.audio || state.paused || state.discardTurn) {
        return;
    }

    state.awaitingSince = 0;
    state.learnerHeardAt = 0;
    state.closing?.heard(clock());
    const { context, voice, sources, scheduler, resampler } = state.audio;
    // Réponse en cours : la porte du micro reste fermée jusqu'à sa fin, même si le son reçu s'épuise un instant.
    state.voicePending = true;
    state.lastChunkAt = context.currentTime;
    state.expectingVoiceUntil = 0;
    const continuing = context.currentTime < scheduler.endTime + 0.5;
    let samples = decodePcm16(atob(base64));

    // Début d'une prise de parole (ou trou du réseau) : le rééchantillonnage repart du silence.
    const gap = scheduler.isGap(context.currentTime);

    if (resampler) {
        if (gap) {
            resampler.reset();
        }

        samples = resampler.process(samples);
    }

    if (samples.length === 0) {
        return;
    }

    if (gap) {
        fadeIn(samples, Math.round(context.sampleRate * 0.005));
    }

    const buffer = context.createBuffer(1, samples.length, context.sampleRate);
    buffer.getChannelData(0).set(samples);

    const node = context.createBufferSource();
    node.buffer = buffer;
    node.connect(voice);

    const { time } = scheduler.schedule(context.currentTime, samples.length, continuing && gap);
    node.start(time);

    // Premier son de la réponse : heure où il sortira du haut-parleur (programmation, retard, latence).
    if (!state.teacherSounding) {
        const latency = context.outputLatency || context.baseLatency || 0;
        teacherSoundStarted(Date.now() + (time - context.currentTime + OUTPUT_DELAY + latency) * 1000);
    }
    sources.add(node);
    node.onended = () => sources.delete(node);
}

/**
 * OpenAI : voix reçue sur une piste WebRTC, branchée sur la même chaîne que Gemini (fondu, volume,
 * limiteur, retard pour la lecture labiale, professeur 3D).
 */
function attachVoiceStream(stream) {
    if (!state.audio) {
        return;
    }

    const { context, voice } = state.audio;

    try { state.voiceSource?.disconnect(); } catch { /* déjà débranché */ }

    state.voiceSource = context.createMediaStreamSource(stream);
    state.voiceSource.connect(voice);
}

/** OpenAI : voix de nouveau audible (après une pause ou une interruption). */
function unmuteVoiceStream() {
    if (!state.audio || !state.transport?.streamsAudio || state.paused) {
        return;
    }

    const { context, voice } = state.audio;
    const now = context.currentTime;

    voice.gain.cancelScheduledValues(now);
    voice.gain.setValueAtTime(voice.gain.value, now);
    voice.gain.linearRampToValueAtTime(1, now + 0.005);
}

function stopPlayback() {
    if (!state.audio) {
        return;
    }

    // Fondu de quelques millisecondes plutôt qu'une coupure nette (clic).
    const { context, voice, sources, scheduler } = state.audio;
    const now = context.currentTime;
    const end = now + STOP_FADE;

    voice.gain.cancelScheduledValues(now);
    voice.gain.setValueAtTime(voice.gain.value, now);
    voice.gain.linearRampToValueAtTime(0, end);

    // OpenAI : OpenAI efface la voix pas encore envoyée ; ce qui est déjà dans le tampon reste muet un instant
    // (en pause : jusqu'à la reprise).
    if (state.transport?.streamsAudio) {
        if (!state.paused) {
            voice.gain.setValueAtTime(1, end + STREAM_MUTE);
        }

        return;
    }

    voice.gain.setValueAtTime(1, end + 0.005);

    sources.forEach((node) => {
        try { node.stop(end); } catch { /* déjà arrêté */ }
    });
    sources.clear();
    scheduler.stop(end + 0.005);
}

function animateMouth() {
    const { analyser } = state.audio;
    const samples = new Uint8Array(analyser.fftSize);
    let opening = 0;

    const frame = () => {
        if (!state.audio) {
            ui.mouth.setAttribute('ry', 3);

            return;
        }

        analyser.getByteTimeDomainData(samples);
        let sum = 0;

        for (const value of samples) {
            const centered = (value - 128) / 128;
            sum += centered * centered;
        }

        const volume = Math.min(1, Math.sqrt(sum / samples.length) * 5);
        trackAudibleVoice();
        opening = opening * 0.6 + volume * 0.4;
        ui.mouth.setAttribute('ry', (3 + opening * 17).toFixed(1));
        updateMicMeter();
        requestAnimationFrame(frame);
    };

    requestAnimationFrame(frame);
}

/**
 * OpenAI : dernier instant où la voix reçue était audible (avant le retard de la lecture labiale).
 */
function trackAudibleVoice() {
    const { voiceAnalyser, voiceSamples, context } = state.audio;

    if (!state.transport?.streamsAudio) {
        return;
    }

    voiceAnalyser.getFloatTimeDomainData(voiceSamples);

    if (rms(voiceSamples) > AUDIBLE_LEVEL) {
        state.lastAudibleAt = context.currentTime;
    }
}

/**
 * Témoin du micro et alerte « Nous ne vous entendons pas » (8 secondes sans aucun
 * son pendant le tour de l'apprenant).
 */
function updateMicMeter() {
    const { micAnalyser, micSamples, silence, context } = state.audio;
    micAnalyser.getFloatTimeDomainData(micSamples);
    const level = state.muted ? 0 : rms(micSamples);

    ui.micLevel.style.transform = `scaleX(${meterLevel(level).toFixed(3)})`;

    const listening = state.connected && !state.ending && ui.turn.dataset.turn === 'listening';
    const warn = silence.update(context.currentTime, level, listening);

    if (ui.micWarning.hidden === warn) {
        ui.micWarning.hidden = !warn;

        if (warn) {
            logEvent('mic_silent');
        }
    }

    trackLearnerVoice(level);
}

/**
 * Voix de l'apprenant mesurée dans le navigateur (niveau du micro seulement), quand c'est à lui de parler :
 * jamais pendant la voix du professeur (elle revient par le haut-parleur), en pause ou micro coupé.
 */
function trackLearnerVoice(level) {
    const now = Date.now();
    // Ni pendant sa voix, ni juste après (echo_guard_ms, comme la porte du micro) : la fin de sa voix qui revient
    // par le haut-parleur n'est jamais prise pour une parole de l'apprenant (fin de tour signalée à tort).
    const canSpeak = state.connected && !state.ending && !state.paused && !state.muted && micOpen() && !teacherAudible();
    const change = canSpeak ? state.vad.update(now, level) : state.vad.reset(now);

    if (!change) {
        return;
    }

    if (change.type === 'start') {
        state.turns.micVoice(true, change.at);
        // L'apprenant parle : la réponse précédente du professeur est finie.
        teacherResponseOver();
        hideWaitNote();
    } else {
        state.turns.micVoice(false, change.at);
    }
}

/**
 * Fin de tour de secours et attente d'une réponse (appelé chaque quart de seconde).
 */
function watchTurn() {
    if (!state.connected || state.ending || state.paused) {
        return;
    }

    const now = Date.now();

    // Le fournisseur n'a pas vu la fin de la parole (bruit de fond, longue réponse) : le navigateur la signale.
    if (state.turns.shouldEndTurn(now)) {
        state.turns.markEndOfTurn(now);
        logEvent('end_of_turn');
        endMicStream();
    }

    const waiting = state.turns.waiting(now);

    if (waiting === 'thinking' && state.turns.warnOnce('thinking')) {
        showWaitNote(MESSAGES.slow, false);
        logEvent('no_answer', `${audioConfig.no_answer_seconds ?? 8} secondes`);
    } else if (waiting === 'stuck' && !state.nudgedAt && state.turns.warnOnce('stuck')) {
        showWaitNote(MESSAGES.stuck, true);
        logEvent('no_answer', `${audioConfig.stuck_seconds ?? 20} secondes`);
    } else if (state.nudgedAt && now - state.nudgedAt > (audioConfig.stuck_seconds ?? 20) * 1000) {
        // Relancé, toujours rien : le bouton revient.
        state.nudgedAt = 0;
        showWaitNote(MESSAGES.stuck, true);
    }
}

function showWaitNote(text, withButton) {
    ui.waitText.textContent = text;
    ui.nudge.hidden = !withButton;
    ui.waitNote.hidden = false;
}

function hideWaitNote() {
    if (ui.waitNote && !ui.waitNote.hidden) {
        ui.waitNote.hidden = true;
        ui.nudge.hidden = true;
    }
}

/** « Relancer Teacher Joe » : il reçoit l'indication de répondre à ce que l'apprenant vient de dire. */
function nudgeTeacher() {
    state.nudgedAt = Date.now();
    showWaitNote(MESSAGES.nudged, false);
    logEvent('nudge');
    sendText(state.config?.cues?.nudge);
}

/* ---------- Connexion au fournisseur (Gemini ou OpenAI) ---------- */

/**
 * @param {boolean} planned  renouvellement prévu du jeton (Gemini) : la connexion actuelle continue en attendant
 * @param {?string} failed   fournisseur auquel on n'a pas pu se connecter avant la première parole : le serveur
 *                           commence alors la séance chez le suivant (une seule fois)
 */
async function connect({ planned = false, failed = null } = {}) {
    setStatus(planned ? 'En conversation' : `${teacherName} arrive…`);

    const result = await post(urls.connect, { handle: state.resumeHandle, failed });

    if (!result.ok) {
        if (planned && state.connected) {
            return; // La connexion actuelle continue jusqu'à l'expiration de son jeton.
        }

        // Aucun fournisseur ne répond : message clair et WhatsApp, jamais le nom d'un fournisseur.
        const unavailable = result.status === 503 && MESSAGES.unavailable;
        const message = unavailable ? MESSAGES.unavailable : (result.data.quota ? MESSAGES.quota : (result.data.message || MESSAGES.network));
        document.getElementById('whatsapp-help')?.toggleAttribute('hidden', !unavailable);

        if (result.status === 409 || result.status === 403) {
            finish('refused', message);
        } else {
            connectionFailed(message);
        }

        return;
    }

    const isResume = state.everReady;
    // Le fournisseur a refusé la reconnexion : la même séance continue chez le suivant.
    const switched = isResume && state.config?.provider !== undefined && result.data.provider !== state.config.provider;
    state.config = result.data;
    document.getElementById('whatsapp-help')?.setAttribute('hidden', '');
    // Les autres onglets de ce navigateur qui ont une séance ouverte s'arrêtent tout de suite.
    pages?.postMessage({ claim: PAGE_ID });

    if (switched) {
        state.resumeHandle = null;
        logEvent('reconnect', `bascule vers ${result.data.provider}`);
        setStatus(MESSAGES.reconnecting ?? 'Reconnexion…');
    }

    if (board && state.config.phases) {
        board.tracker ??= new BoardTracker(board.view.items());
        board.tracker.configure(state.config.phases);
    }
    state.tokenExpiresAt = Date.now() + state.config.token_expires_in * 1000;

    if (!isResume && state.config.closing_phrase) {
        state.end = new ConversationEnd({
            closingPhrase: state.config.closing_phrase,
            cueSeconds: state.config.conclusion_cue_seconds,
            grace: state.config.closing_grace_seconds,
            silentEnd: state.config.silent_end_seconds ?? 0,
        });
    }

    if (!isResume && state.config.session_end) {
        const end = state.config.session_end;
        state.closing = new SessionClosing({
            total: end.total,
            announceSeconds: end.announce_seconds,
            concludeSeconds: end.conclude_seconds,
            latestSeconds: end.latest_seconds,
            grace: end.grace_seconds,
            overtime: end.overtime_seconds,
            silentEnd: end.silent_end_seconds,
            closingPhrase: end.closing_phrase,
        });
    }

    // Page rechargée pendant les questions : le verdict est déjà enregistré.
    if (state.config.questions && !state.end.hasVerdict) {
        state.end.recordVerdict(clock(), { questions: true });
    }

    setRemaining(state.config.remaining_seconds);

    const transport = createTransport(state.config);
    transport.handlers = handlersFor(transport, { planned, isResume, switched });

    if (transport.streamsAudio) {
        try {
            await transport.connect();
        } catch (error) {
            console.warn(`Connexion à ${transport.provider} impossible :`, error);
            transport.handlers.onClose({ reason: error.message ?? '', ready: false, message: error.serverMessage, quota: error.quota });
        }
    } else {
        transport.connect();
    }
}

/**
 * Première connexion impossible : rien n'a commencé, on peut réessayer. En cours de séance : bouton
 * « Reprendre la séance », le temps de séance est en pause pendant la coupure.
 */
function connectionFailed(message) {
    if (!state.everReady) {
        setStatus('Pas disponible pour le moment');
        showError(message);
        setPhase('intro');
        ui.intro.hidden = false;
        ui.subtitles.hidden = true;
        ui.start.disabled = false;
    } else {
        setStatus('Connexion coupée');
        showError(`${message} ${MESSAGES.cutAgain}`);
        ui.resume.hidden = false;
    }
}

function createTransport(config) {
    if (config.provider === 'openai') {
        return new OpenAiTransport(config, {}, { post, context: state.audio?.context, micBus: state.audio?.micBus });
    }

    return new GeminiTransport(config, {}, { inputRate: INPUT_RATE });
}

/**
 * Ce que l'écran fait de chaque événement d'un fournisseur. Les événements d'une ancienne connexion
 * (renouvellement, reconnexion) sont ignorés.
 */
function handlersFor(transport, { planned, isResume, switched = false }) {
    const current = () => transport === state.transport;

    return {
        onReady() {
            logEvent('connected', `${transport.provider}${isResume ? (planned ? ', renouvellement' : ', reprise') : ''}`);
            const previous = state.transport;
            state.transport = transport;
            state.connected = true;
            state.reconnecting = false;
            state.everReady = true;

            if (previous && previous !== transport) {
                previous.close();
            }

            setStatus('En conversation');
            showError('');
            setPhase('live');
            ui.intro.hidden = true;
            ui.subtitles.hidden = false;
            ui.resume.hidden = true;
            ui.stop.hidden = false;
            ui.controls.hidden = false;
            ui.turn.hidden = false;
            ui.micPanel.hidden = false;
            state.generating = false;
            state.discardTurn = false;
            // Nouvelle connexion : flux du micro remis à zéro.
            state.gate = new MicGate({ guard: (state.config.echo_guard_ms ?? 400) / 1000 });

            // Nouvel appel OpenAI après une coupure (OpenAI ne garde rien), ou séance reprise chez un autre
            // fournisseur : on lui redonne les derniers échanges.
            if (isResume && !planned && (transport.streamsAudio || switched) && state.history.trim() !== '') {
                transport.sendContext('CONVERSATION SO FAR (context only, the connection was cut; do not greet again and do not repeat it):'
                    + `${state.history}\n${state.end.hasVerdict ? 'The result has ALREADY been saved: never call the function again.' : ''}`);
            }

            // Un seul accueil : à la première connexion. En pause, la reprise
            // de la pause suffira (inutile de faire parler le professeur).
            if (!planned && !(isResume && state.paused)) {
                cue(isResume ? resumeCue('resume') : state.config.cues.start);
                // Accueil ou reprise demandés : micro fermé jusqu'à sa première parole (un bruit l'interrompait).
                if (state.audio) {
                    state.expectingVoiceUntil = state.audio.context.currentTime + (audioConfig.greeting_wait_seconds ?? 5);
                }
            }

            if (!isResume && !planned) {
                teacher?.expression('welcome');
            }

            scheduleRenewal();
        },

        onAudio(base64) {
            if (current()) {
                state.spoke = true;
                playAudio(base64);
            }
        },

        onRemoteStream(stream) {
            attachVoiceStream(stream);
        },

        onSpeechStarted() {
            if (!current()) {
                return;
            }

            state.spoke = true;

            // En pause ou après « Interrompre » : cette voix n'est pas jouée.
            if (state.paused || state.discardTurn) {
                transport.cancel();

                return;
            }

            state.awaitingSince = 0;
            state.learnerHeardAt = 0;
            unmuteVoiceStream();
            // Premier son : début de l'envoi par OpenAI, plus le retard et la latence de sortie.
            const context = state.audio?.context;
            const latency = context ? (context.outputLatency || context.baseLatency || 0) : 0;
            teacherSoundStarted(Date.now() + (OUTPUT_DELAY + latency) * 1000);
            renderTurn();
        },

        onGenerating() {
            if (!current()) {
                return;
            }

            state.generating = true;
            state.turns.providerResponding(Date.now());

            // OpenAI peut répondre à une phrase coupée par la pause : réponse annulée.
            if (state.paused) {
                transport.cancel();
            }
        },

        onLearnerStarted() {
            if (current()) {
                state.turns.providerStart(Date.now());
                teacher?.learnerSpoke();
            }
        },

        onVoiceActivity(started) {
            if (!current()) {
                return;
            }

            if (started) {
                state.turns.providerStart(Date.now());
            } else {
                state.turns.providerEnd(Date.now());
            }
        },

        onProviderError(detail) {
            if (current()) {
                logEvent('provider_error', `${transport.provider} : ${detail}`);
            }
        },

        onLearnerStopped(key) {
            if (!current()) {
                return;
            }

            state.turns.providerEnd(Date.now());

            // La phrase de l'apprenant prend sa place avant la réponse du professeur.
            if (key) {
                lineFor('learner', key);
            }

            state.teacherLine = null;
            teacherResponseOver();
            state.cues.reset();
            state.awaitingSince = Date.now();
            renderTurn();
        },

        onLearnerText(text, key) {
            text = cleanSpeech(text);

            if (!current() || text.trim() === '') {
                return;
            }

            state.spoke = true;
            state.learnerAnswered = true;
            state.teacherSaid = '';
            teacher?.learnerSpoke();
            state.teacherLine = null;
            state.teacherUtterance = null;
            state.cues.reset();
            // Sous-titre arrivé en retard, pendant la réponse du professeur : ce n'est plus son tour.
            state.learnerHeardAt = state.generating || key ? 0 : Date.now();
            subtitle('learner', text, key);
            renderTurn();
        },

        onTeacherText(text) {
            text = cleanSpeech(text);

            // Ce qui n'est pas joué (pause, interruption) n'apparaît pas non plus en sous-titre.
            if (!current() || !text.trim() || state.paused || state.discardTurn) {
                return;
            }

            state.spoke = true;
            state.learnerLine = null;

            // Nouvelle réponse du professeur : le bandeau suivra sa nouvelle question.
            if (state.teacherTurnDone) {
                state.teacherSaid = '';
                state.teacherTurnDone = false;
            }

            state.teacherSaid = (state.teacherSaid + text).slice(-1500);

            // Annonce du niveau : son texte arrive avant sa voix ; les sous-titres attendent le niveau.
            if (state.pendingReveal) {
                state.pendingReveal.held.push(text);
                listenForLevel(text);
            } else {
                subtitle('teacher', text);
            }

            // Phrase fixe de la conclusion : micro fermé, coupure une fois la voix terminée.
            if (state.end.teacherSaid(text, clock()) || state.closing?.teacherSaid(text, clock())) {
                endMicStream();
            }

            renderTurn();
        },

        onInterrupted() {
            if (current()) {
                state.voicePending = false;
                teacherResponseOver();
                stopPlayback();
                state.generating = false;
                state.discardTurn = false;
                renderTurn();
            }
        },

        onTurnComplete() {
            if (!current()) {
                return;
            }

            state.end.turnComplete(clock());
            state.closing?.turnComplete(clock());
            state.teacherTurnDone = true;
            state.voicePending = false;
            state.cues.reset();
            state.teacherLine = null;
            teacherResponseOver();
            state.learnerLine = null;
            state.generating = false;
            state.discardTurn = false;
            state.awaitingSince = 0;
            renderTurn();
        },

        onFunctionCall(call) {
            if (current()) {
                // Le professeur agit (bilan de la séance) : ce n'est pas un silence de fin.
                state.closing?.heard(clock());
                handleFunctionCall(call);
                state.awaitingSince = Date.now();
            }
        },

        onUsage(input, output, report) {
            state.tokens.add(report
                ? (transport.provider === 'openai' ? openAiUsage(report) : geminiUsage(report))
                : { input, output });
        },

        onResumeHandle(handle) {
            state.resumeHandle = handle;
        },

        onGoAway() {
            if (current()) {
                renew();
            }
        },

        onClose(info) {
            if (current()) {
                onClose(info);

                return;
            }

            // Échec d'un renouvellement prévu : l'ancienne connexion continue, rien à faire.
            if (info.ready || (planned && state.connected)) {
                return;
            }

            // Connexion impossible avant la première parole : la séance commence chez l'autre fournisseur
            // (le serveur décide ; une seule fois, jamais en cours de séance).
            if (!state.spoke && !state.switched && !planned && !state.ending) {
                state.switched = true;
                setStatus(`${teacherName} arrive…`);
                connect({ failed: transport.provider });

                return;
            }

            if (info.message) {
                connectionFailed(info.quota ? MESSAGES.quota : info.message);

                return;
            }

            onClose(info);
        },
    };
}

function sendText(text) {
    if (state.transport?.isOpen && text) {
        state.transport.sendText(text);
    }
}

/**
 * Indication au professeur, qui va répondre : « Un instant… » en attendant sa voix.
 */
function cue(text) {
    // Après le verdict, aucune indication : elle relançait l'annonce du niveau et une question.
    if (!state.end.acceptsCues) {
        return;
    }

    sendText(text);
    state.awaitingSince = Date.now();
    renderTurn();
}

/**
 * Le jeton (et la connexion) dure quelques minutes : on en demande un nouveau
 * juste avant son expiration, en reprenant la même conversation.
 */
function scheduleRenewal() {
    clearTimeout(state.renewal);
    // Recalculé aussi après le verdict : la fin autorisée a bougé (moment des questions).
    const left = (state.tokenExpiresAt - Date.now()) / 1000;

    if (remainingSeconds() > left + 5) {
        state.renewal = setTimeout(() => renew(), Math.max(5, left - 15) * 1000);
    }
}

function renew() {
    if (state.ending || state.reconnecting) {
        return;
    }

    state.reconnecting = true;
    connect({ planned: true }).finally(() => { state.reconnecting = false; });
}

// Ce que le professeur doit faire après l'enregistrement du résultat : une seule annonce, puis fin.
const AFTER_VERDICT = {
    // Sans mini-leçon (le serveur envoie la sienne, avec la phrase à enseigner, quand il en accorde le temps).
    record_assessment_result: 'Saved. Now, if you have not announced it yet, make the ONE announcement of the level, then skip the mini-lesson and invite questions in French: « Avez-vous des questions avant de terminer ? Vous pouvez me les poser en français. » and wait. Then follow steps 6 and 7 (end every answer with « Avez-vous une autre question ? »; the conclusion does not repeat the level) of your instructions (questions in French, then ONE conclusion ending with the exact closing sentence). Never announce the level again and never call this function again.',
    // Conclusion unique (END OF THE SESSION des consignes) : elle finit par la phrase de fin, puis la séance se ferme.
    end_lesson: 'Saved. Now give your CONCLUSION once (END OF THE SESSION), ending with the closing sentence. Nothing else: the lesson ends after this turn.',
    record_level_test_result: 'Saved. Now, if you have not announced it yet, announce the result once, kindly, then say goodbye. Do not ask another question: the test ends after this turn.',
};

function sendFunctionResult(call, response) {
    if (state.transport?.isOpen) {
        state.transport.sendFunctionResult(call, response);
    }
}

function showAssessmentResult(level, label, feedback) {
    // Même instant dans la vidéo : le niveau n'y entre que par ici (jamais avant le verdict).
    state.revealed = { level, label };
    recording.recorder?.revealLevel(level, label);
    showOutcome(`<h2>Votre niveau : ${escapeHtml(level)}</h2>`
        + `<p><strong>${escapeHtml(label)}</strong></p>`
        + `<p>${escapeHtml(feedback)}</p>`);
    // Les formules (bouton orange) n'apparaissent qu'à la fin réelle de la conversation (finish) : jamais pendant
    // la mini-leçon, les questions ou la conclusion de Teacher Joe.
    // À la fin de la conversation, la carte « La suite » rappelle le niveau trouvé.
    state.next = { ...state.next, title: `Votre niveau : ${level}` };
}

/**
 * Évaluation : le résultat arrive juste AVANT que le professeur annonce le niveau. L'écran et la vidéo (même appel,
 * showAssessmentResult) le montrent quand sa voix le dit (repéré dans sa transcription, après le son déjà reçu),
 * sinon level_reveal_delay_seconds après le résultat une fois qu'il s'est tu, et au plus tard
 * level_reveal_max_seconds après. Fin de la conversation pendant l'attente : montré aussitôt (revealLevelNow).
 * Ses sous-titres (texte reçu avant sa voix) attendent aussi : ils ne disent jamais le niveau avant l'écran.
 */
function scheduleLevelReveal(level, show) {
    const delayMs = (state.config?.level_reveal_delay_seconds ?? 0) * 1000;
    const maxMs = Math.max(delayMs, (state.config?.level_reveal_max_seconds ?? 0) * 1000);
    const pending = { level, show, receivedAt: Date.now(), delayMs, maxMs, heardAt: null, spokeAfter: false, text: '', held: [], timer: null };
    state.pendingReveal = pending;
    pending.timer = setInterval(() => {
        if (levelRevealDue({ ...pending, now: Date.now(), speaking: teacherSpeaking() })) {
            revealLevelNow();
        }
    }, 100);

    if (delayMs === 0) {
        revealLevelNow();
    }
}

/** Montre le niveau en attente (écran et vidéo au même instant). */
function revealLevelNow() {
    const pending = state.pendingReveal;

    if (!pending) {
        return;
    }

    clearInterval(pending.timer);
    state.pendingReveal = null;
    pending.show();
    // Sous-titres retenus pendant l'annonce : affichés avec le niveau, jamais avant.
    pending.held.forEach((text) => subtitle('teacher', text));
}

/** Paroles du professeur pendant l'attente : le niveau est-il dit ? */
function listenForLevel(text) {
    const pending = state.pendingReveal;

    if (!pending || pending.heardAt !== null) {
        return;
    }

    pending.spokeAfter = true;
    pending.text += text;

    if (mentionsLevel(pending.text, pending.level)) {
        // Le son reçu jusqu'ici (qui contient le niveau) aura fini d'être joué à cette heure-là.
        const ahead = state.audio ? Math.max(0, voiceEnd() - state.audio.context.currentTime) : 0;
        pending.heardAt = Date.now() + ahead * 1000;
    }
}

async function handleFunctionCall(call) {
    const args = call.args ?? {};
    let response = { result: 'ok' };
    let questions = false;
    let nextStep = null;
    const isVerdict = call.name in AFTER_VERDICT;

    if (isVerdict && state.end.hasVerdict) {
        // Deuxième appel du modèle : rien de nouveau à enregistrer, ni à annoncer.
        response = {
            result: 'already_saved',
            next_step: state.end.questions
                ? 'Already saved. Do not announce the level again. Continue exactly where you were (questions or conclusion).'
                : 'Say only "Goodbye!" if you have not said it yet. Nothing else.',
        };
    } else if (call.name === 'record_assessment_result' && mode === 'assessment') {
        // Mini-leçon personnelle : les vraies erreurs de l'apprenant, transmises avec le verdict (facultatif).
        const result = await post(urls.result, { level: args.level, feedback: args.feedback, lesson: Array.isArray(args.lesson) ? args.lesson : [] });

        if (result.ok && result.data.too_early) {
            // Trop tôt : rien n'est enregistré, le professeur pose encore quelques questions (LiveEarlyEnd).
            response = { result: 'not_saved_yet', next_step: result.data.next_step };
        } else if (result.ok) {
            // Moment des questions ouvert par le serveur : la fin autorisée a été repoussée.
            questions = Boolean(result.data.questions);
            const discovery = questions ? result.data.discovery : null;
            // Niveau enregistré tout de suite (serveur), mais montré (écran et vidéo) quand le professeur le dit.
            scheduleLevelReveal(result.data.level, () => {
                showAssessmentResult(result.data.level, result.data.label, args.feedback);
                // Mini-leçon : son tableau (erreurs de l'apprenant, ou phrase de secours ; montré avec le niveau).
                showDiscoveryBoard(discovery);
            });

            if (questions) {
                setRemaining(result.data.remaining_seconds);
                // La suite à donner au professeur (phrase à enseigner) part sans attendre.
                nextStep = result.data.next_step ?? null;
            }
        } else {
            response = { result: 'error', message: result.data.message ?? 'not saved' };
        }
    } else if (call.name === 'end_lesson' && mode === 'lesson') {
        // Bilan structuré : leçon terminée ou à reprendre, erreurs, notes, devoir.
        const result = await post(urls.result, args);

        if (result.ok && result.data.too_early) {
            // Trop tôt : rien n'est enregistré, la séance continue (LiveEarlyEnd).
            response = { result: 'not_saved_yet', next_step: result.data.next_step };
            sendFunctionResult(call, response);

            return;
        }

        const errors = (args.errors ?? []).map((error) => `<li><span lang="en">${escapeHtml(error.wrong)}</span> → <strong lang="en">${escapeHtml(error.correct)}</strong></li>`).join('');

        showOutcome('<h2>Résumé de la séance</h2>'
            + `<p>${escapeHtml(args.summary)}</p>`
            + (args.lesson_status === 'repeat' ? '<p><strong>Cette leçon sera reprise à la prochaine séance.</strong></p>' : '')
            + (errors ? `<h3>Erreurs à revoir</h3><ul>${errors}</ul>` : '')
            + (args.homework ? `<h3>Devoir</h3><p>${escapeHtml(args.homework)}</p>` : ''));

        if (!result.ok) {
            response = { result: 'error', message: result.data.message ?? 'not saved' };
        }
    } else if (call.name === 'record_level_test_result' && mode === 'level_test') {
        const result = await post(urls.result, args);

        if (result.ok && result.data.passed && result.data.next_level) {
            state.next = {
                title: `Bilan réussi : niveau ${result.data.level} validé !`,
                text: `Votre programme ${result.data.next_level} est prêt : continuez avec ${teacherName}.`,
                label: `Commencer mon programme ${result.data.next_level}`,
            };
        } else if (result.ok && !result.data.passed) {
            state.next = {
                title: 'Bilan à repasser',
                text: 'Revoyez les unités à reprendre dans votre programme, puis repassez le bilan.',
                label: 'Revoir les unités à reprendre',
            };
        }

        if (result.ok && result.data.passed) {
            showOutcome(`<h2>Bilan réussi : niveau ${escapeHtml(result.data.level)} validé !</h2>`
                + `<p>${escapeHtml(args.feedback)}</p>`
                + (result.data.next_level ? `<p>Votre programme ${escapeHtml(result.data.next_level)} est prêt.</p>` : '')
                + (result.data.attestation_url ? `<p><a class="btn" href="${escapeHtml(result.data.attestation_url)}">Télécharger mon attestation</a></p>` : ''));
        } else if (result.ok) {
            showOutcome('<h2>Encore un petit effort !</h2>'
                + `<p>${escapeHtml(args.feedback)}</p>`
                + `<p>Unités à reprendre : ${escapeHtml((result.data.units_to_review ?? []).join(', '))}.</p>`);
        } else {
            response = { result: 'error', message: result.data.message ?? 'not saved' };
        }
    } else {
        response = { result: 'error', message: 'unknown function' };
    }

    const saved = isVerdict && response.result === 'ok';

    if (saved) {
        response = { result: 'saved', next_step: nextStep ?? AFTER_VERDICT[call.name] };
    }

    sendFunctionResult(call, response);

    if (saved) {
        // Sans questions : le professeur termine sa phrase, puis la conversation se coupe toute seule.
        // Avec questions (évaluation) : micro ouvert jusqu'à la conclusion, coupure après la phrase fixe.
        state.end.recordVerdict(clock(), { questions });
        // Bilan de la séance enregistré : sa conclusion commence (jamais coupée au milieu par le minuteur).
        state.closing?.startConclusion(clock());
        state.learnerHeardAt = 0;

        if (questions) {
            scheduleRenewal();
        }

        renderTimer();
        renderTurn();
    }
}

function onClose(event) {
    state.connected = false;
    ui.turn.hidden = true;

    if (state.ending) {
        return;
    }

    if (remainingSeconds() <= 2) {
        // Google ferme la connexion à la limite : pendant la conclusion, la voix déjà reçue
        // finit de sortir du haut-parleur (tick → timeUp coupe ensuite).
        if (!state.end.questions && !state.closing?.concluding) {
            finish('time');
        }

        return;
    }

    // Le serveur le sait tout de suite : le temps de séance est mis en pause jusqu'au retour de la connexion.
    heartbeat();
    logEvent('provider_error', `${state.transport?.provider ?? ''} : connexion fermée${event.code ? ` (${event.code})` : ''}${event.reason ? `, ${event.reason}` : ''}`.slice(0, 200));

    if (/quota|exhausted|rate limit/i.test(event.reason ?? '')) {
        fail(MESSAGES.quota);

        return;
    }

    stopPlayback();

    if (state.autoReconnects < MAX_AUTO_RECONNECTS) {
        state.autoReconnects++;
        state.turns.reset();
        logEvent('reconnect', 'automatique');
        setStatus(MESSAGES.reconnecting ?? 'Reconnexion…');
        setTimeout(() => connect(), 1500);

        return;
    }

    setStatus('Connexion coupée');
    showError(MESSAGES.cut);
    ui.resume.hidden = false;
}

/* ---------- Début et fin ---------- */

async function start() {
    ui.start.disabled = true;
    showError('');
    setStatus('Autorisation du micro…');

    try {
        if (!state.audio) {
            await setupAudio();
        }
    } catch (error) {
        ui.start.disabled = false;
        setStatus('Micro indisponible');
        showError({
            NotAllowedError: MESSAGES.micDenied,
            SecurityError: MESSAGES.micDenied,
            NotFoundError: MESSAGES.micMissing,
            InsecureContext: MESSAGES.micInsecure,
        }[error.name] ?? MESSAGES.micOther);

        return;
    }

    if (recording.armed) {
        startRecording();
    }

    // Connexion : la carte d'accueil reste, avec « Teacher Joe arrive… » et les conseils, jusqu'à la première réponse.
    setPhase('connecting');

    try {
        state.wakeLock = await navigator.wakeLock?.request('screen');
    } catch { /* l'écran pourra se mettre en veille */ }

    await connect();

    if (!state.config) {
        return;
    }

    state.timers.forEach(clearInterval);
    state.timers = [];
    state.timers.push(setInterval(tick, 250));
    state.timers.push(setInterval(heartbeat, state.config.heartbeat_seconds * 1000));
}

function tick() {
    renderTimer();
    watchTurn();
    renderTurn();

    const seconds = remainingSeconds();

    const busy = teacherSpeaking() || state.generating;

    // Préavis : envoyé quand il ne parle pas (il finit sa phrase), au plus tard 20 secondes après.
    if (!state.warningSent && state.connected && seconds <= state.config.warning_seconds
        && (!busy || seconds <= state.config.warning_seconds - 20)) {
        state.warningSent = true;

        if (state.config.cues.warning) {
            cue(state.config.cues.warning);
        }
    }

    // Fin de la séance du jour et de « Mes cours » : d'après le temps restant, jamais d'après le micro.
    if (state.closing && state.connected && !state.paused && !state.end.hasVerdict) {
        const step = state.closing.next(seconds, busy, clock());

        if (step) {
            closingCue(step);
        }
    }

    // Séance du jour : étape affichée sur le tableau, et UNE relance douce vers la conversation si elle tarde.
    if (board?.tracker && state.config.phases) {
        const elapsed = lessonElapsed();
        const phase = board.tracker.phase(elapsed);

        if (board.view.setPhase(phase, state.config.phases?.merged)) {
            showPhaseNote(phase);
        }

        if (!board.nudged && state.connected && !state.paused && state.config.cues?.conversation
            && board.tracker.needsConversationNudge(elapsed) && !teacherSpeaking() && !state.generating) {
            board.nudged = true;
            cue(state.config.cues.conversation);
        }
    }

    // UNE indication après le verdict : passer à la conclusion, environ 30 secondes avant la fin.
    // Le micro reste ouvert : l'apprenant peut encore être entendu jusqu'à la phrase fixe (jamais de séance muette).
    if (state.connected && state.end.shouldConclude(seconds, teacherSpeaking() || state.generating, clock())) {
        sendText(state.config.cues.conclusion);
        state.awaitingSince = Date.now();
    }

    if (state.closing?.shouldFinish(clock(), busy)) {
        finish('done');

        return;
    }

    if (seconds <= 0) {
        if (state.closing) {
            // Sa phrase finit, puis la conclusion (si elle n'a pas encore été demandée), puis on ferme.
            const action = state.closing.timeUp(clock(), busy);

            if (action === 'conclude' && state.connected && !state.paused) {
                closingCue('conclude');
            } else if (action !== 'wait') {
                finish('time');

                return;
            }
        } else if (state.end.timeUp(clock(), teacherSpeaking())) {
            finish('time');
        }

        if (!state.closing) {
            return;
        }
    }

    // Verdict donné et annonce terminée : fin de la conversation, sans attendre le minuteur.
    if (state.end.shouldFinish(clock(), teacherSpeaking())) {
        finish('done');

        return;
    }

    // Le temps continue pendant la pause : au-delà de la limite, on termine proprement.
    if (state.paused && Date.now() - state.pausedAt > state.config.pause_max_seconds * 1000) {
        finish('pause', MESSAGES.pauseTooLong);
    }
}

/** Indication de fin de séance ('announce' ou 'conclude'), envoyée même après les autres indications. */
function closingCue(step) {
    const text = state.config.session_end?.[step];

    if (!text) {
        return;
    }

    logEvent('session_end', step === 'announce' ? 'fin : une minute' : 'fin : conclusion');
    sendText(text);
    state.awaitingSince = Date.now();
    renderTurn();
}

/* ---------- Tour de parole, interruption, pause et micro ---------- */

function currentTurn() {
    const now = Date.now();

    if (state.paused) {
        return 'paused';
    }

    if (teacherSpeaking()) {
        return 'speaking';
    }

    // Verdict donné (sans questions), conclusion en cours ou faite : micro fermé, fin proche.
    if (!micOpen()) {
        return 'ending';
    }

    // L'apprenant s'est tu : Google attend la fin de son silence, puis le professeur répond.
    if (state.learnerHeardAt && now - state.learnerHeardAt > THINKING_AFTER_MS) {
        state.learnerHeardAt = 0;
        state.awaitingSince = now;
    }

    if (state.awaitingSince && now - state.awaitingSince > THINKING_MAX_MS) {
        state.awaitingSince = 0;
    }

    if (state.awaitingSince || state.turns.endingTurn || state.turns.waiting(now)) {
        return 'thinking';
    }

    return state.muted ? 'muted' : 'listening';
}

function renderTurn() {
    if (state.ending || !state.connected) {
        return;
    }

    const turn = currentTurn();
    // Évaluation, premier tour de l'élève : conseil général (des élèves n'avaient rien dit du tout) ; l'exemple
    // n'apparaît que s'il correspond à la question réellement posée (jamais « My name is … » après « How are you? »).
    const text = turn === 'listening'
        ? listeningText(state.teacherSaid, { firstAnswer: mode === 'assessment' && !state.learnerAnswered })
        : TURN_TEXTS[turn];

    if (ui.turn.dataset.turn !== turn || ui.turnText.textContent !== text) {
        ui.turn.dataset.turn = turn;
        ui.turnText.textContent = text;

        // Attente de sa réponse : léger geste (hochement, sourire), jamais d'écran qui « charge ».
        if (turn === 'thinking') {
            teacher?.expression('waiting');
        }

        // Visage simple : même petit hochement (CSS).
        root.toggleAttribute('data-waiting', turn === 'thinking');
    }

    ui.interrupt.hidden = turn !== 'speaking';
}

function setPressed(button, pressed) {
    button.setAttribute('aria-pressed', String(pressed));
    button.querySelector('.live-control-off').hidden = pressed;
    button.querySelector('.live-control-on').hidden = !pressed;
}

/**
 * Arrête la voix du professeur ; la fin de sa réplique en cours ne sera pas jouée.
 */
function silenceTeacher() {
    stopPlayback();
    // L'apprenant a choisi d'interrompre (ou la pause) : le micro n'attend plus la fin de la réponse.
    state.voicePending = false;
    state.expectingVoiceUntil = 0;
    // OpenAI : réponse annulée et voix pas encore jouée effacée (Gemini : la fin est simplement ignorée).
    state.transport?.cancel();

    if (state.generating) {
        state.discardTurn = true;
    }

    state.awaitingSince = 0;
}

function interruptTeacher() {
    logEvent('interrupt');
    state.turns.reset();
    teacherResponseOver();
    silenceTeacher();
    teacher?.expression('attentive');
    renderTurn();
}

function togglePause() {
    if (state.ending) {
        return;
    }

    state.paused = !state.paused;
    setPressed(ui.pause, state.paused);
    ui.pauseNote.hidden = !state.paused;

    logEvent(state.paused ? 'pause' : 'resume');

    if (state.paused) {
        state.pausedAt = Date.now();
        state.learnerHeardAt = 0;
        state.turns.reset();
        teacherResponseOver();
        hideWaitNote();
        silenceTeacher();
        endMicStream();
        setStatus('En pause');
    } else {
        setStatus('En conversation');
        unmuteVoiceStream();
        // L'apprenant n'a rien entendu pendant la pause : le professeur reprend sa question.
        cue(resumeCue('pause'));
    }

    renderTurn();
}

function toggleMute() {
    if (state.ending) {
        return;
    }

    state.muted = !state.muted;
    setPressed(ui.mute, state.muted);
    state.audio?.stream.getAudioTracks().forEach((track) => { track.enabled = !state.muted; });

    if (state.muted) {
        endMicStream();
    }

    renderTurn();
}

/**
 * La séance est maintenant ouverte sur une autre page : celle-ci se tait et s'arrête, sans rien envoyer au serveur
 * (la séance n'est pas close : elle continue là-bas).
 */
function superseded() {
    if (state.ending || !state.config) {
        return;
    }

    logEvent('provider_error', 'séance ouverte sur une autre page');
    state.config = null;
    finish('superseded', MESSAGES.superseded);
}

pages?.addEventListener('message', (event) => {
    // Autre onglet de ce navigateur qui commence une séance : celle-ci s'arrête tout de suite.
    if (event.data?.claim && event.data.claim !== PAGE_ID) {
        superseded();
    }
});

function fail(message) {
    finish('error');
    showError(message);
}

async function finish(reason, message = '') {
    if (state.ending) {
        return;
    }

    state.ending = true;
    state.timers.forEach(clearInterval);
    clearTimeout(state.renewal);

    state.transport?.close();

    stopPlayback();
    teacher?.expression('goodbye');
    // Niveau encore en attente (fin pendant l'annonce) : montré maintenant, à l'écran et dans la vidéo.
    revealLevelNow();
    // La vidéo s'arrête avec la séance, jamais après, et avant la fermeture du contexte audio.
    stopRecording();
    recording.armed = false;

    if (rec.start) {
        rec.start.hidden = true;
        rec.armed.hidden = true;
    }

    teacher?.detachAudio();
    state.audio?.stream.getTracks().forEach((track) => track.stop());
    state.audio?.context.close();
    state.audio = null;
    state.wakeLock?.release?.();

    setPhase('done');
    ui.stop.hidden = true;
    ui.resume.hidden = true;
    ui.intro.hidden = true;
    ui.controls.hidden = true;
    ui.turn.hidden = true;
    ui.pauseNote.hidden = true;
    ui.micPanel.hidden = true;
    ui.micWarning.hidden = true;
    ui.waitNote.hidden = true;
    navigator.mediaDevices?.removeEventListener?.('devicechange', refreshMicrophones);
    setStatus(reason === 'time' ? 'Temps écoulé' : 'Terminé');

    if (message) {
        showError(message);
    }

    let closing = null;

    if (state.config) {
        // Le texte de la conversation part en même temps, sans retarder la fin.
        flushAllTranscript();
        closing = await post(urls.finish, { ...takeTokens(), ...takeTranscript() });
    }

    // Temps écoulé avant le verdict : le serveur a estimé le niveau à partir de la conversation.
    if (mode === 'assessment' && ui.outcome.hidden && closing?.data?.level) {
        showAssessmentResult(closing.data.level, closing.data.label, closing.data.feedback ?? '');
    }

    if (ui.outcome.hidden && state.config) {
        showOutcome({
            assessment: '<h2>Évaluation terminée</h2><p>Votre résultat apparaît dans votre espace s\'il a été enregistré.</p>',
            level_test: '<h2>Bilan terminé</h2><p>Le résultat apparaît dans votre espace s\'il a été enregistré.</p>',
            course: '<h2>Séance terminée</h2><p>Bravo ! Vous pouvez revenir sur cette leçon quand vous voulez.</p>',
            trial: '<h2>Essai terminé</h2><p>Rien n\'a été compté comme une séance.</p>',
        }[mode] ?? '<h2>Séance terminée</h2><p>Merci et à demain !</p>');
    }

    state.config = state.config && { ...state.config, warning_seconds: 0 };
    setRemaining(0);

    // Évaluation : les formules, juste sous le résultat, une fois la conversation vraiment finie.
    if (mode === 'assessment' && state.revealed) {
        ui.plans?.removeAttribute('hidden');
    }

    // La suite : en haut de la colonne, avant le résumé (texte adapté au résultat : niveau, bilan réussi…).
    // Conversation jamais commencée (connexion refusée) : seulement le lien de retour.
    if (state.config && state.everReady) {
        showNext(state.next ?? {});
    } else {
        ui.back.hidden = false;
    }
}

ui.start.addEventListener('click', start);
ui.interrupt.addEventListener('click', interruptTeacher);
ui.pause.addEventListener('click', togglePause);
ui.mute.addEventListener('click', toggleMute);
ui.nudge.addEventListener('click', nudgeTeacher);

/*
 * Abonné payant sans adresse e-mail : il l'écrit sur l'écran de la séance, sans la quitter. Une fois enregistrée,
 * le professeur est prévenu (il remercie en une phrase, puis continue). Sans JavaScript : envoi normal du formulaire.
 */
const emailForm = document.getElementById('email-ask-form');

if (emailForm) {
    emailForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = emailForm.querySelector('button[type="submit"]');
        const error = document.getElementById('email-ask-error');
        const input = emailForm.querySelector('input[name="email"]');
        error.hidden = true;
        button.disabled = true;

        try {
            const response = await fetch(emailForm.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': emailForm.querySelector('input[name="_token"]')?.value ?? '' },
                body: new FormData(emailForm),
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                error.textContent = data.errors?.email?.[0] ?? 'Enregistrement impossible pour le moment. Réessayez dans un instant.';
                error.hidden = false;
                input?.focus();

                return;
            }

            emailForm.hidden = true;
            document.getElementById('email-ask-done').hidden = false;
            sendText(state.config?.cues?.email_saved);
        } catch {
            error.textContent = 'Connexion impossible. Vérifiez votre connexion internet, puis réessayez.';
            error.hidden = false;
        } finally {
            button.disabled = false;
        }
    });
}
ui.micSelect.addEventListener('change', () => switchMicrophone(ui.micSelect.value));
// « Terminer » avant la fin : fenêtre de confirmation intégrée (public/js/app.js), jamais de fenêtre du navigateur.
ui.stop.addEventListener('click', async () => {
    const answer = window.askConfirmation ? await window.askConfirmation(JSON.parse(root.dataset.stopConfirm)) : true;

    if (answer !== false) {
        finish('user');
    }
});
ui.resume.addEventListener('click', () => {
    ui.resume.hidden = true;
    state.autoReconnects = 0;
    state.turns.reset();
    logEvent('reconnect', 'bouton « Reprendre la séance »');
    connect();
});

// Page fermée ou quittée : on prévient le serveur pour clore la conversation.
window.addEventListener('pagehide', () => {
    if (state.config && !state.ending) {
        const form = new FormData();
        form.append('_token', csrf);
        form.append('page_id', PAGE_ID);
        Object.entries(takeTokens()).forEach(([name, value]) => form.append(name, value));

        const { transcript } = takeTranscript();

        if (transcript) {
            form.append('transcript', transcript);
        }

        navigator.sendBeacon(urls.finish, form);

        // Répliques et événements pas encore envoyés : un dernier lot, sans attendre de réponse.
        const lines = state.config.transcript_url ? TranscriptRecorder.payload(state.recorder.takeBatch()) : [];
        const events = state.config.transcript_url ? TranscriptRecorder.eventsPayload(state.recorder.takeEvents()) : [];

        if (lines.length > 0 || events.length > 0) {
            const text = new FormData();
            text.append('_token', csrf);
            text.append('page_id', PAGE_ID);
            TranscriptRecorder.formFields(lines, events).forEach(([name, value]) => text.append(name, value));
            navigator.sendBeacon(state.config.transcript_url, text);
        }
    }
});
