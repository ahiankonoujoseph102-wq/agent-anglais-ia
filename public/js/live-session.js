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
 */

const root = document.getElementById('live');
const urls = JSON.parse(root.dataset.urls);
const mode = root.dataset.mode;
const csrf = document.querySelector('meta[name="csrf-token"]').content;

const $ = (id) => document.getElementById(id);
const ui = {
    start: $('start'), stop: $('stop'), resume: $('resume'), back: $('back'),
    intro: $('intro'), status: $('status'), timer: $('timer'), error: $('error'),
    subtitles: $('subtitles'), outcome: $('outcome'), mouth: $('mouth'),
};

// Professeur 3D, chargé en arrière-plan ; le visage SVG reste affiché en attendant.
let teacher = null;

if (root.dataset.teacher3d && root.dataset.teacherView) {
    import(root.dataset.teacherView)
        .then(({ createTeacherView }) => {
            teacher = createTeacherView(root, JSON.parse(root.dataset.teacher3d));

            if (state.audio) {
                teacher.attachAudio(state.audio.context, state.audio.output, state.audio.source);
            }
        })
        .catch((error) => console.warn('Professeur 3D indisponible :', error));
}

const INPUT_RATE = 16000;
const OUTPUT_RATE = 24000;
const MAX_AUTO_RECONNECTS = 2;

const MESSAGES = {
    micDenied: 'Vous avez refusé l\'accès au micro. Autorisez-le dans les réglages du navigateur (icône à gauche de l\'adresse du site), puis rechargez la page.',
    micMissing: 'Aucun micro n\'a été trouvé sur cet appareil. Branchez des écouteurs avec micro ou essayez depuis votre téléphone.',
    micInsecure: 'Votre navigateur ne permet pas d\'utiliser le micro sur cette page. Ouvrez le site avec une adresse sécurisée (https) dans Chrome, Firefox ou Safari à jour.',
    micOther: 'Le micro n\'a pas pu être activé. Fermez les autres applications qui l\'utilisent, puis réessayez.',
    network: 'Impossible de joindre le serveur. Vérifiez votre connexion internet, puis réessayez.',
    cut: 'La connexion avec le professeur a été coupée. Vérifiez votre connexion internet, puis appuyez sur « Reprendre ».',
    quota: 'Le professeur a atteint sa limite de conversations gratuites pour le moment. Réessayez un peu plus tard : votre temps non utilisé reste disponible.',
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

async function heartbeat() {
    if (state.ending) {
        return;
    }

    const tokens = takeTokens();
    const result = await post(urls.signal, tokens);

    if (!result.ok) {
        // Le signal sera renvoyé avec les jetons au prochain passage.
        state.tokens.input += tokens.input_tokens;
        state.tokens.output += tokens.output_tokens;

        return;
    }

    setRemaining(result.data.remaining_seconds);

    if (!result.data.active) {
        finish('time');
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

    const stream = await navigator.mediaDevices.getUserMedia({
        audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true, autoGainControl: true },
    });

    const context = new AudioContext();
    await context.audioWorklet.addModule(root.dataset.worklet);

    const source = context.createMediaStreamSource(stream);
    const capture = new AudioWorkletNode(context, 'pcm-capture', { processorOptions: { targetRate: INPUT_RATE } });
    const silent = context.createGain();
    silent.gain.value = 0;
    source.connect(capture).connect(silent).connect(context.destination);
    capture.port.onmessage = (event) => sendAudio(event.data);

    // La voix est un peu retardée : la lecture labiale du professeur 3D
    // (qui écoute avant ce retard) a le temps de préparer la forme de la bouche.
    const output = context.createGain();
    const delay = context.createDelay(0.5);
    delay.delayTime.value = 0.08;
    const analyser = context.createAnalyser();
    analyser.fftSize = 512;
    output.connect(delay).connect(analyser).connect(context.destination);

    state.audio = { context, stream, source, output, analyser, playHead: 0, sources: new Set() };
    teacher?.attachAudio(context, output, source);
    animateMouth();
}

function base64FromBuffer(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';

    for (let i = 0; i < bytes.length; i += 0x8000) {
        binary += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
    }

    return btoa(binary);
}

function sendAudio(buffer) {
    if (!state.connected || state.ending || state.ws?.readyState !== WebSocket.OPEN) {
        return;
    }

    state.ws.send(JSON.stringify({
        realtimeInput: { audio: { data: base64FromBuffer(buffer), mimeType: `audio/pcm;rate=${INPUT_RATE}` } },
    }));
}

function playAudio(base64) {
    const { context, output, sources } = state.audio;
    const binary = atob(base64);
    const samples = new Int16Array(binary.length >> 1);

    for (let i = 0; i < samples.length; i++) {
        samples[i] = binary.charCodeAt(2 * i) | (binary.charCodeAt(2 * i + 1) << 8);
    }

    const buffer = context.createBuffer(1, samples.length, OUTPUT_RATE);
    const channel = buffer.getChannelData(0);

    for (let i = 0; i < samples.length; i++) {
        channel[i] = samples[i] / 0x8000;
    }

    const node = context.createBufferSource();
    node.buffer = buffer;
    node.connect(output);

    const startAt = Math.max(context.currentTime + 0.02, state.audio.playHead);
    node.start(startAt);
    state.audio.playHead = startAt + buffer.duration;
    sources.add(node);
    node.onended = () => sources.delete(node);
}

function stopPlayback() {
    if (!state.audio) {
        return;
    }

    state.audio.sources.forEach((node) => {
        try { node.stop(); } catch { /* déjà arrêté */ }
    });
    state.audio.sources.clear();
    state.audio.playHead = state.audio.context.currentTime;
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
        requestAnimationFrame(frame);
    };

    requestAnimationFrame(frame);
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

            if (!planned) {
                sendText(isResume ? state.config.cues.resume : state.config.cues.start);
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
 * Le jeton (et la connexion) dure quelques minutes : on en demande un nouveau
 * juste avant son expiration, en reprenant la même conversation.
 */
function scheduleRenewal() {
    clearTimeout(state.renewal);
    const seconds = state.config.token_expires_in - 15;

    if (remainingSeconds() > state.config.token_expires_in + 5) {
        state.renewal = setTimeout(() => renew(), Math.max(5, seconds) * 1000);
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
        for (const part of content.modelTurn?.parts ?? []) {
            if (part.inlineData?.data) {
                playAudio(part.inlineData.data);
            }
        }

        if (content.inputTranscription?.text) {
            teacher?.learnerSpoke();
            state.teacherLine = null;
            subtitle('learner', content.inputTranscription.text);
        }

        if (content.outputTranscription?.text) {
            state.learnerLine = null;
            subtitle('teacher', content.outputTranscription.text);
        }

        if (content.interrupted) {
            stopPlayback();
        }

        if (content.turnComplete) {
            state.teacherLine = null;
            state.learnerLine = null;
        }
    }

    if (message.toolCall?.functionCalls) {
        message.toolCall.functionCalls.forEach(handleFunctionCall);
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

async function handleFunctionCall(call) {
    const args = call.args ?? {};
    let response = { result: 'ok' };

    if (call.name === 'set_expression') {
        // Expression du professeur animé (sans effet sur le visage simple).
        teacher?.expression(args.expression);
    } else if (call.name === 'record_assessment_result' && mode === 'assessment') {
        const result = await post(urls.result, { level: args.level, feedback: args.feedback });

        if (result.ok) {
            showOutcome(`<h2>Votre niveau : ${escapeHtml(result.data.level)}</h2>`
                + `<p><strong>${escapeHtml(result.data.label)}</strong></p>`
                + `<p>${escapeHtml(args.feedback)}</p>`);
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

    if (state.ws?.readyState === WebSocket.OPEN) {
        state.ws.send(JSON.stringify({
            toolResponse: { functionResponses: [{ id: call.id, name: call.name, response }] },
        }));
    }
}

function onClose(event) {
    state.connected = false;

    if (state.ending) {
        return;
    }

    if (remainingSeconds() <= 2) {
        finish('time');

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

    const seconds = remainingSeconds();

    if (!state.warningSent && state.connected && seconds <= state.config.warning_seconds) {
        state.warningSent = true;
        sendText(state.config.cues.warning);
    }

    if (seconds <= 0) {
        finish('time');
    }
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
    ui.back.hidden = false;
    setStatus(reason === 'time' ? 'Temps écoulé' : 'Terminé');

    if (message) {
        showError(message);
    }

    if (state.config) {
        await post(urls.finish, takeTokens());
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
        navigator.sendBeacon(urls.finish, form);
    }
});
