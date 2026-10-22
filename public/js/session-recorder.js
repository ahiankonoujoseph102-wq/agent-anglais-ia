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

/**
 * Image 3D vraiment dessinée ? (pas une image vide ou d'une seule couleur, avant que la scène soit prête)
 * Quelques centaines de points lus, pas toute l'image. Fonction pure, testée.
 * @param {{data: ArrayLike<number>, width: number, height: number}|null} frame
 */
export function hasPicture(frame, samples = 400) {
    if (!frame?.data || !(frame.width > 0) || !(frame.height > 0)) {
        return false;
    }

    const pixels = frame.width * frame.height;
    const step = Math.max(1, Math.floor(pixels / samples));
    let first = null;

    for (let i = 0; i < pixels; i += step) {
        const o = i * 4;
        const value = (frame.data[o] << 24 | frame.data[o + 1] << 16 | frame.data[o + 2] << 8 | frame.data[o + 3]) >>> 0;

        if (frame.data[o + 3] === 0) {
            continue;
        }

        if (first === null) {
            first = value;
        } else if (value !== first) {
            return true;
        }
    }

    return false;
}

/**
 * Ce que montre le haut de la vidéo : 'board' (tableau de la séance ou « Découverte »), 'level' (niveau trouvé, au
 * verdict), 'intro' (tableau neutre de l'évaluation) ou null (plein cadre). Avant le verdict (reveal nul), jamais
 * 'level' : le niveau n'existe pas encore dans l'enregistreur. Après, le niveau reste au moins jusqu'à reveal.until,
 * et tant qu'aucun tableau n'est arrivé (jamais de tableau vide). Fonction pure, testée.
 */
export function boardKind({ board, intro, reveal, now }) {
    if (reveal) {
        return board && now >= reveal.until ? 'board' : 'level';
    }

    if (board) {
        return 'board';
    }

    return intro ? 'intro' : null;
}

/**
 * Contenu du tableau neutre de l'évaluation : seulement les textes de la configuration et le temps de l'écran
 * (« Temps restant 4:12 »). Aucun niveau, aucune question, aucun score. Fonction pure, testée.
 */
export function introModel(intro, clock) {
    return {
        kind: 'intro',
        phase: intro.phase,
        title: intro.title,
        text: intro.text,
        clockLabel: clock?.label ?? '',
        clock: clock?.value ?? '',
    };
}

/** Carte du niveau trouvé (seulement après le verdict). Fonction pure, testée. */
export function levelModel(intro, reveal) {
    return {
        kind: 'level',
        phase: intro?.result_phase ?? '',
        heading: intro?.level_heading ?? '',
        level: reveal.level,
        label: reveal.label ?? '',
        text: intro?.level_text ?? '',
    };
}

export class SessionRecorder {
    /**
     * @param {object} config réglages de App\Support\SessionRecording::clientConfig()
     * @param {{svg: SVGElement|null, mouth: Element|null, mimeType: string, board?: Element|null, waitForTeacher?: boolean}} options
     *   waitForTeacher : le professeur 3D se charge encore ; aucune image n'est écrite avant sa première vraie image
     *   (la couverture de la vidéo le montre), sauf repli sur le visage simple (useFace()).
     */
    constructor(config, { svg = null, mouth = null, mimeType, board = null, waitForTeacher = false, clock = null }) {
        this.config = config;
        // Évaluation : temps affiché à l'écran ({label, value}), lu pour le tableau neutre.
        this.clock = clock;
        // Niveau trouvé ({level, label, until}) : posé par revealLevel(), au même instant que l'écran.
        this.reveal = null;
        this.boardModel = null;
        this.shown = { kind: undefined, clock: '' };
        this.fade = null;
        // Professeur dessiné dans la vidéo : 'waiting' (3D attendue, rien d'écrit), '3d', ou 'face' (visage simple).
        this.mode = waitForTeacher ? 'waiting' : 'face';
        this.stopped = false;
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

        this.requestedAt = performance.now();
        this.timer = setInterval(() => this.tick(), 1000 / fps);

        if (this.mode === 'face') {
            this.begin(null);
        }
    }

    /**
     * Première image de la vidéo (sa couverture) : la première vraie image 3D, ou le visage simple en repli. Le
     * MediaRecorder ne démarre qu'ici : aucune image avant.
     */
    begin(frame) {
        if (this.recorder || this.stopped) {
            return;
        }

        const { fps } = this.config;
        // Directement dans la bonne disposition, sans transition.
        this.updateSplit(performance.now());
        this.split = { value: this.split.to, from: this.split.to, to: this.split.to, at: 0 };
        this.draw(frame);
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
    }

    get recording() {
        return this.recorder?.state === 'recording';
    }

    /** En attente de la première image 3D (rien d'écrit pour l'instant). */
    get waiting() {
        return this.mode === 'waiting' && !this.recorder && !this.stopped;
    }

    /** Professeur 3D indisponible (téléphone incompatible, échec, « Visage simple ») : le visage simple prend sa place. */
    useFace() {
        this.mode = 'face';

        if (!this.recorder && this.timer) {
            this.begin(null);
        }
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
     * Évaluation : le niveau trouvé vient d'être affiché à l'écran (même signal : showAssessmentResult). La vidéo le
     * montre reveal_ms au moins, puis le tableau « Découverte » s'il est arrivé (fondu).
     */
    revealLevel(level, label) {
        if (!level || this.reveal) {
            return;
        }

        this.reveal = { level, label, until: performance.now() + (this.config.assessment?.reveal_ms ?? 4000) };
    }

    /**
     * Professeur 3D : appelé juste après chaque image de l'écran (teacher-3d.js → onrender) ; au rythme de la
     * vidéo au plus, une image par la caméra de la vidéo, à la taille de sa place.
     */
    captureTeacher(teacher) {
        if (typeof teacher?.renderVideo !== 'function') {
            return;
        }

        if (this.waiting) {
            this.firstTeacherFrame(teacher);

            return;
        }

        if (!this.recording) {
            return;
        }

        const now = performance.now();
        this.mode = '3d';

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

    /** Première vraie image 3D (jamais une image vide) : la vidéo commence par elle. */
    firstTeacherFrame(teacher) {
        const now = performance.now();
        this.updateSplit(now);
        const area = this.teacherArea();
        const split = this.split.to;
        const frame = teacher.renderVideo({ width: this.canvas.width, height: area.height, camera: this.cameraFor(split) });

        if (!hasPicture(frame)) {
            return;
        }

        this.mode = '3d';
        this.teacherFrameAt = now;
        this.lastFrame = frame;
        this.begin(frame);
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

    /**
     * Minuterie. En attente de la 3D : rien, sauf repli sur le visage simple après teacher_wait_ms. Ensuite, quand
     * le professeur 3D ne fournit plus d'image (calcul long, onglet en arrière-plan), sa dernière image est
     * redessinée ; le visage simple seulement en mode 'face'.
     */
    tick() {
        const now = performance.now();

        if (this.waiting) {
            if (now - this.requestedAt > (this.config.teacher_wait_ms ?? 35000)) {
                this.useFace();
            }

            return;
        }

        if (this.recording && now - this.teacherFrameAt > 250) {
            this.updateSplit(now);
            this.draw(this.mode === '3d' ? this.lastFrame ?? null : null);
        }
    }

    /** Arrête l'enregistrement ; renvoie la vidéo (Blob) ou null. */
    stop() {
        this.stopped = true;
        clearInterval(this.timer);
        this.observer?.disconnect();

        if (!this.recorder) {
            this.unlink();

            return Promise.resolve(null);
        }

        try {
            if (this.recorder.state !== 'inactive') {
                this.recorder.requestData?.();
                this.recorder.stop();
            }
        } catch { /* déjà arrêté */ }

        this.video?.getTracks().forEach((track) => track.stop());
        this.unlink();

        return this.done;
    }

    unlink() {
        for (const [from, to] of this.links ?? []) {
            try { from.disconnect(to); } catch { /* déjà débranché */ }
        }

        this.links = [];
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
        let changed = false;

        if (this.boardDirty) {
            this.boardDirty = false;
            this.boardModel = this.readBoard();
            changed = true;
        }

        const intro = this.config.assessment ?? null;
        const kind = boardKind({ board: this.boardModel !== null, intro: intro !== null, reveal: this.reveal, now });
        const clock = kind === 'intro' && this.clock ? this.clock() : null;
        const clockText = clock ? `${clock.label} ${clock.value}` : '';

        if (changed || kind !== this.shown.kind || clockText !== this.shown.clock) {
            // Autre contenu (tableau neutre → niveau → « Découverte ») : fondu depuis l'image précédente.
            if (this.shown.kind && kind && kind !== this.shown.kind) {
                this.startFade(now);
            }

            this.shown = { kind, clock: clockText };
            const target = this.paintKind(kind, intro, clock);

            if (target !== this.split.to) {
                this.split = { value: this.split.value, from: this.split.value, to: target, at: now };
            }
        }

        const progress = (now - this.split.at) / this.config.transition_ms;
        this.split.value = this.split.from + (this.split.to - this.split.from) * easeInOut(progress);
    }

    /** Dessine le contenu choisi dans l'image du tableau ; renvoie la part visée (1 : écran partagé, 0 : plein cadre). */
    paintKind(kind, intro, clock) {
        if (kind === 'board') {
            this.boardLayout = this.layout(this.boardModel);
            this.paintBoard(this.boardModel);

            return this.boardLayout ? 1 : 0;
        }

        this.boardLayout = null;

        if (kind === 'intro') {
            this.paintCard(introModel(intro, clock));

            return 1;
        }

        if (kind === 'level') {
            this.paintCard(levelModel(intro, this.reveal));

            return 1;
        }

        this.paintBoard(null);

        return 0;
    }

    startFade(now) {
        this.prevBoard ??= document.createElement('canvas');
        this.prevBoard.width = this.boardCanvas.width;
        this.prevBoard.height = this.boardCanvas.height;
        this.prevBoard.getContext('2d').drawImage(this.boardCanvas, 0, 0);
        this.fade = { at: now };
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
        this.paintPanel(g, model.phase, m);

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

    /** Panneau du tableau (un peu plus clair que le fond, liseré orange en haut) et son en-tête : la plateforme
     * (discrète) à gauche, l'étape à droite. */
    paintPanel(g, phase, m) {
        const { width, height } = this.boardCanvas;
        const margin = this.margin();
        const c = this.colors;
        const x = margin + m.pad;

        g.fillStyle = c.text;
        g.globalAlpha = 0.07;
        roundRect(g, margin, margin, width - 2 * margin, height - 2 * margin, 18);
        g.fill();
        g.globalAlpha = 1;
        g.fillStyle = c.accent;
        g.fillRect(margin + 18, margin, width - 2 * margin - 36, 4);

        g.textBaseline = 'top';
        g.textAlign = 'left';
        g.font = `700 ${m.small}px ${this.font}`;
        g.fillStyle = c.text;
        g.globalAlpha = 0.72;
        g.fillText(this.config.platform, x, margin + m.small * 0.9);
        g.globalAlpha = 1;

        if (phase) {
            g.font = `700 ${m.small}px ${this.font}`;
            const w = g.measureText(phase).width + m.small * 1.2;
            const right = width - margin - m.pad;
            g.fillStyle = c.accent;
            roundRect(g, right - w, margin + m.small * 0.55, w, m.small * 1.6, m.small * 0.8);
            g.fill();
            g.fillStyle = c.background;
            g.textAlign = 'center';
            g.fillText(phase, right - w / 2, margin + m.small * 0.9);
            g.textAlign = 'left';
        }
    }

    /**
     * Tableau neutre de l'évaluation (titre, phrase, temps) ou carte du niveau trouvé : même panneau et même
     * en-tête que le tableau de la séance, contenu centré dans la hauteur.
     */
    paintCard(model) {
        const g = this.boardCanvas.getContext('2d');
        const { width, height } = this.boardCanvas;
        const margin = this.margin();
        const c = this.colors;
        const m = boardMetrics(this.config.board_sizes?.max ?? 34);
        const x = margin + m.pad;
        const inner = width - 2 * x;
        const font = (size, weight) => `${weight} ${size}px ${this.font}`;
        const measure = (size, weight) => (text) => {
            g.font = font(size, weight);

            return g.measureText(text).width;
        };

        g.fillStyle = c.background;
        g.fillRect(0, 0, width, height);
        this.paintPanel(g, model.phase, m);

        // Lignes : [texte, taille, graisse, couleur, opacité].
        const rows = [];
        const add = (text, size, weight, color, alpha = 1, maxLines = 2) => {
            for (const line of wrapLines(text, inner, measure(size, weight), maxLines)) {
                rows.push({ line, size, weight, color, alpha });
            }
        };

        if (model.kind === 'intro') {
            add(model.title, 44, 700, c.text);
            rows.push({ gap: 10 });
            // Une phrase par ligne (jamais un mot seul en bout de phrase).
            for (const sentence of String(model.text ?? '').split(/(?<=[.!?])\s+/).filter(Boolean)) {
                add(sentence, 24, 400, c.text, 0.85, 2);
            }
            rows.push({ gap: 22 });
            add(model.clockLabel, 20, 700, c.text, 0.72, 1);
            add(model.clock, 72, 700, c.accent, 1, 1);
        } else {
            add(model.heading, 26, 700, c.text, 0.85, 1);
            rows.push({ gap: 4 });
            add(model.level, 110, 700, c.accent, 1, 1);
            add(model.label, 34, 700, c.text, 1, 1);
            rows.push({ gap: 14 });
            add(model.text, 24, 400, c.text, 0.85, 2);
        }

        const lineHeight = (row) => (row.gap ?? row.size * 1.18);
        const total = rows.reduce((sum, row) => sum + lineHeight(row), 0);
        const top = margin + m.header + m.pad * 0.5;
        let y = top + Math.max(0, (height - margin - m.pad - top - total) / 2);
        g.textAlign = 'center';
        g.textBaseline = 'top';

        for (const row of rows) {
            if (row.line !== undefined) {
                g.font = font(row.size, row.weight);
                g.fillStyle = row.color;
                g.globalAlpha = row.alpha;
                g.fillText(row.line, width / 2, y);
                g.globalAlpha = 1;
            }

            y += lineHeight(row);
        }

        g.textAlign = 'left';
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
            const fading = this.fade ? Math.min(1, (performance.now() - this.fade.at) / this.config.transition_ms) : 1;

            if (fading < 1) {
                // Fondu entre l'ancien contenu et le nouveau (tableau neutre → niveau → « Découverte »).
                g.drawImage(this.prevBoard, 0, area.top - boardH);
                g.globalAlpha *= easeInOut(fading);
            } else {
                this.fade = null;
            }

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
