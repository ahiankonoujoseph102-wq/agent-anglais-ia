/*
 * Page de bienvenue de Teacher Joe : lit le message vocal (une suite de morceaux : l'ouverture avec le
 * prénom, puis les paragraphes), avec le professeur 3D dont la bouche suit la voix (même technique que
 * l'écran de conversation : teacher-view.js, teacher-3d.js, HeadAudio), des sous-titres, « Pause » et
 * « Passer ». À la fin, le bouton de la prochaine étape apparaît, la page y descend et lui donne le focus.
 *
 * Les morceaux sont demandés au serveur l'un après l'autre (le suivant se charge pendant que le précédent
 * est dit) : rien n'est lu avant le toucher, et aucun fichier vidéo n'est chargé. Un morceau indisponible
 * (salutation en échec, paragraphe pas encore préparé) est sauté : il reste écrit dans les sous-titres et la
 * suite est jouée ; le message d'erreur n'apparaît que si rien n'a pu être joué.
 */
const root = document.getElementById('welcome');

if (root) {
    run();
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
        // Le contexte est créé pendant le toucher (obligatoire pour que le son soit autorisé).
        const Context = window.AudioContext || window.webkitAudioContext;
        const context = new Context({ latencyHint: 'playback' });

        // Voix → volume (LIVE_VOICE_GAIN) → limiteur → sortie. Le professeur 3D écoute la sortie
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

        // Chaque morceau : demandé au serveur, décodé, puis programmé à la suite du précédent.
        play.cursor = context.currentTime + 0.1;
        let pending = load(0);
        let missing = 0;

        for (let index = 0; index < pieces.length; index++) {
            const buffer = await pending;

            if (finished) {
                return;
            }

            // Le morceau suivant se charge pendant que celui-ci est dit.
            pending = index + 1 < pieces.length ? load(index + 1) : null;

            if (buffer === null) {
                // Morceau indisponible : il reste écrit, la suite est jouée.
                missing++;
                sentences[index].paragraph.classList.add('is-text-only');
                continue;
            }

            ui.status.hidden = true;
            // En pause, le temps est figé : le morceau attend la reprise.
            const earliest = play.paused ? play.pausedAt : context.currentTime + 0.05;
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

    async function load(index) {
        // Paragraphe pas encore préparé : aucune demande, il reste en texte.
        if (pieces[index].ready === false) {
            return null;
        }

        try {
            const response = await fetch(pieces[index].url, { credentials: 'same-origin' });

            if (!response.ok) {
                return null;
            }

            return await graph.context.decodeAudioData(await response.arrayBuffer());
        } catch {
            return null;
        }
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
