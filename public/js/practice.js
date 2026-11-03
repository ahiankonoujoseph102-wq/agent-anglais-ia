/*
 * « Réviser à voix haute » : un élément du tableau à la fois. Écouter (voix du professeur, gardée par le serveur ;
 * sinon la voix anglaise du téléphone en attendant), « Lentement » (même voix, ralentie sans changer sa
 * hauteur), « M'enregistrer » (micro du téléphone, gardé en mémoire dans la page, JAMAIS envoyé), « Réécouter seulement ma voix »,
 * « Comparer » (le modèle, puis l'élève), « Précédent » / « Suivant ».
 */

/** Adresse de la voix de l'élément « index » (l'adresse donnée par la page vise l'élément 0). */
export function audioUrl(template, index) {
    return String(template).replace(/\/0(\?|$)/, `/${index}$1`);
}

/** Pourcentage de la barre de progression pour l'élément « index » (0 = le premier) sur « count ». */
export function progress(index, count) {
    return count > 0 ? Math.round(((index + 1) / count) * 100) : 0;
}

/** Faut-il encore demander l'état de la voix ? (au moins un élément pas prêt, et pas trop d'essais) */
export function shouldPoll(ready, attempts, maxAttempts = 24) {
    return attempts < maxAttempts && ready.some((value) => !value);
}

function start(root) {
    const items = [...root.querySelectorAll('[data-practice-item]')];
    if (items.length === 0) return;

    const $ = (selector) => root.querySelector(selector);
    const teacher = root.dataset.teacher || '';
    const slowRate = Number(root.dataset.slowRate) || 0.75;
    const recordSeconds = Number(root.dataset.recordSeconds) || 12;
    let ready = [];
    try { ready = JSON.parse(root.dataset.ready || '[]'); } catch { ready = []; }

    const note = $('[data-practice-note]');
    const listen = $('[data-practice-listen]');
    const slow = $('[data-practice-slow]');
    const record = $('[data-practice-record]');
    const recordLabel = $('[data-practice-record-label]');
    const mine = $('[data-practice-mine]');
    const compare = $('[data-practice-compare]');
    const prev = $('[data-practice-prev]');
    const next = $('[data-practice-next]');
    const done = $('[data-practice-done]');
    const controls = $('[data-practice-controls]');
    const progressBox = $('[data-practice-progress]');

    const recordings = new Map();
    let index = 0;
    let player = null;
    let recorder = null;
    let recordTimer = null;
    let token = 0;

    const canRecord = Boolean(navigator.mediaDevices?.getUserMedia && window.MediaRecorder);

    root.classList.add('is-ready');
    controls.hidden = false;
    progressBox.hidden = false;
    if (!canRecord) {
        record.hidden = true;
        mine.hidden = true;
        compare.hidden = true;
    }

    function say(text) {
        note.textContent = text;
    }

    function stopSound() {
        token++;
        if (player) {
            player.pause();
            player = null;
        }
        if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    }

    function playUrl(url, rate, myToken) {
        return new Promise((resolve) => {
            if (myToken !== token) return resolve(false);
            const audio = new Audio(url);
            audio.preservesPitch = true;
            audio.playbackRate = rate;
            audio.onended = () => resolve(true);
            audio.onerror = () => resolve(false);
            player = audio;
            audio.play().catch(() => resolve(false));
        });
    }

    function speakWithPhone(text, rate, myToken) {
        return new Promise((resolve) => {
            if (!('speechSynthesis' in window) || myToken !== token) return resolve(false);
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'en-US';
            const voice = window.speechSynthesis.getVoices().find((item) => item.lang === 'en-US')
                || window.speechSynthesis.getVoices().find((item) => item.lang?.startsWith('en'));
            if (voice) utterance.voice = voice;
            utterance.rate = rate < 1 ? 0.7 : 0.9;
            utterance.onend = () => resolve(true);
            utterance.onerror = () => resolve(false);
            window.speechSynthesis.speak(utterance);
        });
    }

    /** Fait entendre l'élément en cours : voix du professeur si elle est prête, sinon celle du téléphone. */
    async function playModel(rate, myToken) {
        if (ready[index]) {
            if (await playUrl(audioUrl(root.dataset.audioUrl, index), rate, myToken)) return true;
            ready[index] = false;
        }
        const text = items[index].querySelector('.practice-en')?.textContent.trim() || '';
        if (myToken !== token) return false;
        say(`Voix du téléphone, en attendant celle de ${teacher}.`);
        return speakWithPhone(text, rate, myToken);
    }

    async function playMine(myToken) {
        const blob = recordings.get(index);
        if (!blob) return false;
        const url = URL.createObjectURL(blob);
        const result = await playUrl(url, 1, myToken);
        URL.revokeObjectURL(url);
        return result;
    }

    function show(position) {
        stopSound();
        stopRecording();
        index = position;
        items.forEach((item, i) => { item.hidden = i !== index; });
        $('[data-practice-count]').textContent = `${index + 1} sur ${items.length}`;
        $('[data-practice-bar]').style.setProperty('--p', `${progress(index, items.length)}%`);
        prev.disabled = index === 0;
        next.innerHTML = next.innerHTML.replace(/^(\s*)(Suivant|Terminer)/, `$1${index === items.length - 1 ? 'Terminer' : 'Suivant'}`);
        const hasMine = recordings.has(index);
        mine.disabled = !hasMine;
        compare.disabled = !hasMine;
        say('');
    }

    async function startRecording() {
        stopSound();
        let stream;
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } });
        } catch {
            say('Le micro n\'est pas autorisé. Touchez « Autoriser » quand le téléphone demande le micro.');
            return;
        }
        const chunks = [];
        const position = index;
        recorder = new MediaRecorder(stream);
        recorder.ondataavailable = (event) => { if (event.data.size > 0) chunks.push(event.data); };
        recorder.onstop = () => {
            stream.getTracks().forEach((track) => track.stop());
            if (chunks.length > 0) {
                recordings.set(position, new Blob(chunks, { type: recorder?.mimeType || chunks[0].type }));
            }
            recorder = null;
            clearTimeout(recordTimer);
            record.classList.remove('is-recording');
            recordLabel.textContent = 'M\'enregistrer';
            if (position === index && recordings.has(position)) {
                mine.disabled = false;
                compare.disabled = false;
                say('Touchez « Comparer » pour écouter la différence.');
            }
        };
        recorder.start();
        record.classList.add('is-recording');
        recordLabel.textContent = 'Arrêter';
        say(`Parlez maintenant : ${recordSeconds} secondes au plus.`);
        recordTimer = setTimeout(stopRecording, recordSeconds * 1000);
    }

    function stopRecording() {
        clearTimeout(recordTimer);
        if (recorder && recorder.state !== 'inactive') recorder.stop();
    }

    listen.addEventListener('click', () => { stopSound(); playModel(1, token); });
    slow.addEventListener('click', () => { stopSound(); playModel(slowRate, token); });
    record.addEventListener('click', () => { if (recorder) stopRecording(); else startRecording(); });
    mine.addEventListener('click', () => { stopSound(); playMine(token); });
    compare.addEventListener('click', async () => {
        stopSound();
        const myToken = token;
        if (await playModel(1, myToken) && myToken === token) {
            await new Promise((resolve) => setTimeout(resolve, 400));
            await playMine(myToken);
        }
    });
    prev.addEventListener('click', () => { if (index > 0) show(index - 1); });
    next.addEventListener('click', () => {
        if (index < items.length - 1) {
            show(index + 1);
            return;
        }
        stopSound();
        stopRecording();
        controls.hidden = true;
        progressBox.hidden = true;
        items.forEach((item) => { item.hidden = true; });
        done.hidden = false;
        done.focus({ preventScroll: true });
        // Le message de fin en haut de l'écran, sous l'en-tête fixe.
        window.scrollTo({ top: Math.max(0, root.getBoundingClientRect().top + window.scrollY - 80), behavior: 'instant' });
    });
    $('[data-practice-restart]').addEventListener('click', () => {
        done.hidden = true;
        controls.hidden = false;
        progressBox.hidden = false;
        show(0);
    });

    // La voix du professeur se prépare après l'ouverture de la page : on demande où elle en est, de temps en temps.
    let attempts = 0;
    async function poll() {
        if (!shouldPoll(ready, attempts)) return;
        attempts++;
        try {
            const response = await fetch(root.dataset.statusUrl, { headers: { Accept: 'application/json' } });
            if (response.ok) {
                const data = await response.json();
                if (Array.isArray(data.ready)) ready = data.ready.map((value, i) => Boolean(value) || Boolean(ready[i]));
            }
        } catch { /* réseau : on réessaiera */ }
        setTimeout(poll, 8000);
    }

    // Les voix du téléphone se chargent parfois après la page.
    if ('speechSynthesis' in window) window.speechSynthesis.getVoices();

    show(0);
    setTimeout(poll, 4000);
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-practice]').forEach(start);
}
