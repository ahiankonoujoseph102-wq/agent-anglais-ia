/*
 * « Enregistrer ma séance » : vidéo de la séance fabriquée ENTIÈREMENT dans le navigateur, jamais envoyée
 * au serveur.
 *
 * Image : un canevas vertical 9:16 (config/platform.php → recording) où sont recopiés, à chaque image, le
 * professeur 3D (juste après son rendu : le canevas WebGL n'est lisible qu'à ce moment-là) ou, à défaut, le
 * visage SVG, avec le nom de la plateforme, celui du professeur et, en bas, l'adresse du site.
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

/** Rectangle source qui remplit la zone sans déformer (recadrage au centre, un peu vers le haut : la tête). */
export function coverRect(sourceWidth, sourceHeight, width, height) {
    const scale = Math.max(width / sourceWidth, height / sourceHeight);
    const sw = width / scale;
    const sh = height / scale;

    return { sx: (sourceWidth - sw) / 2, sy: Math.max(0, (sourceHeight - sh) * 0.35), sw, sh };
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

export class SessionRecorder {
    /**
     * @param {object} config réglages de App\Support\SessionRecording::clientConfig()
     * @param {{svg: SVGElement|null, mouth: Element|null, mimeType: string}} options
     */
    constructor(config, { svg = null, mouth = null, mimeType }) {
        this.config = config;
        this.svg = svg;
        this.mouth = mouth;
        this.mimeType = mimeType;
        this.chunks = [];
        this.teacherFrameAt = 0;
        this.startedAt = 0;
        this.recorder = null;
    }

    /** Démarre l'enregistrement sur le contexte audio de la séance. Lève une erreur si c'est impossible. */
    start({ context, voiceNode, micNode }) {
        const { width, height, fps } = this.config;

        this.canvas = document.createElement('canvas');
        this.canvas.width = width;
        this.canvas.height = height;
        this.g = this.canvas.getContext('2d');
        this.colors = this.readColors();
        this.svgShapes = this.readSvg();

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
     * Image du professeur 3D, dessinée dans la vidéo juste après son rendu (appelée par teacher-3d.js) : une
     * seule lecture du canevas WebGL par image, au rythme de la vidéo au plus.
     */
    captureTeacher(canvas) {
        if (!this.recording || !canvas.width || !canvas.height) {
            return;
        }

        const now = performance.now();

        if (now - this.teacherFrameAt < 1000 / this.config.fps - 4) {
            return;
        }

        this.teacherFrameAt = now;
        this.draw(canvas);
    }

    /** Minuterie : dessine le visage SVG seulement quand le professeur 3D ne fournit plus d'image. */
    tick() {
        if (performance.now() - this.teacherFrameAt > 250) {
            this.draw(null);
        }
    }

    /** Arrête l'enregistrement ; renvoie la vidéo (Blob) ou null. */
    stop() {
        if (!this.recorder) {
            return Promise.resolve(null);
        }

        clearInterval(this.timer);

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

    /** @param {HTMLCanvasElement|null} teacher canevas 3D qui vient d'être rendu, sinon le visage SVG */
    draw(teacher) {
        const g = this.g;
        const { width, height } = this.canvas;
        const font = getComputedStyle(document.body).fontFamily || 'sans-serif';
        const top = Math.round(height * 0.1);
        const stageHeight = Math.round(width * 4 / 3);

        g.fillStyle = this.colors.background;
        g.fillRect(0, 0, width, height);

        // En haut : plateforme et type de séance.
        g.textAlign = 'center';
        g.textBaseline = 'middle';
        g.fillStyle = this.colors.accent;
        g.font = `700 ${Math.round(width * 0.06)}px ${font}`;
        g.fillText(this.config.platform, width / 2, top * 0.42);
        g.fillStyle = this.colors.text;
        g.font = `400 ${Math.round(width * 0.036)}px ${font}`;
        g.fillText(this.config.title, width / 2, top * 0.8);

        // Le professeur : image 3D récente, sinon le visage SVG.
        if (teacher) {
            const { sx, sy, sw, sh } = coverRect(teacher.width, teacher.height, width, stageHeight);
            g.drawImage(teacher, sx, sy, sw, sh, 0, top, width, stageHeight);
        } else {
            g.fillStyle = this.colors.stage;
            g.fillRect(0, top, width, stageHeight);
            this.drawFace(top, stageHeight);
        }

        // Liseré orange sous le professeur, son nom, puis l'adresse du site, discrète.
        g.fillStyle = this.colors.accent;
        g.fillRect(0, top + stageHeight, width, Math.max(2, Math.round(height * 0.004)));
        g.fillStyle = this.colors.text;
        g.font = `700 ${Math.round(width * 0.05)}px ${font}`;
        g.fillText(this.config.teacher, width / 2, top + stageHeight + (height - top - stageHeight) * 0.4);

        if (this.config.mention) {
            g.globalAlpha = 0.7;
            g.font = `400 ${Math.round(width * 0.032)}px ${font}`;
            g.fillText(this.config.mention, width / 2, height - Math.round(height * 0.035));
            g.globalAlpha = 1;
        }
    }

    drawFace(top, stageHeight) {
        if (this.svgShapes.length === 0) {
            return;
        }

        const g = this.g;
        const { width } = this.canvas;
        const [, , boxWidth, boxHeight] = (this.svg.getAttribute('viewBox') ?? '0 0 200 250').split(/\s+/).map(Number);
        const scale = Math.min(width / boxWidth, stageHeight / boxHeight) * 0.92;

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
