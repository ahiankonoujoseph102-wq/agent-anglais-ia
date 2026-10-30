/*
 * Page de bienvenue du professeur (Teacher Joe ou Teacher Rose) : lit le message vocal (une suite de morceaux : l'ouverture avec le
 * prénom, puis les paragraphes), avec le professeur 3D dont la bouche suit la voix (même technique que
 * l'écran de conversation : teacher-view.js, teacher-3d.js, HeadAudio), des sous-titres, « Pause » et
 * « Passer ». À la fin, le bouton de la prochaine étape apparaît, la page y descend et lui donne le focus.
 *
 * Départ en moins d'une seconde : dès l'ouverture de la page, les premiers morceaux et la voix de chaque
 * professeur sont téléchargés en version légère (µ-law, deux fois plus petite ; version complète si le
 * navigateur ne la lit pas) et décodés d'avance ; rien n'est JOUÉ avant le toucher. Les suivants se chargent
 * pendant que les premiers sont dits. Le serveur ne fait jamais de synthèse pendant l'écoute (« pret=1 ») : la
 * salutation au prénom pas encore gardée se prépare à part, la page la redemande quelques fois et, si elle n'est
 * pas là au toucher, le message commence au paragraphe suivant. Un morceau indisponible est sauté (il reste écrit
 * dans les sous-titres) ; le message d'erreur n'apparaît que si rien n'a pu être joué.
 */
const root = document.getElementById('welcome');

/** Morceaux préchargés, au plus, avant le toucher (les suivants se chargent pendant la lecture). */
const PRELOAD = 3;

/** Salutation en préparation : redemandée toutes les 2,5 secondes, 10 fois au plus. */
const SOON_TRIES = 10;
const SOON_EVERY_MS = 2500;

// Décodeur sans son (aucun toucher nécessaire) : les morceaux sont prêts à jouer avant le toucher.
let decoder = null;

try {
    const Offline = window.OfflineAudioContext || window.webkitOfflineAudioContext;
    decoder = Offline ? new Offline(1, 1, 24000) : null;
} catch {
    decoder = null;
}

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function withLight(url) {
    const light = new URL(url, window.location.href);
    light.searchParams.set('leger', '1');

    return light.toString();
}

/**
 * Télécharge et décode un son : version légère d'abord, version complète si le navigateur ne la lit pas.
 * Renvoie un AudioBuffer, ou null (pas prêt, réseau).
 */
async function fetchSound(url, context = decoder) {
    for (const candidate of [withLight(url), url]) {
        let data;

        try {
            const response = await fetch(candidate, { credentials: 'same-origin' });

            if (!response.ok) {
                return null;
            }

            data = await response.arrayBuffer();
        } catch {
            return null;
        }

        try {
            return await decodeWith(context, data);
        } catch {
            // Version légère illisible ici : la version complète.
        }
    }

    return null;
}

function decodeWith(context, data) {
    if (!context) {
        return Promise.reject(new Error('Aucun décodeur.'));
    }

    // Anciens Safari : decodeAudioData à fonctions de rappel seulement.
    return new Promise((resolve, reject) => {
        const result = context.decodeAudioData(data, resolve, reject);
        result?.then?.(resolve, reject);
    });
}

if (root) {
    run();
    voiceSamples();
}

/**
 * « Sa voix » (choix du professeur) : court extrait de la voix de chaque professeur, préchargé dès l'ouverture,
 * joué au toucher, un seul à la fois (un second toucher l'arrête). Un extrait illisible ne bloque rien.
 */
function voiceSamples() {
    const buttons = [...root.querySelectorAll('[data-voice-sample]')];
    const sounds = new Map(buttons.map((button) => [button, fetchSound(button.dataset.voiceSample)]));
    let context = null;
    let playing = null;

    const stop = () => {
        if (playing) {
            try { playing.source.stop(); } catch { /* déjà fini */ }

            playing.button.setAttribute('aria-pressed', 'false');
            playing = null;
        }
    };

    for (const button of buttons) {
        button.setAttribute('aria-pressed', 'false');
        button.addEventListener('click', async () => {
            const again = playing?.button === button;
            stop();

            if (again) {
                return;
            }

            // Contexte créé pendant le toucher (obligatoire pour le son), puis gardé.
            if (!context) {
                const Context = window.AudioContext || window.webkitAudioContext;
                context = new Context({ latencyHint: 'interactive' });
            }

            context.resume?.().catch(() => {});
            const buffer = await sounds.get(button);

            if (!buffer) {
                button.hidden = true;

                return;
            }

            const source = context.createBufferSource();
            source.buffer = buffer;
            source.connect(context.destination);
            source.onended = () => {
                if (playing?.source === source) {
                    stop();
                }
            };
            source.start();
            playing = { button, source };
            button.setAttribute('aria-pressed', 'true');
        });
    }

    // Le message principal coupe l'extrait en cours.
    root.addEventListener('welcome:play', stop);
}

async function run() {
    const pieces = JSON.parse(root.dataset.pieces);
    const audioConfig = JSON.parse(root.dataset.audio);
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    const { expressionFor } = await import(root.dataset.teacherCues);

    const $ = (id) => document.getElementById(id);
    const ui = {
        start: $('welcome-start'), play: $('welcome-play'), skipStart: $('welcome-skip-start'),
        controls: $('welcome-controls'), pause: $('welcome-pause'), skip: $('welcome-skip'),
        status: $('welcome-status'), error: $('welcome-error'), partial: $('welcome-partial'), subtitles: $('welcome-subtitles'),
        next: $('welcome-next'), mouth: $('welcome-mouth'),
    };

    // Professeur 3D, chargé en arrière-plan ; un emplacement de chargement en attendant, le visage simple en repli.
    let teacher = null;

    if (root.dataset.teacher3d && root.dataset.teacherView) {
        import(root.dataset.teacherView)
            .then(({ createTeacherView }) => {
                teacher = createTeacherView(root, JSON.parse(root.dataset.teacher3d));

                if (graph) {
                    teacher.attachAudio(graph.context, graph.output, null);
                }
            })
            .catch((error) => {
                console.warn('Professeur 3D indisponible :', error);
                root.dataset.teacher = 'svg';
            });
    }

    // Sous-titres : une ligne par morceau, une étiquette par phrase.
    const sentences = pieces.map((piece, index) => {
        const paragraph = document.createElement('p');
        paragraph.dataset.piece = String(index);
        const list = piece.text.split(/(?<=[.!?…»])\s+(?=[A-ZÀ-ÖØ-Þ«])/u).filter(Boolean);
        const spans = list.map((text) => {
            const span = document.createElement('span');
            span.className = 'sentence';
            span.textContent = text;

            return { text, span, expressed: false };
        });
        spans.forEach(({ span }, position) => {
            if (position > 0) {
                paragraph.append(' ');
            }

            paragraph.append(span);
        });

        return { paragraph, spans, start: 0, end: 0 };
    });
    ui.subtitles.replaceChildren(...sentences.map(({ paragraph }) => paragraph));
    ui.start.hidden = false;


    let graph = null;
    let finished = false;
    let skipped = false;
    let raf = 0;
    // Lecture : morceaux programmés (début et fin dans l'horloge audio), position du prochain, pause.
    const play = { timeline: [], cursor: 0, paused: false, pausedAt: 0 };

    // Préchargement : un emplacement par morceau (promesse, son décodé, terminé).
    let skippedOpening = false;
    const slots = pieces.map(() => ({ promise: null, buffer: null, done: false }));

    // L'un après l'autre, le premier d'abord (sur un réseau lent, le partage ralentirait le premier) ; la
    // salutation en préparation n'est pas attendue.
    (async () => {
        for (let index = 0; index < Math.min(PRELOAD, pieces.length); index++) {
            const loading = want(index);

            if (!pieces[index].soon) {
                await loading;
            }
        }
    })();

    function showNext() {
        ui.next.classList.add('is-shown');
        ui.next.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
        ui.next.focus({ preventScroll: true });
    }

    function finish() {
        if (finished) {
            return;
        }

        finished = true;
        cancelAnimationFrame(raf);
        play.timeline.forEach((item) => stopItem(item));
        ui.controls.hidden = true;
        ui.start.hidden = true;
        ui.status.hidden = true;
        sentences.forEach(({ spans }) => spans.forEach(({ span }) => span.classList.remove('is-now')));
        teacher?.expression('neutral');
        ui.mouth?.setAttribute('ry', 3);
        graph?.context.close?.().catch(() => {});
        teacher?.detachAudio();
        showNext();
    }

    function fail() {
        ui.error.hidden = false;
        finish();
    }

    // « Passer » avant d'avoir écouté : on va directement au bouton.
    ui.skipStart.addEventListener('click', () => { skipped = true; finish(); });
    ui.skip.addEventListener('click', () => { skipped = true; finish(); });

    // « Pause » : les sons programmés sont arrêtés, puis reprogrammés là où ils en étaient (le contexte audio
    // n'est pas suspendu : le professeur 3D et sa lecture labiale restent branchés, la reprise est immédiate).
    ui.pause.addEventListener('click', () => {
        if (!graph || finished) {
            return;
        }

        const now = graph.context.currentTime;

        if (!play.paused) {
            play.paused = true;
            play.pausedAt = now;

            for (const item of play.timeline) {
                stopItem(item);
            }
        } else {
            const resumeAt = now + 0.05;
            const shift = resumeAt - play.pausedAt;
            play.paused = false;
            play.cursor = Math.max(play.cursor + shift, resumeAt);

            for (const item of play.timeline) {
                if (item.end <= play.pausedAt) {
                    continue;
                }

                // Le morceau repart de l'endroit où il s'était arrêté ; ceux qui suivent gardent leur écart.
                const offset = Math.max(0, play.pausedAt - item.start);
                item.start += shift;
                item.end += shift;
                shiftSubtitles(item.index, shift);
                startItem(item, Math.max(item.start, resumeAt), offset);
            }
        }

        const paused = play.paused;
        ui.pause.setAttribute('aria-pressed', String(paused));
        ui.pause.querySelector('[data-pause-label]').hidden = paused;
        ui.pause.querySelector('[data-resume-label]').hidden = !paused;
    });

    ui.play.addEventListener('click', async () => {
        ui.play.disabled = true;
        ui.start.hidden = true;
        ui.controls.hidden = false;
        ui.status.textContent = 'Un instant…';
        ui.status.hidden = false;

        try {
            await startPlayback();
        } catch (error) {
            console.warn('Message de bienvenue indisponible :', error);
            fail();
        }
    });

    async function startPlayback() {
        root.dispatchEvent(new CustomEvent('welcome:play'));

        // Le contexte est créé pendant le toucher (obligatoire pour que le son soit autorisé).
        const Context = window.AudioContext || window.webkitAudioContext;
        const context = new Context({ latencyHint: 'interactive' });
        context.resume?.().catch(() => {});

        // Voix → volume (fichiers déjà à −14 LUFS) → limiteur → sortie. Le professeur 3D écoute la sortie
        // avant le petit retard ajouté au haut-parleur : sa bouche a le temps de se préparer.
        const boost = context.createGain();
        boost.gain.value = audioConfig.voice_gain;
        const limiter = context.createDynamicsCompressor();
        limiter.threshold.value = -3;
        limiter.knee.value = 0;
        limiter.ratio.value = 20;
        limiter.attack.value = 0.002;
        limiter.release.value = 0.15;
        const output = context.createGain();
        boost.connect(limiter).connect(output);
        const delay = context.createDelay(0.5);
        delay.delayTime.value = 0.08;
        const analyser = context.createAnalyser();
        analyser.fftSize = 512;
        output.connect(delay).connect(analyser).connect(context.destination);
        graph = { context, output, boost, analyser };
        teacher?.attachAudio(context, output, null);
        animateMouth(analyser);

        teacher?.expression('welcome');

        // Ce qui est prêt part tout de suite : la salutation encore en préparation est sautée (elle reste écrite).
        if (!slots[0].buffer && pieces[0].soon) {
            skippedOpening = true;
        }

        play.cursor = context.currentTime + 0.03;
        let missing = 0;

        for (let index = 0; index < pieces.length; index++) {
            // Le morceau suivant se charge pendant que celui-ci est dit.
            want(index + 1);
            const buffer = index === 0 && skippedOpening ? null : await want(index);

            if (finished) {
                return;
            }

            if (buffer === null) {
                // Morceau indisponible : il reste écrit, la suite est jouée.
                if (index > 0 || !skippedOpening) {
                    missing++;
                }

                sentences[index].paragraph.classList.add('is-text-only');
                continue;
            }

            ui.status.hidden = true;
            // En pause, le temps est figé : le morceau attend la reprise.
            const earliest = play.paused ? play.pausedAt : context.currentTime + 0.03;
            const start = Math.max(play.cursor, earliest);
            const item = { index, buffer, start, end: start + buffer.duration, last: false, source: null };
            play.timeline.push(item);
            schedule(index, start, buffer.duration);
            play.cursor = item.end + 0.35;

            if (!play.paused) {
                startItem(item, start, 0);
            }

            if (play.timeline.length === 1) {
                raf = requestAnimationFrame(follow);
            }
        }

        const lastItem = play.timeline.at(-1);

        // Rien n'a pu être joué : le texte reste lisible, avec le message d'erreur.
        if (!lastItem) {
            return fail();
        }

        if (missing > 0) {
            ui.partial.hidden = false;
        }

        // Fin du dernier morceau joué : le bouton de la prochaine étape apparaît.
        lastItem.last = true;

        if (lastItem.source) {
            lastItem.source.onended = onLastEnded;
        }

        if (!play.paused && graph.context.currentTime >= lastItem.end) {
            finish();
        }
    }

    function onLastEnded() {
        if (!skipped && !play.paused) {
            finish();
        }
    }

    // Programme (ou reprogramme) un morceau dans l'horloge audio, à partir de « offset » secondes.
    function startItem(item, when, offset) {
        const source = graph.context.createBufferSource();
        source.buffer = item.buffer;
        source.connect(graph.boost);

        if (item.last) {
            source.onended = onLastEnded;
        }

        source.start(when, offset);
        item.source = source;
    }

    function stopItem(item) {
        if (!item.source) {
            return;
        }

        item.source.onended = null;

        try { item.source.stop(); } catch { /* déjà arrêté */ }

        item.source = null;
    }

    /**
     * Le son décodé d'un morceau (promesse partagée : un seul téléchargement). Paragraphe pas encore préparé :
     * null, sans demande. Salutation en préparation (« soon ») : redemandée quelques fois, tant qu'elle peut
     * encore servir (avant le toucher).
     */
    function want(index) {
        if (index >= pieces.length) {
            return Promise.resolve(null);
        }

        const slot = slots[index];

        if (slot.promise) {
            return slot.promise;
        }

        const piece = pieces[index];

        slot.promise = (async () => {
            if (!piece.ready && !piece.soon) {
                return null;
            }

            const tries = piece.ready ? 1 : SOON_TRIES;

            for (let attempt = 0; attempt < tries; attempt++) {
                if (attempt > 0) {
                    await sleep(SOON_EVERY_MS);
                }

                if (skippedOpening || finished) {
                    return null;
                }

                const buffer = await fetchSound(piece.url);

                if (buffer) {
                    return buffer;
                }
            }

            return null;
        })().then((buffer) => {
            slot.buffer = buffer;
            slot.done = true;

            return buffer;
        });

        return slot.promise;
    }

    // Sous-titres : l'heure (dans l'horloge audio) de chaque phrase, répartie selon sa longueur.
    function schedule(index, at, duration) {
        const entry = sentences[index];
        const total = entry.spans.reduce((sum, { text }) => sum + text.length, 0) || 1;
        let elapsed = 0;
        entry.start = at;
        entry.end = at + duration;

        for (const sentence of entry.spans) {
            sentence.from = at + (elapsed / total) * duration;
            elapsed += sentence.text.length;
            sentence.to = at + (elapsed / total) * duration;
        }
    }

    function shiftSubtitles(index, shift) {
        for (const sentence of sentences[index].spans) {
            sentence.from += shift;
            sentence.to += shift;
        }
    }

    let current = null;

    function follow() {
        if (finished) {
            return;
        }

        // En pause, les sous-titres restent sur la phrase en cours.
        if (play.paused) {
            raf = requestAnimationFrame(follow);

            return;
        }

        const now = graph.context.currentTime;

        for (const entry of sentences) {
            for (const sentence of entry.spans) {
                if (sentence.from === undefined) {
                    continue;
                }

                const active = now >= sentence.from && now < sentence.to;

                if (active && current !== sentence) {
                    current?.span.classList.remove('is-now');
                    sentence.span.classList.add('is-now');
                    sentence.span.scrollIntoView({ block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth' });
                    current = sentence;

                    if (!sentence.expressed) {
                        sentence.expressed = true;
                        const expression = expressionFor(sentence.text) ?? (/félicitations|bravo|c'est parti|allons-y/i.test(sentence.text) ? 'happy' : null);

                        if (expression) {
                            teacher?.expression(expression);
                        }
                    }
                }
            }
        }

        raf = requestAnimationFrame(follow);
    }

    // Repli : bouche du visage simple d'après le volume de la voix.
    function animateMouth(analyser) {
        const samples = new Uint8Array(analyser.fftSize);
        let opening = 0;

        const frame = () => {
            if (finished || !ui.mouth) {
                return;
            }

            analyser.getByteTimeDomainData(samples);
            let sum = 0;

            for (const value of samples) {
                const centered = (value - 128) / 128;
                sum += centered * centered;
            }

            opening = opening * 0.6 + Math.min(1, Math.sqrt(sum / samples.length) * 5) * 0.4;
            ui.mouth.setAttribute('ry', (3 + opening * 17).toFixed(1));
            requestAnimationFrame(frame);
        };

        frame();
    }
}
