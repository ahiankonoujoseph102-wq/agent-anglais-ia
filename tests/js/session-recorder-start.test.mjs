// node --test tests/js/session-recorder-start.test.mjs
// Début de la vidéo : jamais le visage simple quand le professeur 3D arrive, la première image (couverture) est sa
// première vraie image ; le visage simple seulement en repli.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { boardKind, hasPicture, introModel, levelModel, SessionRecorder } from '../../public/js/session-recorder.js';

const config = {
    width: 54, height: 96, fps: 24, video_bits_per_second: 1, audio_bits_per_second: 1, board_share: 0.45,
    transition_ms: 700, board_sizes: { max: 34, min: 24, window: 28 }, frame_budget_ms: 14, teacher_wait_ms: 35000,
    camera_full: { target: [0, 0.52, 0], half_height: 0.4, half_width: 0.26 },
    camera_split: { target: [0, 0.62, 0], half_height: 0.3, half_width: 0.32 },
    platform: 'P', mention: 'p.test',
};

/** Navigateur simulé : canevas, MediaRecorder et horloge ; chaque dessin du professeur est noté. */
function browser() {
    const env = { now: 1000, recorders: [], drawn: [], texts: [] };
    const context2d = () => new Proxy({}, {
        get: (target, name) => {
            if (name in target) {
                return target[name];
            }

            if (name === 'measureText') {
                return () => ({ width: 10 });
            }

            // Textes écrits dans la vidéo, gardés pour les vérifier.
            if (name === 'fillText') {
                return (text) => env.texts.push(String(text));
            }

            if (name === 'getContext') {
                return undefined;
            }

            // createLinearGradient, getImageData… : un objet qui accepte tout.
            return () => context2d();
        },
        set: (target, name, value) => { target[name] = value; return true; },
    });
    const canvas = () => {
        const g = context2d();

        return { width: 0, height: 0, getContext: () => g, captureStream: () => ({ getVideoTracks: () => [], getTracks: () => [] }) };
    };

    Object.assign(globalThis, {
        document: { createElement: canvas, body: {}, documentElement: {} },
        getComputedStyle: () => ({ fontFamily: 'sans-serif', getPropertyValue: () => '' }),
        ImageData: class { constructor(data, width, height) { Object.assign(this, { data, width, height }); } },
        MediaStream: class { constructor(tracks) { this.tracks = tracks; } },
        MediaRecorder: class {
            constructor() { this.state = 'inactive'; env.recorders.push(this); }
            start() { this.state = 'recording'; }
            stop() { this.state = 'inactive'; this.onstop?.(); }
        },
        setInterval: () => 1,
        clearInterval: () => {},
    });
    Object.defineProperty(globalThis, 'performance', { value: { now: () => env.now }, configurable: true });

    return env;
}

function audio() {
    const node = () => ({ connect: (to) => to, disconnect() {}, gain: { value: 1 } });
    const context = {
        currentTime: 0,
        state: 'running',
        createMediaStreamDestination: () => ({ ...node(), stream: { getAudioTracks: () => [] } }),
        createGain: node,
        createDynamicsCompressor: () => ({ ...node(), threshold: {}, knee: {}, ratio: {}, attack: {}, release: {} }),
    };

    return { context, voiceNode: node(), micNode: node() };
}

/** Recorder dont chaque image dessinée est notée : '3d' (image du professeur) ou 'face' (visage simple). */
function recorder(env, options) {
    const r = new SessionRecorder(config, { mimeType: 'video/webm', ...options });
    const draw = r.draw.bind(r);
    r.draw = (frame) => {
        env.drawn.push({ kind: frame ? '3d' : 'face', recording: r.recording });
        draw(frame);
    };

    return r;
}

const picture = (w = 8, h = 8) => {
    const data = new Uint8Array(w * h * 4);

    for (let i = 0; i < w * h; i++) {
        data.set([i % 7 * 30, 80, 120, 255], i * 4);
    }

    return { data, width: w, height: h };
};

const teacher = (frames) => ({ renderVideo: () => frames.shift() ?? picture() });

test('une image vide ou d\'une seule couleur n\'est pas une vraie image 3D', () => {
    assert.equal(hasPicture(null), false);
    assert.equal(hasPicture({ data: new Uint8Array(0), width: 0, height: 0 }), false);
    assert.equal(hasPicture({ data: new Uint8Array(8 * 8 * 4), width: 8, height: 8 }), false);
    assert.equal(hasPicture({ data: new Uint8Array(8 * 8 * 4).fill(200), width: 8, height: 8 }), false);
    assert.equal(hasPicture(picture()), true);
});

test('3D en chargement : aucune image avant la première vraie image 3D, qui est la couverture', () => {
    const env = browser();
    const r = recorder(env, { waitForTeacher: true });
    r.start(audio());

    assert.equal(r.waiting, true);
    assert.equal(env.recorders.length, 0);
    assert.deepEqual(env.drawn, []);
    assert.equal(r.elapsedSeconds, 0);

    // La minuterie ne dessine jamais le visage simple en attendant.
    env.now += 5000;
    r.tick();
    assert.deepEqual(env.drawn, []);

    // Une image vide (scène pas encore prête) est ignorée, la suivante ouvre la vidéo.
    const t = teacher([{ data: new Uint8Array(54 * 96 * 4), width: 54, height: 96 }]);
    r.captureTeacher(t);
    assert.equal(env.recorders.length, 0);
    r.captureTeacher(t);
    assert.equal(env.recorders.length, 1);
    assert.equal(r.recording, true);
    assert.deepEqual(env.drawn[0], { kind: '3d', recording: false });
    assert.equal(env.drawn.length, 1);

    // Ensuite, un calcul long : la dernière image 3D est redessinée, jamais le visage simple.
    env.now += 1000;
    r.tick();
    assert.deepEqual(env.drawn.map((d) => d.kind), ['3d', '3d']);
});

test('3D indisponible (repli) : la vidéo commence avec le visage simple, sans retard', () => {
    const env = browser();
    const r = recorder(env, { waitForTeacher: true });
    r.start(audio());
    env.now += 300;
    r.useFace();

    assert.equal(env.recorders.length, 1);
    assert.equal(env.drawn[0].kind, 'face');
    env.now += 1000;
    r.tick();
    assert.deepEqual(env.drawn.map((d) => d.kind), ['face', 'face']);

    // Sans professeur 3D du tout : visage simple dès le départ.
    const env2 = browser();
    const r2 = recorder(env2, {});
    r2.start(audio());
    assert.equal(env2.recorders.length, 1);
    assert.equal(env2.drawn[0].kind, 'face');
});

test('3D jamais arrivée : visage simple après le délai, jamais avant', () => {
    const env = browser();
    const r = recorder(env, { waitForTeacher: true });
    r.start(audio());
    env.now += 34000;
    r.tick();
    assert.equal(env.recorders.length, 0);
    env.now += 2000;
    r.tick();
    assert.equal(env.recorders.length, 1);
    assert.equal(env.drawn[0].kind, 'face');
});

test('arrêt avant la première image 3D : pas de vidéo, le son est débranché, rien ne démarre après', async () => {
    const env = browser();
    const r = recorder(env, { waitForTeacher: true });
    r.start(audio());
    assert.equal(await r.stop(), null);
    assert.deepEqual(r.links, []);
    r.captureTeacher(teacher([]));
    r.useFace();
    assert.equal(env.recorders.length, 0);
    assert.deepEqual(env.drawn, []);
});

const assessment = {
    phase: 'Évaluation', title: "Évaluation gratuite d'anglais", text: 'Parlez anglais avec Teacher Joe. Découvrez votre niveau en 5 minutes.',
    result_phase: 'Résultat', level_heading: 'Votre niveau', level_text: 'Bravo ! Votre parcours commence ici.', reveal_ms: 4000,
};
// Tout ce qui trahirait un niveau : lettres du niveau et libellés.
const LEVEL = /\b(A1|A2|B1|B2|C1|C2)\b|Débutant|Élémentaire|Intermédiaire|Avancé|Maîtrise/;

test('ce que montre le haut de la vidéo : tableau neutre, niveau au verdict, puis « Découverte »', () => {
    // Évaluation avant le verdict : le tableau neutre, jamais le niveau ni le plein cadre.
    assert.equal(boardKind({ board: false, intro: true, reveal: null, now: 0 }), 'intro');
    // Au verdict : le niveau, même si « Découverte » est déjà là, pendant reveal_ms.
    assert.equal(boardKind({ board: true, intro: true, reveal: { until: 5 }, now: 1 }), 'level');
    // Ensuite « Découverte » ; sans lui, le niveau reste (jamais de tableau vide).
    assert.equal(boardKind({ board: true, intro: true, reveal: { until: 5 }, now: 6 }), 'board');
    assert.equal(boardKind({ board: false, intro: true, reveal: { until: 5 }, now: 60 }), 'level');
    // Séance du jour : son tableau ; sans tableau ni évaluation : plein cadre, comme avant.
    assert.equal(boardKind({ board: true, intro: false, reveal: null, now: 0 }), 'board');
    assert.equal(boardKind({ board: false, intro: false, reveal: null, now: 0 }), null);
});

test('le tableau neutre ne contient aucun niveau, seulement les textes et le temps de l\'écran', () => {
    const model = introModel(assessment, { label: 'Temps restant', value: '4:12' });
    assert.deepEqual(model, {
        kind: 'intro', phase: 'Évaluation', title: "Évaluation gratuite d'anglais",
        text: 'Parlez anglais avec Teacher Joe. Découvrez votre niveau en 5 minutes.', clockLabel: 'Temps restant', clock: '4:12',
    });
    assert.doesNotMatch(JSON.stringify(model), LEVEL);
    assert.deepEqual(levelModel(assessment, { level: 'B1', label: 'Intermédiaire' }), {
        kind: 'level', phase: 'Résultat', heading: 'Votre niveau', level: 'B1', label: 'Intermédiaire', text: 'Bravo ! Votre parcours commence ici.',
    });
});

test('évaluation : aucune lettre de niveau dans la vidéo avant le verdict, le niveau au verdict, puis « Découverte »', () => {
    const env = browser();
    let clock = '5:00';
    const r = recorder(env, { waitForTeacher: true, clock: () => ({ label: 'Temps restant', value: clock }) });
    r.config = { ...config, width: 540, height: 960, assessment };
    r.start(audio());
    const t = teacher([]);
    r.captureTeacher(t);

    // Écran partagé dès la couverture, avec le temps de l'écran, qui suit le minuteur.
    assert.equal(r.split.value, 1);
    assert.ok(env.texts.includes("Évaluation gratuite d'anglais"));
    assert.ok(env.texts.includes('5:00'));
    clock = '4:59';
    env.now += 1000;
    r.captureTeacher(t);
    assert.ok(env.texts.includes('4:59'));
    assert.equal(env.texts.filter((text) => LEVEL.test(text)).length, 0);

    // Verdict (même signal que l'écran) : le niveau et son libellé.
    r.revealLevel('A2', 'Élémentaire');
    env.now += 50;
    r.captureTeacher(t);
    assert.ok(env.texts.includes('A2') && env.texts.includes('Élémentaire'));
    assert.equal(r.split.value, 1);
});
