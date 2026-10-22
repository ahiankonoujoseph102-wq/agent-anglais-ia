/*
 * « Enregistrer ma séance » : vidéo de la séance fabriquée ENTIÈREMENT dans le navigateur, jamais envoyée
 * au serveur.
 *
 * Image : un canevas vertical 9:16 (config/platform.php → recording), en écran partagé. En haut (board_share
 * de la hauteur), le TABLEAU de la séance, dessiné ici à partir de ses données (#board : titre, étape, phrases,
 * mots, verbes, règle, piège), même quand il est replié à l'écran ; l'anglais en orange, l'élément expliqué
 * surligné, mis à jour en même temps que l'écran. En bas, le professeur vu par une caméra réservée à la vidéo
 * (teacher-3d.js → renderVideo, rendu juste après l'image de l'écran, qui ne change pas), ou le visage SVG.
 * Sans tableau (évaluation avant le verdict, tableau vide) : le professeur sur toute la hauteur, avec une
 * transition douce quand le tableau apparaît ou disparaît. Discrets : le nom de la plateforme en haut,
 * l'adresse du site en bas.
 *
 * Son : une sortie « MediaStreamDestination » du contexte audio de la séance reçoit la voix du professeur
 * (après le retard, telle qu'on l'entend) et le micro de l'apprenant. Elle n'est branchée sur AUCUN
 * haut-parleur : rien n'est rejoué, donc aucun écho, et la chaîne de la séance (demi-duplex, envoi au
 * fournisseur) n'est pas touchée. Le micro est baissé dans la vidéo quand la porte du micro est fermée (voix
 * du professeur, pause) : sur Android, sans annulation d'écho, il entendrait sinon le haut-parleur.
 *
 * Tout échec reste ici : la séance n'est jamais bloquée ni raccourcie. Fonctions pures testées par
 * tests/js/session-recorder.test.mjs.
 */

/** Formats essayés, du préféré au moins bon : MP4 (lisible partout, WhatsApp compris), sinon WebM. */
export const MIME_TYPES = [
    'video/mp4;codecs=avc1.42E01E,mp4a.40.2',
    'video/mp4;codecs=avc1,mp4a.40.2',
    'video/mp4',
    'video/webm;codecs=vp9,opus',
    'video/webm;codecs=vp8,opus',
    'video/webm',
];

/** Premier format que le navigateur sait enregistrer, ou null. */
export function pickMimeType(isTypeSupported) {
    for (const type of MIME_TYPES) {
        try {
            if (isTypeSupported(type)) {
                return type;
            }
        } catch { /* format refusé */ }
    }

    return null;
}

export function extensionFor(mimeType) {
    return /^video\/mp4/.test(mimeType ?? '') ? 'mp4' : 'webm';
}

/** Nom clair du fichier : « talkami-seance-22-10-2026-14h05.mp4 ». */
export function fileName(prefix, date, extension) {
    const two = (n) => String(n).padStart(2, '0');

    return `${prefix || 'seance'}-${two(date.getDate())}-${two(date.getMonth() + 1)}-${date.getFullYear()}-${two(date.getHours())}h${two(date.getMinutes())}.${extension}`;
}

/** Durée affichée pendant l'enregistrement : « 2:05 ». */
export function formatElapsed(seconds) {
    const total = Math.max(0, Math.floor(seconds));

    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}

/** Taille en toutes lettres : « 12 mégaoctets », « moins d'un mégaoctet ». */
export function formatSize(bytes) {
    const megabytes = Math.round(bytes / (1024 * 1024));

    if (megabytes < 1) {
        return 'moins d\'un mégaoctet';
    }

    return megabytes === 1 ? '1 mégaoctet' : `${megabytes} mégaoctets`;
}

/**
 * Le navigateur sait-il fabriquer la vidéo ? (MediaRecorder, capture d'un canevas, sortie audio en flux, et
 * un format connu.)
 */
export function supportInfo(env = globalThis) {
    const recorder = env.MediaRecorder;
    const canvas = env.HTMLCanvasElement?.prototype;
    const audio = env.AudioContext?.prototype ?? env.webkitAudioContext?.prototype;

    if (!recorder || typeof recorder.isTypeSupported !== 'function' || typeof canvas?.captureStream !== 'function'
        || typeof audio?.createMediaStreamDestination !== 'function') {
        return null;
    }

    return pickMimeType((type) => recorder.isTypeSupported(type));
}

/** Courbe douce des transitions (0 → 1). */
export function easeInOut(x) {
    const t = Math.min(1, Math.max(0, x));

    return t < 0.5 ? 2 * t * t : 1 - ((-2 * t + 2) ** 2) / 2;
}

/** Cadrage intermédiaire entre deux cadrages de caméra (t de 0 à 1). */
export function mixCamera(a, b, t) {
    const mix = (x, y) => x + (y - x) * t;

    return {
        target: a.target.map((value, i) => mix(value, b.target[i])),
        half_height: mix(a.half_height, b.half_height),
        half_width: mix(a.half_width, b.half_width),
    };
}

/**
 * Coupe un texte en lignes de maxWidth pixels au plus (measure : largeur d'un texte), maxLines lignes au plus ;
 * un mot trop long est coupé, une ligne de trop finit par « … ».
 */
export function wrapLines(text, maxWidth, measure, maxLines = 3) {
    const lines = [];
    let line = '';

    const push = (value) => {
        lines.push(value);
        line = '';
    };

    for (const word of String(text ?? '').trim().split(/\s+/).filter(Boolean)) {
        const candidate = line ? `${line} ${word}` : word;

        if (measure(candidate) <= maxWidth) {
            line = candidate;
            continue;
        }

        if (line) {
            push(line);
        }

        // Mot plus large que la ligne : coupé lettre par lettre.
        let rest = word;

        while (measure(rest) > maxWidth && rest.length > 1) {
            let cut = rest.length - 1;

            while (cut > 1 && measure(rest.slice(0, cut)) > maxWidth) {
                cut--;
            }

            push(rest.slice(0, cut));
            rest = rest.slice(cut);
        }

        line = rest;
    }

    if (line) {
        lines.push(line);
    }

    if (lines.length <= maxLines) {
        return lines;
    }

    const kept = lines.slice(0, maxLines);
    let last = kept[maxLines - 1];

    while (last.length > 1 && measure(`${last}…`) > maxWidth) {
        last = last.slice(0, -1).trimEnd();
    }

    kept[maxLines - 1] = `${last}…`;

    return kept;
}

/**
 * Réglage de l'image vidéo de le professeur d'après son coût moyen (millisecondes) : au-delà du budget, d'abord
 * moins de pixels (1 → 0,75 → 0,5), puis une image sur deux. Ne remonte jamais.
 */
export function adaptQuality({ scale, every }, mean, budget) {
    if (mean <= budget) {
        return { scale, every };
    }

    if (scale > 0.5) {
        return { scale: scale > 0.75 ? 0.75 : 0.5, every };
    }

    return { scale, every: Math.min(4, every * 2) };
}

/** Tailles du tableau pour une taille de texte anglais s (pixels). */
export function boardMetrics(s) {
    return {
        en: s,
        fr: Math.round(s * 0.66),
        title: Math.round(s * 0.9),
        heading: Math.round(s * 0.54),
        small: Math.round(s * 0.56),
        header: Math.round(s * 1.3),
        pad: Math.round(s * 0.62),
        itemPad: Math.round(s * 0.2),
        gap: Math.round(s * 0.14),
        line: 1.2,
    };
}

/**
 * Mise en page du tableau dans width × height : la plus grande taille de texte (sizes.max → sizes.min) où tout
 * tient ; sinon, à la taille sizes.window, seulement les éléments autour de celui que le professeur explique.
 * model : {title, phase, sections: [{heading, items: [{en, fr, lit}]}], pitfall} ; measure(texte, taille,
 * graisse) : largeur en pixels. Renvoie {size, metrics, blocks (avec y), windowed}.
 */
export function layoutBoard(model, { width, height, showFr = true, sizes = { max: 34, min: 24, window: 28 } }, measure) {
    const items = [];

    model.sections.forEach((section, sectionIndex) => {
        section.items.forEach((item) => items.push({ ...item, section: sectionIndex }));
    });

    const build = (s, range = null) => {
        const m = boardMetrics(s);
        const inner = width - 2 * m.pad;
        const blocks = [];
        let y = m.header;

        const add = (block, h) => {
            blocks.push({ ...block, y, h });
            y += h;
        };

        if (model.title) {
            const lines = wrapLines(model.title, inner, (t) => measure(t, m.title, 700), 2);
            add({ type: 'title', lines }, Math.round(lines.length * m.title * m.line + m.gap * 2));
        }

        const chosen = range ? items.slice(range[0], range[1] + 1) : items;
        let section = -1;

        for (const item of chosen) {
            if (item.section !== section) {
                section = item.section;
                const heading = model.sections[section]?.heading;

                if (heading) {
                    add({ type: 'heading', text: heading }, Math.round(m.heading * 1.9));
                }
            }

            const en = wrapLines(item.en, inner - m.itemPad, (t) => measure(t, m.en, 600), 3);
            const fr = showFr && item.fr ? wrapLines(item.fr, inner - m.itemPad, (t) => measure(t, m.fr, 400), 2) : [];
            const h = Math.round(en.length * m.en * m.line + fr.length * m.fr * m.line + m.itemPad * 2 + m.gap);
            add({ type: 'item', en, fr, lit: !!item.lit }, h);
        }

        if (!range && model.pitfall) {
            add({ type: 'heading', text: 'Attention, piège' }, Math.round(m.heading * 1.9));
            const lines = wrapLines(model.pitfall, inner, (t) => measure(t, m.fr, 400), 3);
            add({ type: 'pitfall', lines }, Math.round(lines.length * m.fr * m.line + m.gap * 2));
        }

        return { size: s, metrics: m, blocks, bottom: y + m.pad / 2 };
    };

    for (let s = sizes.max; s >= sizes.min; s -= 2) {
        const layout = build(s);

        if (layout.bottom <= height) {
            return { ...layout, windowed: false };
        }
    }

    // Trop long : les éléments autour de celui qui est expliqué (ou le début), à une taille lisible.
    const focus = Math.max(0, items.findIndex((item) => item.lit));
    let range = [Math.max(0, focus - 1), Math.max(0, focus - 1)];
    const fits = (r) => build(sizes.window, r).bottom <= height;

    while (range[1] + 1 < items.length && fits([range[0], range[1] + 1])) {
        range = [range[0], range[1] + 1];
    }

    while (range[0] > 0 && fits([range[0] - 1, range[1]])) {
        range = [range[0] - 1, range[1]];
    }

    if (range[1] < focus) {
        range = [focus, focus];
    }

    return { ...build(sizes.window, items.length ? range : null), windowed: true };
}

export class SessionRecorder {
    /**
     * @param {object} config réglages de App\Support\SessionRecording::clientConfig()
     * @param {{svg: SVGElement|null, mouth: Element|null, mimeType: string, board?: Element|null}} options
     */
    constructor(config, { svg = null, mouth = null, mimeType, board = null }) {
        this.config = config;
        this.svg = svg;
        this.mouth = mouth;
        this.mimeType = mimeType;
        this.chunks = [];
        this.teacherFrameAt = 0;
        this.startedAt = 0;
        this.recorder = null;
        this.boardEl = board;
        this.boardDirty = true;
        this.boardLayout = null;
        // Part du tableau : 0 (le professeur sur toute la hauteur) à 1 (écran partagé), animée.
        this.split = { value: 0, from: 0, to: 0, at: 0 };
        // Protection de la séance : coût mesuré de chaque image 3D de la vidéo ; trop cher, la définition de
        // le professeur baisse (scale), puis une image sur deux seulement (every).
        this.cost = { samples: [], scale: 1, every: 1, count: 0 };
    }

    /** Démarre l'enregistrement sur le contexte audio de la séance. Lève une erreur si c'est impossible. */
    start({ context, voiceNode, micNode }) {
        const { width, height, fps } = this.config;

        this.canvas = document.createElement('canvas');
        this.canvas.width = width;
        this.canvas.height = height;
        this.g = this.canvas.getContext('2d');
        this.boardCanvas = document.createElement('canvas');
        this.boardCanvas.width = width;
        this.boardCanvas.height = this.boardHeight();
        this.staging = document.createElement('canvas');
        this.font = getComputedStyle(document.body).fontFamily || 'sans-serif';
        this.colors = this.readColors();
        this.svgShapes = this.readSvg();
        this.setBoard(this.boardEl);
        // Première image : directement dans la bonne disposition, sans transition.
        this.updateSplit(performance.now());
        this.split = { value: this.split.to, from: this.split.to, to: this.split.to, at: 0 };

        this.context = context;
        this.destination = context.createMediaStreamDestination();
        this.voiceTap = context.createGain();
        this.learnerTap = context.createGain();
        // Limiteur du mélange : les deux voix ensemble ne saturent jamais la vidéo.
        this.limiter = context.createDynamicsCompressor();
        this.limiter.threshold.value = -6;
        this.limiter.knee.value = 0;
        this.limiter.ratio.value = 20;
        this.limiter.attack.value = 0.003;
        this.limiter.release.value = 0.2;
        this.limiter.connect(this.destination);
        voiceNode.connect(this.voiceTap).connect(this.limiter);
        micNode.connect(this.learnerTap).connect(this.limiter);
        this.links = [[voiceNode, this.voiceTap], [micNode, this.learnerTap]];

        this.draw(null);
        this.video = this.canvas.captureStream(fps);
        const stream = new MediaStream([...this.video.getVideoTracks(), ...this.destination.stream.getAudioTracks()]);

        this.recorder = new MediaRecorder(stream, {
            mimeType: this.mimeType,
            videoBitsPerSecond: this.config.video_bits_per_second,
            audioBitsPerSecond: this.config.audio_bits_per_second,
        });
        this.recorder.ondataavailable = (event) => {
            if (event.data?.size > 0) {
                this.chunks.push(event.data);
            }
        };
        this.done = new Promise((resolve) => {
            this.recorder.onstop = () => resolve(this.blob());
            this.recorder.onerror = () => resolve(this.blob());
        });
        this.recorder.start(1000);
        this.startedAt = performance.now();
        this.timer = setInterval(() => this.tick(), 1000 / fps);
    }

    get recording() {
        return this.recorder?.state === 'recording';
    }

    get elapsedSeconds() {
        return this.startedAt ? (performance.now() - this.startedAt) / 1000 : 0;
    }

    /** Porte du micro de la séance : la voix de l'apprenant n'est gardée que quand c'est à lui de parler. */
    learnerOpen(open) {
        if (!this.learnerTap || !this.context || this.context.state === 'closed') {
            return;
        }

        const now = this.context.currentTime;
        const gain = this.learnerTap.gain;
        gain.cancelScheduledValues(now);
        gain.setValueAtTime(gain.value, now);
        gain.linearRampToValueAtTime(open ? 1 : 0, now + 0.03);
    }

    /**
     * Tableau de la séance (#board), ou null. Suivi par un MutationObserver : élément allumé, étape, contenu ; il
     * est relu seulement quand il change.
     */
    setBoard(element) {
        this.observer?.disconnect();
        this.boardEl = element ?? null;
        this.boardDirty = true;

        if (this.boardEl && typeof MutationObserver !== 'undefined') {
            this.observer = new MutationObserver(() => { this.boardDirty = true; });
            this.observer.observe(this.boardEl, { subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['class', 'data-phase'] });
        }
    }

    /**
     * Professeur 3D : appelé juste après chaque image de l'écran (teacher-3d.js → onrender) ; au rythme de la
     * vidéo au plus, une image par la caméra de la vidéo, à la taille de sa place.
     */
    captureTeacher(teacher) {
        if (!this.recording || typeof teacher?.renderVideo !== 'function') {
            return;
        }

        const now = performance.now();

        if (now - this.teacherFrameAt < 1000 / this.config.fps - 4) {
            return;
        }

        this.teacherFrameAt = now;
        this.updateSplit(now);
        this.cost.count++;

        if (this.cost.count % this.cost.every !== 0 && this.lastFrame) {
            this.draw(this.lastFrame);

            return;
        }

        const area = this.teacherArea();
        const scale = this.cost.scale;
        const frame = teacher.renderVideo({ width: this.canvas.width * scale, height: area.height * scale, camera: this.cameraFor(this.split.value) });
        this.draw(frame);
        this.lastFrame = frame;
        this.watchCost(performance.now() - now);
    }

    /** Image vidéo trop chère pour ce téléphone : moins de pixels, puis moins d'images (jamais la séance). */
    watchCost(ms) {
        const cost = this.cost;
        cost.samples.push(ms);

        if (cost.samples.length < 24) {
            return;
        }

        const mean = cost.samples.reduce((sum, value) => sum + value, 0) / cost.samples.length;
        cost.samples = [];
        const next = adaptQuality(cost, mean, this.config.frame_budget_ms);
        cost.scale = next.scale;
        cost.every = next.every;
    }

    /** Minuterie : dessine le visage SVG seulement quand le professeur 3D ne fournit plus d'image. */
    tick() {
        const now = performance.now();

        if (now - this.teacherFrameAt > 250) {
            this.updateSplit(now);
            this.draw(null);
        }
    }

    /** Arrête l'enregistrement ; renvoie la vidéo (Blob) ou null. */
    stop() {
        if (!this.recorder) {
            return Promise.resolve(null);
        }

        clearInterval(this.timer);
        this.observer?.disconnect();

        try {
            if (this.recorder.state !== 'inactive') {
                this.recorder.requestData?.();
                this.recorder.stop();
            }
        } catch { /* déjà arrêté */ }

        this.video?.getTracks().forEach((track) => track.stop());

        for (const [from, to] of this.links ?? []) {
            try { from.disconnect(to); } catch { /* déjà débranché */ }
        }

        this.links = [];

        return this.done;
    }

    blob() {
        return this.chunks.length > 0 ? new Blob(this.chunks, { type: this.mimeType.split(';')[0] }) : null;
    }

    /* ---------- Mise en page ---------- */

    boardHeight() {
        return Math.round(this.config.height * this.config.board_share);
    }

    /** Place de le professeur : sous le tableau (qui glisse pendant la transition). */
    teacherArea() {
        const top = Math.round(this.boardHeight() * this.split.value);

        return { top, height: this.config.height - top };
    }

    cameraFor(split) {
        return mixCamera(this.config.camera_full, this.config.camera_split, split);
    }

    /** Relit le tableau s'il a changé, et vise l'écran partagé s'il a du contenu, sinon le plein cadre. */
    updateSplit(now) {
        if (this.boardDirty) {
            this.boardDirty = false;
            const model = this.readBoard();
            this.boardLayout = model ? this.layout(model) : null;
            this.paintBoard(model);
            const target = this.boardLayout ? 1 : 0;

            if (target !== this.split.to) {
                this.split = { value: this.split.value, from: this.split.value, to: target, at: now };
            }
        }

        const progress = (now - this.split.at) / this.config.transition_ms;
        this.split.value = this.split.from + (this.split.to - this.split.from) * easeInOut(progress);
    }

    /** Données du tableau (#board) : texte des éléments, élément allumé, étape. null s'il est vide ou absent. */
    readBoard() {
        const root = this.boardEl;

        if (!root) {
            return null;
        }

        const text = (el) => (el?.textContent ?? '').replace(/\s+/g, ' ').trim();
        const sections = [];
        let pitfall = '';
        let current = null;

        for (const el of root.querySelectorAll('.board-heading, .board-item, .board-pitfall')) {
            if (el.classList.contains('board-heading')) {
                current = { heading: text(el), items: [] };
                sections.push(current);
            } else if (el.classList.contains('board-pitfall')) {
                pitfall = text(el);
                sections.pop();
            } else {
                if (!current) {
                    current = { heading: '', items: [] };
                    sections.push(current);
                }

                const en = text(el.querySelector('.board-en'));

                if (en) {
                    current.items.push({ en, fr: text(el.querySelector('.board-fr')), lit: el.classList.contains('is-lit') });
                }
            }
        }

        const filled = sections.filter((section) => section.items.length > 0);

        if (filled.length === 0) {
            return null;
        }

        const translations = root.dataset?.translations;

        return {
            title: text(root.querySelector('.board-title')),
            phase: text(root.querySelector('[data-board-phase]')),
            sections: filled,
            pitfall,
            showFr: translations !== 'hidden' || root.classList.contains('show-translations'),
        };
    }

    layout(model) {
        const g = this.g;
        const margin = this.margin();
        const measure = (text, size, weight) => {
            g.font = `${weight} ${size}px ${this.font}`;

            return g.measureText(text).width;
        };

        return layoutBoard(model, {
            width: this.canvas.width - 2 * margin,
            height: this.boardHeight() - 2 * margin,
            showFr: model.showFr,
            sizes: this.config.board_sizes,
        }, measure);
    }

    margin() {
        return Math.round(this.config.width * 0.03);
    }

    /** Dessine le tableau dans son image (refaite seulement quand il change). */
    paintBoard(model) {
        const g = this.boardCanvas.getContext('2d');
        const { width, height } = this.boardCanvas;
        const margin = this.margin();
        const c = this.colors;

        g.fillStyle = c.background;
        g.fillRect(0, 0, width, height);

        if (!model || !this.boardLayout) {
            return;
        }

        const { metrics: m, blocks } = this.boardLayout;
        const x = margin + m.pad;
        const panel = [margin, margin, width - 2 * margin, height - 2 * margin];

        // Panneau du tableau : un peu plus clair que le fond, liseré orange en haut.
        g.fillStyle = c.text;
        g.globalAlpha = 0.07;
        roundRect(g, ...panel, 18);
        g.fill();
        g.globalAlpha = 1;
        g.fillStyle = c.accent;
        g.fillRect(margin + 18, margin, width - 2 * margin - 36, 4);

        g.textBaseline = 'top';
        // En-tête : la plateforme (discrète) à gauche, l'étape à droite.
        g.textAlign = 'left';
        g.font = `700 ${m.small}px ${this.font}`;
        g.fillStyle = c.text;
        g.globalAlpha = 0.72;
        g.fillText(this.config.platform, x, margin + m.small * 0.9);
        g.globalAlpha = 1;

        if (model.phase) {
            g.font = `700 ${m.small}px ${this.font}`;
            const w = g.measureText(model.phase).width + m.small * 1.2;
            const right = width - margin - m.pad;
            g.fillStyle = c.accent;
            roundRect(g, right - w, margin + m.small * 0.55, w, m.small * 1.6, m.small * 0.8);
            g.fill();
            g.fillStyle = c.background;
            g.textAlign = 'center';
            g.fillText(model.phase, right - w / 2, margin + m.small * 0.9);
            g.textAlign = 'left';
        }

        for (const block of blocks) {
            const top = margin + block.y;

            if (block.type === 'title') {
                g.font = `700 ${m.title}px ${this.font}`;
                g.fillStyle = c.text;
                block.lines.forEach((line, i) => g.fillText(line, x, top + m.gap + i * m.title * m.line));
            } else if (block.type === 'heading') {
                g.font = `700 ${m.heading}px ${this.font}`;
                g.fillStyle = c.text;
                g.globalAlpha = 0.62;
                g.fillText(block.text.toUpperCase(), x, top + m.heading * 0.6);
                g.globalAlpha = 1;
            } else if (block.type === 'item') {
                // Élément que le professeur explique : surligné, barre orange à gauche.
                if (block.lit) {
                    g.fillStyle = c.accent;
                    g.globalAlpha = 0.18;
                    roundRect(g, x - m.itemPad, top, width - 2 * x + 2 * m.itemPad, block.h - m.gap, 10);
                    g.fill();
                    g.globalAlpha = 1;
                    g.fillRect(x - m.itemPad, top, 5, block.h - m.gap);
                }

                let y = top + m.itemPad;
                g.font = `600 ${m.en}px ${this.font}`;
                g.fillStyle = c.accent;

                for (const line of block.en) {
                    g.fillText(line, x + m.itemPad, y);
                    y += m.en * m.line;
                }

                g.font = `400 ${m.fr}px ${this.font}`;
                g.fillStyle = c.text;
                g.globalAlpha = 0.85;

                for (const line of block.fr) {
                    g.fillText(line, x + m.itemPad, y);
                    y += m.fr * m.line;
                }

                g.globalAlpha = 1;
            } else if (block.type === 'pitfall') {
                g.font = `400 ${m.fr}px ${this.font}`;
                g.fillStyle = c.text;
                block.lines.forEach((line, i) => g.fillText(line, x, top + m.gap + i * m.fr * m.line));
            }
        }
    }

    /* ---------- Image ---------- */

    readColors() {
        const css = getComputedStyle(document.documentElement);
        const read = (name, fallback) => css.getPropertyValue(name).trim() || fallback;

        return {
            background: read('--c-primary', 'navy'),
            accent: read('--c-accent', 'orange'),
            stage: read('--c-primary-soft', 'white'),
            text: read('--c-surface', 'white'),
        };
    }

    /** Formes du visage SVG de secours (chemins, cercles, ellipses) avec leurs couleurs calculées par le CSS. */
    readSvg() {
        if (!this.svg) {
            return [];
        }

        const shapes = [];

        for (const element of this.svg.querySelectorAll('path, circle, ellipse')) {
            const style = getComputedStyle(element);
            const shape = { element, fill: style.fill, stroke: style.stroke, lineWidth: parseFloat(style.strokeWidth) || 0 };
            const attr = (name) => parseFloat(element.getAttribute(name)) || 0;

            if (element.tagName.toLowerCase() === 'path') {
                shape.path = new Path2D(element.getAttribute('d'));
            } else {
                shape.cx = attr('cx');
                shape.cy = attr('cy');
                shape.rx = attr(element.tagName.toLowerCase() === 'circle' ? 'r' : 'rx');
                shape.ry = attr(element.tagName.toLowerCase() === 'circle' ? 'r' : 'ry');
            }

            shapes.push(shape);
        }

        return shapes;
    }

    /** @param {{data: Uint8Array, width: number, height: number}|null} frame image 3D de la caméra de la vidéo, sinon le visage SVG */
    draw(frame) {
        const g = this.g;
        const { width, height } = this.canvas;
        const c = this.colors;
        const split = this.split.value;
        const area = this.teacherArea();

        g.fillStyle = c.background;
        g.fillRect(0, 0, width, height);

        // le professeur, sous le tableau.
        if (frame && frame.height > 0) {
            if (this.staging.width !== frame.width || this.staging.height !== frame.height) {
                this.staging.width = frame.width;
                this.staging.height = frame.height;
            }

            const pixels = new Uint8ClampedArray(frame.data.buffer, frame.data.byteOffset, frame.width * frame.height * 4);
            this.staging.getContext('2d').putImageData(new ImageData(pixels, frame.width, frame.height), 0, 0);
            // Image lue du bas vers le haut : retournée.
            g.save();
            g.translate(0, area.top + area.height);
            g.scale(1, -1);
            g.drawImage(this.staging, 0, 0, frame.width, frame.height, 0, 0, width, area.height);
            g.restore();
        } else {
            g.fillStyle = c.stage;
            g.fillRect(0, area.top, width, area.height);
            this.drawFace(area.top, area.height);
        }

        // Le tableau glisse depuis le haut.
        if (split > 0.001) {
            const boardH = this.boardHeight();
            g.globalAlpha = Math.min(1, split * 1.5);
            g.drawImage(this.boardCanvas, 0, area.top - boardH);
            g.globalAlpha = 1;
            g.fillStyle = c.accent;
            g.fillRect(0, area.top - 2, width, 4);
        }

        g.textBaseline = 'middle';
        const small = Math.round(width * 0.034);

        // Plein cadre : la plateforme en haut, discrète (dans l'en-tête du tableau en écran partagé) ; elle
        // s'efface dès que le tableau commence à descendre.
        const overlay = Math.max(0, 1 - split * 4);

        if (overlay > 0) {
            g.globalAlpha = overlay;
            this.shade(0, 0, width, small * 3.2, true);
            g.globalAlpha = overlay * 0.85;
            g.textAlign = 'left';
            g.font = `700 ${small}px ${this.font}`;
            g.fillStyle = c.text;
            g.fillText(this.config.platform, Math.round(width * 0.05), small * 1.4);
            g.globalAlpha = 1;
        }

        // L'adresse du site, en bas, discrète.
        if (this.config.mention) {
            this.shade(0, height - small * 3.2, width, small * 3.2, false);
            g.globalAlpha = 0.8;
            g.textAlign = 'center';
            g.font = `400 ${small}px ${this.font}`;
            g.fillStyle = c.text;
            g.fillText(this.config.mention, width / 2, height - small * 1.4);
            g.globalAlpha = 1;
        }
    }

    /** Dégradé sombre léger sous un texte posé sur l'image du professeur (lisible sur le mur clair). */
    shade(x, y, w, h, fromTop) {
        const g = this.g;
        const gradient = g.createLinearGradient(0, fromTop ? y : y + h, 0, fromTop ? y + h : y);
        gradient.addColorStop(0, this.colors.background);
        gradient.addColorStop(1, 'rgba(0, 0, 0, 0)');
        const alpha = g.globalAlpha;
        g.globalAlpha = alpha * 0.55;
        g.fillStyle = gradient;
        g.fillRect(x, y, w, h);
        g.globalAlpha = alpha;
    }

    drawFace(top, stageHeight) {
        if (this.svgShapes.length === 0) {
            return;
        }

        const g = this.g;
        const { width } = this.canvas;
        const [, , boxWidth, boxHeight] = (this.svg.getAttribute('viewBox') ?? '0 0 200 250').split(/\s+/).map(Number);
        const scale = Math.min(width / boxWidth, stageHeight / boxHeight) * 0.86;

        g.save();
        g.translate((width - boxWidth * scale) / 2, top + (stageHeight - boxHeight * scale));
        g.scale(scale, scale);

        for (const shape of this.svgShapes) {
            let path = shape.path;

            if (!path) {
                // La bouche bouge avec la voix (attribut ry mis à jour par live-session.js).
                const ry = shape.element === this.mouth ? parseFloat(this.mouth.getAttribute('ry')) || shape.ry : shape.ry;
                path = new Path2D();
                path.ellipse(shape.cx, shape.cy, shape.rx, ry, 0, 0, Math.PI * 2);
            }

            if (shape.fill && shape.fill !== 'none') {
                g.fillStyle = shape.fill;
                g.fill(path);
            }

            if (shape.stroke && shape.stroke !== 'none' && shape.lineWidth > 0) {
                g.strokeStyle = shape.stroke;
                g.lineWidth = shape.lineWidth;
                g.lineCap = 'round';
                g.stroke(path);
            }
        }

        g.restore();
    }
}

/** Rectangle arrondi (tracé seulement). */
function roundRect(g, x, y, w, h, r) {
    const radius = Math.max(0, Math.min(r, w / 2, h / 2));
    g.beginPath();
    g.moveTo(x + radius, y);
    g.arcTo(x + w, y, x + w, y + h, radius);
    g.arcTo(x + w, y + h, x, y + h, radius);
    g.arcTo(x, y + h, x, y, radius);
    g.arcTo(x, y, x + w, y, radius);
    g.closePath();
}
