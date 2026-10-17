/*
 * Conversation vocale avec le professeur (évaluation et séance du jour).
 *
 * Le navigateur se connecte directement à l'API Gemini Live par WebSocket, avec
 * un jeton éphémère délivré par le serveur (la clé API ne quitte jamais le
 * serveur). Le serveur reste maître du temps : le jeton expire à la limite, et
 * le navigateur envoie un signal régulier qui enregistre le temps consommé.
 *
 * Audio : micro en PCM 16 bits 16 kHz vers Gemini, voix du professeur reçue en
 * PCM 16 bits 24 kHz. La bouche du visage suit le volume de cette voix.
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
const { MicGate, voiceEndsAt } = await import(root.dataset.micGate);
// Lecture propre de la voix (décodage, programmation bout à bout, rééchantillonnage continu).
const { VOICE_RATE, decodePcm16, StreamResampler, VoiceScheduler, fadeIn } = await import(root.dataset.pcmAudio);
// Micro : liste « Micro utilisé », témoin de niveau, alerte de silence.
const { listMicrophones, rms, meterLevel, SilenceWatch } = await import(root.dataset.micInput);
// Réglages audio de la page (config/platform.php → live).
const audioConfig = JSON.parse(root.dataset.audio);
// Fin automatique après le verdict ; expressions du professeur 3D d'après ses paroles.
const { ConversationEnd } = await import(root.dataset.conversationEnd);
const { CueTracker } = await import(root.dataset.teacherCues);
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
    interrupt: $('interrupt'), pause: $('pause'), mute: $('mute'), pauseNote: $('pause-note'),
};
const teacherName = root.dataset.teacherName || 'Le professeur';

// Professeur 3D, chargé en arrière-plan ; le visage SVG reste affiché en attendant.
let teacher = null;

if (root.dataset.teacher3d && root.dataset.teacherView) {
    import(root.dataset.teacherView)
        .then(({ createTeacherView }) => {
            teacher = createTeacherView(root, JSON.parse(root.dataset.teacher3d));

            if (state.audio) {
                teacher.attachAudio(state.audio.context, state.audio.output, state.audio.micBus);
            }
        })
        .catch((error) => console.warn('Professeur 3D indisponible :', error));
}

const INPUT_RATE = 16000;
// Fondu à l'arrêt de la voix (interruption, pause), en secondes : pas de clic.
const STOP_FADE = 0.015;
const MAX_AUTO_RECONNECTS = 2;
// Retard ajouté à la voix du professeur (lecture labiale du professeur 3D).
const OUTPUT_DELAY = 0.08;
// Après la fin de parole de l'apprenant : au-delà, on affiche « réfléchit… ».
const THINKING_AFTER_MS = 1300;
// Sans réponse du professeur pendant ce temps, on rend la parole à l'apprenant.
const THINKING_MAX_MS = 12000;

const TURN_TEXTS = {
    speaking: `${teacherName} parle…`,
    listening: 'À vous de parler',
    thinking: `${teacherName} réfléchit…`,
    paused: 'Conversation en pause',
    muted: 'Micro coupé',
    ending: 'Fin de la conversation…',
};

const MESSAGES = {
    micDenied: 'Vous avez refusé l\'accès au micro. Autorisez-le dans les réglages du navigateur (icône à gauche de l\'adresse du site), puis rechargez la page.',
    micMissing: 'Aucun micro n\'a été trouvé sur cet appareil. Branchez des écouteurs avec micro ou essayez depuis votre téléphone.',
    micInsecure: 'Votre navigateur ne permet pas d\'utiliser le micro sur cette page. Ouvrez le site avec une adresse sécurisée (https) dans Chrome, Firefox ou Safari à jour.',
    micOther: 'Le micro n\'a pas pu être activé. Fermez les autres applications qui l\'utilisent, puis réessayez.',
    network: 'Impossible de joindre le serveur. Vérifiez votre connexion internet, puis réessayez.',
    cut: 'La connexion avec le professeur a été coupée. Vérifiez votre connexion internet, puis appuyez sur « Se reconnecter ».',
    quota: 'Le professeur a atteint sa limite de conversations gratuites pour le moment. Réessayez un peu plus tard : votre temps non utilisé reste disponible.',
    pauseTooLong: 'La pause a duré trop longtemps : la conversation est terminée. Le temps déjà utilisé est décompté.',
};

const state = {
    config: null,
    ws: null,
    connected: false,
    ending: false,
    reconnecting: false,
    autoReconnects: 0,
    resumeHandle: null,
    deadline: 0,
    warningSent: false,
    tokens: { input: 0, output: 0 },
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
    tokenExpiresAt: 0,
    cues: new CueTracker(),
    // Sous-titres de l'évaluation pas encore envoyés au serveur (repli si le temps finit sans verdict).
    transcript: '',
    transcriptSpeaker: null,
};

/* ---------- Échanges avec le serveur Laravel ---------- */

async function post(url, body = {}) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        const data = await response.json().catch(() => ({}));

        return { ok: response.ok, status: response.status, data };
    } catch {
        return { ok: false, status: 0, data: { message: MESSAGES.network } };
    }
}

function takeTokens() {
    const tokens = { input_tokens: state.tokens.input, output_tokens: state.tokens.output };
    state.tokens = { input: 0, output: 0 };

    return tokens;
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

    const tokens = takeTokens();
    const transcript = takeTranscript();
    const result = await post(urls.signal, { ...tokens, ...transcript });

    if (!result.ok) {
        // Le signal sera renvoyé avec les jetons (et les sous-titres) au prochain passage.
        state.tokens.input += tokens.input_tokens;
        state.tokens.output += tokens.output_tokens;
        state.transcript = (transcript.transcript ?? '') + state.transcript;

        return;
    }

    setRemaining(result.data.remaining_seconds);

    if (!result.data.active) {
        // Pendant les questions ou la conclusion, la phrase en cours peut finir (tick → timeUp).
        if (state.end.questions) {
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
        ui.timerLabel.textContent = state.end.questions ? 'Vos questions' : 'Temps restant';
    }

    ui.timer.textContent = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
    ui.timer.classList.toggle('timer-low', state.config !== null && seconds <= state.config.warning_seconds);
}

function subtitle(speaker, text) {
    if (!text) {
        return;
    }

    const key = speaker === 'teacher' ? 'teacherLine' : 'learnerLine';

    if (!state[key]) {
        state[key] = document.createElement('p');
        state[key].className = `line line-${speaker}`;
        state[key].dataset.speaker = speaker === 'teacher' ? 'Professeur' : 'Vous';
        ui.subtitles.append(state[key]);
    }

    state[key].textContent += text;
    ui.subtitles.scrollTop = ui.subtitles.scrollHeight;
    recordTranscript(speaker, text);

    // Expression du professeur 3D au moment où il dit « Great answer! », « You said… », « Goodbye! »…
    if (speaker === 'teacher') {
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
    const delay = context.createDelay(0.5);
    delay.delayTime.value = OUTPUT_DELAY;
    const analyser = context.createAnalyser();
    analyser.fftSize = 512;
    output.connect(delay).connect(analyser).connect(context.destination);

    state.audio = {
        context, stream, source, micBus, micAnalyser, output, voice, analyser,
        micSamples: new Float32Array(micAnalyser.fftSize),
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

/**
 * Ouvre le micro. L'annulation d'écho est réglable (config platform.live.echo_cancellation) ;
 * réduction de bruit et gain automatique toujours demandés. Le micro choisi est
 * « préféré » au démarrage (s'il a disparu, un autre est pris) et « exigé » quand
 * l'apprenant le choisit dans la liste.
 */
async function openMicrophone(deviceId, exact) {
    const audio = {
        channelCount: 1,
        echoCancellation: audioConfig.echo_cancellation,
        noiseSuppression: true,
        autoGainControl: true,
    };

    if (deviceId) {
        audio.deviceId = exact ? { exact: deviceId } : { ideal: deviceId };
    }

    const stream = await navigator.mediaDevices.getUserMedia({ audio });
    const settings = stream.getAudioTracks()[0]?.getSettings?.() ?? {};
    console.debug('Micro : réglages obtenus', settings);

    if (audioConfig.echo_cancellation && settings.echoCancellation === false) {
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

function base64FromBuffer(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';

    for (let i = 0; i < bytes.length; i += 0x8000) {
        binary += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
    }

    return btoa(binary);
}

/**
 * Instant (horloge audio) où le haut-parleur se tait vraiment : fin programmée
 * du dernier tampon de voix, retard ajouté et latence de sortie de l'appareil.
 */
function voiceEnd() {
    const { context, scheduler } = state.audio;

    return voiceEndsAt(scheduler.endTime, OUTPUT_DELAY, context.outputLatency || context.baseLatency || 0);
}

/**
 * Vrai tant que la voix du professeur sort du haut-parleur.
 */
function teacherSpeaking() {
    return state.audio !== null && state.audio.context.currentTime < voiceEnd();
}

/**
 * Chaque paquet du micro passe par la porte : envoyé seulement micro ouvert
 * (ni voix du professeur, ni echo_guard_ms après, ni pause, ni micro coupé).
 * Micro fermé plus d'une seconde (tout de suite en pause ou micro coupé) :
 * « audioStreamEnd », comme le demande Google ; le flux reprend au paquet suivant.
 */
function sendAudio(buffer) {
    if (!state.connected || state.ending || !state.audio || !state.gate || state.ws?.readyState !== WebSocket.OPEN) {
        return;
    }

    // Après le verdict, micro fermé, sauf pendant les questions libres de l'évaluation (jusqu'à la conclusion).
    const action = state.gate.decide(state.audio.context.currentTime, voiceEnd(), state.paused || state.muted || !state.end.micOpen);

    if (action === 'send' && buffer) {
        state.ws.send(JSON.stringify({
            realtimeInput: { audio: { data: base64FromBuffer(buffer), mimeType: `audio/pcm;rate=${INPUT_RATE}` } },
        }));
    } else if (action === 'end') {
        state.ws.send(JSON.stringify({ realtimeInput: { audioStreamEnd: true } }));
    }
}

/**
 * Pause ou micro coupé : fermeture immédiate du flux, sans attendre le prochain paquet.
 */
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
    const { context, voice, sources, scheduler, resampler } = state.audio;
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
    sources.add(node);
    node.onended = () => sources.delete(node);
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
        opening = opening * 0.6 + volume * 0.4;
        ui.mouth.setAttribute('ry', (3 + opening * 17).toFixed(1));
        updateMicMeter();
        requestAnimationFrame(frame);
    };

    requestAnimationFrame(frame);
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
    }
}

/* ---------- Connexion à Gemini Live ---------- */

async function connect({ planned = false } = {}) {
    setStatus(planned ? 'En conversation' : 'Connexion au professeur…');

    const result = await post(urls.connect, { handle: state.resumeHandle });

    if (!result.ok) {
        if (planned && state.connected) {
            return; // La connexion actuelle continue jusqu'à l'expiration de son jeton.
        }

        const message = result.data.quota ? MESSAGES.quota : (result.data.message || MESSAGES.network);

        if (result.status === 409 || result.status === 403) {
            finish('refused', message);
        } else if (state.config === null) {
            // Première connexion impossible : rien n'a commencé, on peut réessayer.
            setStatus('Indisponible');
            showError(message);
            ui.intro.hidden = false;
            ui.subtitles.hidden = true;
            ui.start.disabled = false;
        } else {
            setStatus('Connexion coupée');
            showError(message);
            ui.resume.hidden = false;
        }

        return;
    }

    const isResume = state.config !== null;
    state.config = result.data;
    state.tokenExpiresAt = Date.now() + state.config.token_expires_in * 1000;

    if (!isResume && state.config.closing_phrase) {
        state.end = new ConversationEnd({
            closingPhrase: state.config.closing_phrase,
            cueSeconds: state.config.conclusion_cue_seconds,
            grace: state.config.closing_grace_seconds,
        });
    }

    // Page rechargée pendant les questions : le verdict est déjà enregistré.
    if (state.config.questions && !state.end.hasVerdict) {
        state.end.recordVerdict(clock(), { questions: true });
    }

    setRemaining(state.config.remaining_seconds);

    const ws = new WebSocket(state.config.websocket_url);
    let ready = false;

    ws.onopen = () => ws.send(JSON.stringify({ setup: state.config.setup }));

    ws.onmessage = async (event) => {
        const text = typeof event.data === 'string' ? event.data : await event.data.text();
        const message = JSON.parse(text);

        if (message.setupComplete && !ready) {
            ready = true;
            const previous = state.ws;
            state.ws = ws;
            state.connected = true;
            state.reconnecting = false;

            if (previous && previous !== ws) {
                previous.onclose = null;
                previous.close();
            }

            setStatus('En conversation');
            showError('');
            ui.resume.hidden = true;
            ui.stop.hidden = false;
            ui.controls.hidden = false;
            ui.turn.hidden = false;
            ui.micPanel.hidden = false;
            state.generating = false;
            state.discardTurn = false;
            // Nouvelle connexion : flux du micro remis à zéro.
            state.gate = new MicGate({ guard: (state.config.echo_guard_ms ?? 400) / 1000 });

            // Un seul accueil : à la première connexion. En pause, la reprise
            // de la pause suffira (inutile de faire parler le professeur).
            if (!planned && !(isResume && state.paused)) {
                cue(isResume ? state.config.cues.resume : state.config.cues.start);
            }

            if (!isResume && !planned) {
                teacher?.expression('welcome');
            }

            scheduleRenewal();
        }

        if (ws === state.ws) {
            handleMessage(message);
        }
    };

    ws.onclose = (event) => {
        // Échec d'un renouvellement : l'ancienne connexion continue, rien à faire.
        if (ws === state.ws || (!ready && !(planned && state.connected))) {
            onClose(event);
        }
    };
}

function sendText(text) {
    if (state.ws?.readyState === WebSocket.OPEN && text) {
        state.ws.send(JSON.stringify({ realtimeInput: { text } }));
    }
}

/**
 * Indication au professeur, qui va répondre : « réfléchit… » en attendant sa voix.
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

function handleMessage(message) {
    const content = message.serverContent;

    if (content) {
        if (content.modelTurn) {
            state.generating = true;
        }

        for (const part of content.modelTurn?.parts ?? []) {
            if (part.inlineData?.data) {
                playAudio(part.inlineData.data);
            }
        }

        if (content.inputTranscription?.text) {
            teacher?.learnerSpoke();
            state.teacherLine = null;
            state.cues.reset();
            // Sous-titre arrivé en retard, pendant la réponse du professeur : ce n'est plus son tour.
            state.learnerHeardAt = state.generating ? 0 : Date.now();
            subtitle('learner', content.inputTranscription.text);
        }

        // Ce qui n'est pas joué (pause, interruption) n'apparaît pas non plus en sous-titre.
        if (content.outputTranscription?.text && !state.paused && !state.discardTurn) {
            state.learnerLine = null;
            subtitle('teacher', content.outputTranscription.text);

            // Phrase fixe de la conclusion : micro fermé, coupure une fois la voix terminée.
            if (state.end.teacherSaid(content.outputTranscription.text, clock())) {
                endMicStream();
            }
        }

        if (content.interrupted) {
            stopPlayback();
            state.generating = false;
            state.discardTurn = false;
        }

        if (content.turnComplete) {
            state.end.turnComplete(clock());
            state.cues.reset();
            state.teacherLine = null;
            state.learnerLine = null;
            state.generating = false;
            state.discardTurn = false;
            state.awaitingSince = 0;
        }

        renderTurn();
    }

    if (message.toolCall?.functionCalls) {
        message.toolCall.functionCalls.forEach(handleFunctionCall);
        state.awaitingSince = Date.now();
    }

    if (message.usageMetadata) {
        state.tokens.input += message.usageMetadata.promptTokenCount ?? 0;
        state.tokens.output += message.usageMetadata.responseTokenCount ?? message.usageMetadata.candidatesTokenCount ?? 0;
    }

    if (message.sessionResumptionUpdate?.resumable && message.sessionResumptionUpdate.newHandle) {
        state.resumeHandle = message.sessionResumptionUpdate.newHandle;
    }

    if (message.goAway) {
        // Google va fermer cette connexion : on en ouvre une autre sans attendre.
        renew();
    }
}

// Ce que le professeur doit faire après l'enregistrement du résultat : une seule annonce, puis fin.
const AFTER_VERDICT = {
    record_assessment_result: 'Saved. Now, if you have not announced it yet, make the ONE announcement of the level in simple English, then invite questions in French: « Avez-vous des questions avant de terminer ? Vous pouvez me les poser en français. » and wait. Then follow steps 5 and 6 of your instructions (questions in French, then ONE conclusion ending with the exact closing sentence). Never announce the level again and never call this function again.',
    end_lesson: 'Saved. Now say goodbye warmly in one or two short sentences. Do not start anything new: the lesson ends after this turn.',
    record_level_test_result: 'Saved. Now, if you have not announced it yet, announce the result once, kindly, then say goodbye. Do not ask another question: the test ends after this turn.',
};

function showAssessmentResult(level, label, feedback) {
    showOutcome(`<h2>Votre niveau : ${escapeHtml(level)}</h2>`
        + `<p><strong>${escapeHtml(label)}</strong></p>`
        + `<p>${escapeHtml(feedback)}</p>`);
    // Les formules s'affichent juste en dessous du résultat (bouton orange).
    ui.plans?.removeAttribute('hidden');
}

async function handleFunctionCall(call) {
    const args = call.args ?? {};
    let response = { result: 'ok' };
    let questions = false;
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
        const result = await post(urls.result, { level: args.level, feedback: args.feedback });

        if (result.ok) {
            showAssessmentResult(result.data.level, result.data.label, args.feedback);
            // Moment des questions ouvert par le serveur : la fin autorisée a été repoussée.
            questions = Boolean(result.data.questions);

            if (questions) {
                setRemaining(result.data.remaining_seconds);
            }
        } else {
            response = { result: 'error', message: result.data.message ?? 'not saved' };
        }
    } else if (call.name === 'end_lesson' && mode === 'lesson') {
        // Bilan structuré : leçon terminée ou à reprendre, erreurs, notes, devoir.
        const result = await post(urls.result, args);
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
        response = { result: 'saved', next_step: AFTER_VERDICT[call.name] };
    }

    if (state.ws?.readyState === WebSocket.OPEN) {
        state.ws.send(JSON.stringify({
            toolResponse: { functionResponses: [{ id: call.id, name: call.name, response }] },
        }));
    }

    if (saved) {
        // Sans questions : le professeur termine sa phrase, puis la conversation se coupe toute seule.
        // Avec questions (évaluation) : micro ouvert jusqu'à la conclusion, coupure après la phrase fixe.
        state.end.recordVerdict(clock(), { questions });
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
        if (!state.end.questions) {
            finish('time');
        }

        return;
    }

    if (/quota|exhausted|rate limit/i.test(event.reason ?? '')) {
        fail(MESSAGES.quota);

        return;
    }

    stopPlayback();

    if (state.autoReconnects < MAX_AUTO_RECONNECTS) {
        state.autoReconnects++;
        setStatus('Connexion perdue, reconnexion…');
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

    ui.intro.hidden = true;
    ui.subtitles.hidden = false;

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
    renderTurn();

    const seconds = remainingSeconds();

    if (!state.warningSent && state.connected && seconds <= state.config.warning_seconds) {
        state.warningSent = true;
        cue(state.config.cues.warning);
    }

    // UNE indication après le verdict : passer à la conclusion, environ 30 secondes avant la fin.
    if (state.connected && state.end.shouldConclude(seconds, teacherSpeaking() || state.generating)) {
        sendText(state.config.cues.conclusion);
        endMicStream();
        state.awaitingSince = Date.now();
    }

    if (seconds <= 0) {
        if (state.end.timeUp(clock(), teacherSpeaking())) {
            finish('time');
        }

        return;
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
    if (!state.end.micOpen) {
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

    if (state.awaitingSince) {
        return 'thinking';
    }

    return state.muted ? 'muted' : 'listening';
}

function renderTurn() {
    if (state.ending || !state.connected) {
        return;
    }

    const turn = currentTurn();

    if (ui.turn.dataset.turn !== turn || !ui.turnText.textContent) {
        ui.turn.dataset.turn = turn;
        ui.turnText.textContent = TURN_TEXTS[turn];
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

    if (state.generating) {
        state.discardTurn = true;
    }

    state.awaitingSince = 0;
}

function interruptTeacher() {
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

    if (state.paused) {
        state.pausedAt = Date.now();
        state.learnerHeardAt = 0;
        silenceTeacher();
        endMicStream();
        setStatus('En pause');
    } else {
        setStatus('En conversation');
        // L'apprenant n'a rien entendu pendant la pause : le professeur reprend sa question.
        cue(state.config.cues.pause_resume);
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

    if (state.ws) {
        state.ws.onclose = null;
        state.ws.close();
    }

    stopPlayback();
    teacher?.expression('goodbye');
    teacher?.detachAudio();
    state.audio?.stream.getTracks().forEach((track) => track.stop());
    state.audio?.context.close();
    state.audio = null;
    state.wakeLock?.release?.();

    ui.stop.hidden = true;
    ui.resume.hidden = true;
    ui.intro.hidden = true;
    ui.controls.hidden = true;
    ui.turn.hidden = true;
    ui.pauseNote.hidden = true;
    ui.micPanel.hidden = true;
    ui.micWarning.hidden = true;
    navigator.mediaDevices?.removeEventListener?.('devicechange', refreshMicrophones);
    ui.back.hidden = false;
    setStatus(reason === 'time' ? 'Temps écoulé' : 'Terminé');

    if (message) {
        showError(message);
    }

    let closing = null;

    if (state.config) {
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
        }[mode] ?? '<h2>Séance terminée</h2><p>Merci et à demain !</p>');
    }

    state.config = state.config && { ...state.config, warning_seconds: 0 };
    setRemaining(0);
}

ui.start.addEventListener('click', start);
ui.interrupt.addEventListener('click', interruptTeacher);
ui.pause.addEventListener('click', togglePause);
ui.mute.addEventListener('click', toggleMute);
ui.micSelect.addEventListener('change', () => switchMicrophone(ui.micSelect.value));
ui.stop.addEventListener('click', () => {
    if (confirm('Terminer maintenant ? Le temps déjà utilisé est décompté.')) {
        finish('user');
    }
});
ui.resume.addEventListener('click', () => {
    ui.resume.hidden = true;
    state.autoReconnects = 0;
    connect();
});

// Page fermée ou quittée : on prévient le serveur pour clore la conversation.
window.addEventListener('pagehide', () => {
    if (state.config && !state.ending) {
        const form = new FormData();
        form.append('_token', csrf);
        const tokens = takeTokens();
        form.append('input_tokens', tokens.input_tokens);
        form.append('output_tokens', tokens.output_tokens);

        const { transcript } = takeTranscript();

        if (transcript) {
            form.append('transcript', transcript);
        }

        navigator.sendBeacon(urls.finish, form);
    }
});
