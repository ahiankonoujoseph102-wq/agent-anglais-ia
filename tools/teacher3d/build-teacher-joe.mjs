/*
 * Génère le modèle 3D de « Teacher Joe » : public/teacher3d/models/teacher-joe.glb
 *
 * Personnage original, entièrement construit par ce script (aucune ressource
 * externe) et placé dans le domaine public (CC0). Il suit les conventions
 * courantes des avatars parlants, pour pouvoir être remplacé par un autre
 * fichier GLB :
 *   - squelette aux noms Mixamo (Hips, Spine, Neck, Head, LeftArm…) ;
 *   - formes de bouche Oculus (viseme_aa, viseme_PP…) ;
 *   - expressions aux noms ARKit (mouthSmileLeft, eyeBlinkLeft, browInnerUp…).
 *
 * Repère glTF : Y vers le haut, le personnage regarde vers +Z, sa gauche est +X.
 * Unités : mètres. Le personnage est assis, les avant-bras posés sur un bureau.
 *
 * Utilisation (en local uniquement) : npm install && npm run model
 */

import { Document, NodeIO } from '@gltf-transform/core';
import { ALL_EXTENSIONS } from '@gltf-transform/extensions';
import { dedup, meshopt, prune, quantize, reorder } from '@gltf-transform/functions';
import { MeshoptEncoder } from 'meshoptimizer';
import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

// TEACHER=rose : Teacher Rose, deuxième professeure (même squelette, mêmes visèmes, autre apparence).
// Sans variable : Teacher Joe, fichier identique à l'octet près à celui d'avant.
const ROSE = process.env.TEACHER === 'rose';
const OUTPUT = resolve(dirname(fileURLToPath(import.meta.url)), `../../public/teacher3d/models/${ROSE ? 'teacher-rose' : 'teacher-joe'}.glb`);

/* ---------- Outils mathématiques ---------- */

const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
const lerp = (a, b, t) => a + (b - a) * t;
const smooth = (e0, e1, x) => {
    const t = clamp((x - e0) / (e1 - e0));

    return t * t * (3 - 2 * t);
};
const add = (a, b) => [a[0] + b[0], a[1] + b[1], a[2] + b[2]];
const sub = (a, b) => [a[0] - b[0], a[1] - b[1], a[2] - b[2]];
const mul = (a, s) => [a[0] * s, a[1] * s, a[2] * s];
const dot = (a, b) => a[0] * b[0] + a[1] * b[1] + a[2] * b[2];
const cross = (a, b) => [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]];
const len = (a) => Math.hypot(a[0], a[1], a[2]);
const norm = (a) => mul(a, 1 / (len(a) || 1));
const gauss = (dx, dy, rx, ry = rx) => Math.exp(-((dx * dx) / (rx * rx) + (dy * dy) / (ry * ry)));

// Interpolation douce dans une table [[x, y], …] triée par x.
function table(rows, x) {
    if (x <= rows[0][0]) {
        return rows[0][1];
    }

    for (let i = 1; i < rows.length; i++) {
        if (x <= rows[i][0]) {
            const t = (x - rows[i - 1][0]) / (rows[i][0] - rows[i - 1][0]);

            return lerp(rows[i - 1][1], rows[i][1], t * t * (3 - 2 * t));
        }
    }

    return rows[rows.length - 1][1];
}

// Couleur sRGB (#rrggbb) → linéaire, comme l'attend COLOR_0.
function color(hex) {
    const c = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255);

    return c.map((v) => (v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4));
}

const mix = (a, b, t) => [lerp(a[0], b[0], t), lerp(a[1], b[1], t), lerp(a[2], b[2], t)];

const C = {
    skin: color('#7b4a30'),
    skinShadow: color('#5a3220'),
    nostril: color('#2e1810'),
    lips: color('#5e2f27'),
    lipsInner: color('#7d3c35'),
    mouth: color('#2b0f0e'),
    teeth: color('#f1ece2'),
    tongue: color('#b45550'),
    brows: color('#1a1210'),
    lash: color('#120c0a'),
    beard: color('#2a1a12'),
    hair: color('#1b1411'),
    glasses: color('#2a2320'),
    sclera: color('#f4efe6'),
    iris: color('#4a2a17'),
    irisRim: color('#24140b'),
    pupil: color('#0b0807'),
    catchlight: color('#ffffff'),
    shirt: color('#e9eff7'),
    vest: color('#0E2A5C'),     // bleu nuit Talkami
    vestDark: color('#0A1F45'),
    tie: color('#FF7A1A'),      // orange vif Talkami
    tieDark: color('#D9620F'),
};

// Teacher Rose : personnage original et stylisé (peau brun chaud, tresses fines auburn, foulard orange).
if (ROSE) {
    Object.assign(C, {
        // Peau lisse et lumineuse, brun chaud ; ombres très douces (jamais d'ombre entre le nez et la bouche).
        skin: color('#93593b'),
        skinShadow: color('#7e4a30'),
        nostril: color('#6e3d27'),
        lips: color('#8c3542'),
        lipsInner: color('#a24c58'),
        blush: color('#b0604f'),
        brows: color('#2a150c'),
        lash: color('#140a07'),
        // Yeux vifs : iris brun chaud, reflet plus grand.
        iris: color('#7a4220'),
        irisRim: color('#2c1508'),
        sclera: color('#fbf7f1'),
        hair: color('#4a1f0e'),
        hairDark: color('#24100a'),
    });
}

/* ---------- Squelette (noms Mixamo) ---------- */

const BONES = [
    ['Hips', null, [0, 0.0, 0]],
    ['Spine', 'Hips', [0, 0.10, 0]],
    ['Spine1', 'Spine', [0, 0.22, 0.004]],
    ['Spine2', 'Spine1', [0, 0.34, 0]],
    ['Neck', 'Spine2', [0, 0.47, -0.006]],
    ['Head', 'Neck', [0, 0.585, 0]],
    ['HeadTop_End', 'Head', [0, 0.86, 0]],
];

const HEAD = [0, 0.668, 0]; // centre de la tête
const HEAD_SCALE = 1.14; // tête un peu plus grande, comme dans un dessin animé

// Agrandit autour du centre de la tête une pièce construite à l'échelle 1.
function scaleHead(b) {
    b.positions = b.positions.map((p) => add(HEAD, mul(sub(p, HEAD), HEAD_SCALE)));

    return b;
}
const EYE_X = 0.043;
const EYE_Y = 0.012; // par rapport au centre de la tête
const EYE_R = ROSE ? 0.0276 : 0.0255;
const MOUTH_Y = -0.069;

// Bras gauche (+X) ; le bras droit est son symétrique.
const ARM = {
    shoulder: [0.03, 0.44, -0.006],
    arm: [0.185, 0.415, -0.010],
    elbow: [0.236, 0.205, -0.012],
    wrist: [0.172, 0.200, 0.226],
    handEnd: [0.150, 0.190, 0.318],
};

const mirror = (p) => [-p[0], p[1], p[2]];

/* ---------- Construction des pièces ---------- */

/**
 * Une pièce = sommets (position, couleur, poids) + triangles. Les formes
 * (morph targets) sont obtenues en reconstruisant la pièce avec d'autres
 * paramètres : la topologie ne change jamais.
 */
class Builder {
    constructor() {
        this.positions = [];
        this.colors = [];
        this.skins = []; // [[os, poids], …]
        this.indices = [];
        this.smoothGroups = [];
    }

    vertex(p, c, skin, group = 0) {
        this.positions.push(p);
        this.colors.push(c);
        this.skins.push(skin);
        this.smoothGroups.push(group);

        return this.positions.length - 1;
    }

    tri(a, b, c) {
        this.indices.push(a, b, c);
    }

    quad(a, b, c, d) {
        this.tri(a, b, c);
        this.tri(a, c, d);
    }

    // Grille rows × cols (indices de sommets), fermée en colonnes si wrap.
    grid(ids, wrap = false, flip = false) {
        for (let i = 0; i < ids.length - 1; i++) {
            const n = ids[i].length;

            for (let j = 0; j < (wrap ? n : n - 1); j++) {
                const a = ids[i][j];
                const b = ids[i][(j + 1) % n];
                const c = ids[i + 1][(j + 1) % n];
                const d = ids[i + 1][j];

                if (flip) {
                    this.quad(a, d, c, b);
                } else {
                    this.quad(a, b, c, d);
                }
            }
        }
    }
}

/* ---------- Tête : surface analytique ---------- */

const AX = 0.112;
const AY = 0.130;
const AZ = 0.122;

const widthProfile = ROSE
    ? (dy) => table([[-1, 0.42], [-0.85, 0.60], [-0.55, 0.84], [-0.2, 0.97], [0.1, 1.0], [0.5, 0.97], [1, 0.90]], dy)
    : (dy) => table([[-1, 0.56], [-0.85, 0.72], [-0.55, 0.90], [-0.2, 0.99], [0.1, 1.0], [0.5, 0.98], [1, 0.92]], dy);
// Teacher Rose (visage de dessin animé) : petit nez court et doux, lèvres pleines, sourcils fins et arqués,
// sourire au repos, pommettes hautes, reliefs autour de la bouche très légers (aucune ombre de moustache).
const NOSE = ROSE ? 0.55 : 1;
const NOSE_TIP = ROSE ? 0.034 : 0.041;
const NOSE_RIDGE = ROSE ? 0.22 : 1;
const LIPS = ROSE ? 1.22 : 1;
const BROW = ROSE ? 0.42 : 1;
const REST_SMILE = ROSE ? 0.7 : 0;
const SMILE_LIFT = ROSE ? 1.8 : 1;
const MOUTH_W = ROSE ? 0.026 : 0.030;
const CHEEKS = ROSE ? 0.011 : 0.007;
const PHILTRUM = ROSE ? 0.0015 : 0.004;
const frontDepth = (dy) => table([[-1, 0.8], [-0.6, 0.94], [0, 1.0], [0.6, 0.95], [1, 0.9]], dy);
const backDepth = (dy) => table([[-1, 0.7], [-0.5, 0.85], [0, 1.05], [0.6, 1.06], [1, 0.95]], dy);

// Reliefs du visage (nez, arcades, joues, bouche, menton), ajoutés vers l'avant.
function faceRelief(x, y) {
    const ax = Math.abs(x);
    let r = 0;
    // Arête du nez, de plus en plus saillante vers le bout.
    const t = clamp((0.024 - y) / 0.062);
    r += NOSE * NOSE_RIDGE * lerp(0.004, 0.020, t * t) * gauss(x, 0, 0.0105) * smooth(0.034, 0.018, y) * smooth(-0.058, -0.044, y);
    r += NOSE * 0.019 * gauss(x, y + NOSE_TIP, 0.0165);
    r += NOSE * 0.012 * gauss(ax - 0.020, y + NOSE_TIP + 0.005, 0.0115);
    r -= 0.010 * gauss(ax - EYE_X, y - EYE_Y, 0.022);
    r += 0.006 * gauss(ax - 0.040, y - 0.042, 0.030, 0.012);
    r += CHEEKS * gauss(ax - 0.060, y + (ROSE ? 0.018 : 0.026), 0.028);
    r += 0.012 * gauss(x, y - MOUTH_Y, 0.046, 0.030);
    r += PHILTRUM * gauss(x, y - MOUTH_Y - 0.020, 0.012, 0.008);
    r -= (ROSE ? 0.0012 : 0.003) * gauss(x, y - MOUTH_Y + 0.022, 0.018, 0.005);
    r += 0.007 * gauss(x, y + 0.108, 0.026, 0.014);

    return r;
}

const frontWeight = (dz) => smooth(0.15, 0.75, dz);

// Point de la tête (repère du centre de la tête) pour une direction unitaire.
function headPoint(dx, dy, dz) {
    const x = dx * AX * widthProfile(dy);
    const y = dy * AY;
    let z = dz * AZ * (dz >= 0 ? frontDepth(dy) : backDepth(dy));
    z += faceRelief(x, y) * frontWeight(dz);

    return [x, y, z];
}

// Profondeur de la surface avant au point (x, y) du visage.
function surfaceZ(x, y) {
    const dy = clamp(y / AY, -0.999, 0.999);
    const dx = x / (AX * widthProfile(dy));
    const dz = Math.sqrt(Math.max(0, 1 - dx * dx - dy * dy));

    return dz * AZ * frontDepth(dy) + faceRelief(x, y) * frontWeight(dz);
}

// Mâchoire : déplacement vers le bas de la partie basse du visage.
function jawWeight(x, y, z) {
    return smooth(MOUTH_Y + 0.004, MOUTH_Y - 0.016, y)
        * (1 - smooth(0.04, 0.09, Math.abs(x)))
        * smooth(-0.03, 0.05, z)
        * (1 - smooth(-0.13, -0.17, y) * 0.3);
}

const JAW_DROP = 0.019;

/* ---------- Paramètres d'expression ---------- */

const NEUTRAL = {
    open: 0, wide: 0, round: 0, smileL: 0, smileR: 0, ff: 0, press: 0, th: 0,
    blinkL: 0, blinkR: 0, squintL: 0, squintR: 0, wideEyeL: 0, wideEyeR: 0,
    browInner: 0, browDownL: 0, browDownR: 0, browOuterL: 0, browOuterR: 0,
};

// Formes de bouche Oculus (15 visèmes) puis expressions ARKit.
const SHAPES = {
    viseme_sil: {},
    viseme_PP: { press: 1 },
    viseme_FF: { ff: 1, open: 0.12 },
    viseme_TH: { open: 0.22, th: 1, wide: 0.1 },
    viseme_DD: { open: 0.30, wide: 0.2 },
    viseme_kk: { open: 0.36, wide: 0.15 },
    viseme_CH: { open: 0.20, round: 0.55, wide: -0.1 },
    viseme_SS: { open: 0.10, wide: 0.5 },
    viseme_nn: { open: 0.16, wide: 0.15 },
    viseme_RR: { open: 0.22, round: 0.45 },
    viseme_aa: { open: 1, wide: 0.1 },
    viseme_E: { open: 0.55, wide: 0.45 },
    viseme_I: { open: 0.30, wide: 0.65 },
    viseme_O: { open: 0.65, round: 0.8 },
    viseme_U: { open: 0.22, round: 1 },
    jawOpen: { open: 1 },
    mouthSmileLeft: { smileL: 1 },
    mouthSmileRight: { smileR: 1 },
    mouthFrownLeft: { smileL: -1 },
    mouthFrownRight: { smileR: -1 },
    mouthPucker: { round: 1 },
    eyeBlinkLeft: { blinkL: 1 },
    eyeBlinkRight: { blinkR: 1 },
    eyeSquintLeft: { squintL: 1 },
    eyeSquintRight: { squintR: 1 },
    eyeWideLeft: { wideEyeL: 1 },
    eyeWideRight: { wideEyeR: 1 },
    browInnerUp: { browInner: 1 },
    browDownLeft: { browDownL: 1 },
    browDownRight: { browDownR: 1 },
    browOuterUpLeft: { browOuterL: 1 },
    browOuterUpRight: { browOuterR: 1 },
};

const headSkin = [['Head', 1]];

/* ---------- Visage : tête, oreilles, paupières, sourcils, bouche ---------- */

function buildFace(p) {
    const b = new Builder();
    const at = (q) => add(HEAD, q);

    // Tête : grille latitude/longitude, plus dense au centre du visage.
    const LON = 100;
    const LAT = 72;
    const rows = [];

    for (let i = 0; i <= LAT; i++) {
        const s = -1 + (2 * i) / LAT;
        const theta = (Math.PI / 2) * (0.55 * s + 0.45 * s * s * s);
        const row = [];

        for (let j = 0; j < LON; j++) {
            const t = -1 + (2 * j) / LON;
            const phi = Math.PI * (0.42 * t + 0.58 * t * t * t);
            const dx = Math.cos(theta) * Math.sin(phi);
            const dy = Math.sin(theta);
            const dz = Math.cos(theta) * Math.cos(phi);
            let q = headPoint(dx, dy, dz);
            const [x, y, z] = q;

            // Mâchoire.
            q = add(q, [0, -JAW_DROP * p.open * jawWeight(x, y, z), 0]);
            // Sous la bouche ouverte, la peau recule : elle ne traverse pas l'intérieur de la bouche.
            const gap = Math.min(1, 5 * (p.open + 0.4 * p.th + 0.4 * p.ff));
            q = add(q, [0, 0, -0.02 * gap * (1 - smooth(0.022, 0.036, Math.abs(x))) * smooth(MOUTH_Y + 0.012, MOUTH_Y + 0.004, y) * smooth(MOUTH_Y - 0.030, MOUTH_Y - 0.016, y) * frontWeight(dz)]);

            // Joues qui remontent avec le sourire.
            for (const [side, amount] of [[1, p.smileL], [-1, p.smileR]]) {
                if (amount > 0) {
                    const g = gauss(x - side * 0.052, y + 0.040, 0.026) * frontWeight(dz) * amount;
                    q = add(q, [side * 0.0015 * g, 0.004 * g, 0.0025 * g]);
                }
            }

            let c = C.skin;
            // Narines, ombre sous le nez et légère barbe de quelques jours.
            const nostril = ROSE
                ? gauss(Math.abs(x) - 0.010, y + NOSE_TIP + 0.0105, 0.0030, 0.0018) * smooth(0.10, 0.12, z)
                : gauss(Math.abs(x) - 0.0115, y + 0.0535, 0.0042, 0.0028) * smooth(0.10, 0.12, z);
            c = mix(c, C.nostril, clamp(nostril * (ROSE ? 0.6 : 1.3)));
            const beard = smooth(-0.02, 0.03, z) * smooth(-0.090, -0.108, y) * (1 - smooth(-0.15, -0.17, y)) * (1 - smooth(0.05, 0.085, Math.abs(x)));
            if (ROSE) {
                const cheek = gauss(Math.abs(x) - 0.056, y + 0.030, 0.028) * frontWeight(dz);
                c = mix(c, C.blush, clamp(cheek) * 0.4);
            } else {
                c = mix(c, C.beard, clamp(beard) * 0.22);
            }
            row.push(b.vertex(at(q), c, headSkin));
        }

        rows.push(row);
    }

    // Les pôles : sommets confondus, triangles dégénérés sans conséquence.
    b.grid(rows, true);

    // Oreilles.
    for (const side of [1, -1]) {
        const center = [side * 0.106, -0.004, -0.006];
        const ring = [];
        const N = 20;
        const M = 10;

        for (let i = 0; i <= M; i++) {
            const v = -Math.PI / 2 + (Math.PI * i) / M;
            const r = [];

            for (let j = 0; j < N; j++) {
                const u = (2 * Math.PI * j) / N;
                const ex = Math.cos(v) * Math.cos(u);
                const ey = Math.sin(v);
                const ez = Math.cos(v) * Math.sin(u);
                // Creux de la conque sur la face extérieure.
                const dent = ex > 0 ? -0.6 * ex * Math.cos(v) * gauss(ey, ez, 0.6) : 0;
                const q = [center[0] + side * (0.004 + 0.011 * (ex + dent)), center[1] + 0.031 * ey, center[2] + 0.020 * ez - 0.006 * ey];
                const c = mix(C.skin, C.skinShadow, clamp(-dent * 1.4));
                r.push(b.vertex(at(q), c, headSkin));
            }

            ring.push(r);
        }

        b.grid(ring, true, side > 0);
    }

    // Paupières (coquilles autour des yeux) : supérieure et inférieure.
    for (const [side, blink, squint, wide] of [[1, p.blinkL, p.squintL, p.wideEyeL], [-1, p.blinkR, p.squintR, p.wideEyeR]]) {
        const eye = eyeCenter(side);
        const R = EYE_R + 0.0016;
        const N = 28;
        const deg = Math.PI / 180;

        // Paupière supérieure : du haut vers le bord libre.
        const upper = [];
        const UR = 10;

        for (let i = 0; i <= UR + 1; i++) {
            const row = [];

            for (let j = 0; j <= N; j++) {
                const a = (-105 + (210 * j) / N) * deg;
                const ca = Math.cos(a);
                const openEdge = ((ROSE ? 39 : 33) + 9 * wide - 13 * squint) * Math.max(0, ca) ** 0.6 - 3;
                const closedEdge = -14 * Math.max(0, ca) ** 0.5 - 4;
                const edge = lerp(openEdge, closedEdge, blink) * deg;
                const top = 82 * deg;
                const k = Math.min(i, UR) / UR;
                const beta = lerp(top, edge, k);
                const r = R + (i > UR ? 0.0011 : 0) + 0.0006 * Math.sin(Math.PI * k);
                const d = [Math.sin(a) * Math.cos(beta), Math.sin(beta), Math.cos(a) * Math.cos(beta)];
                const q = add(eye, mul([side * d[0], d[1], d[2]], r));
                const c = i >= UR - 1 ? C.lash : C.skin;
                row.push(b.vertex(q, c, headSkin, 1));
            }

            upper.push(row);
        }

        b.grid(upper, false, side > 0);

        // Teacher Rose : cils, petites pointes au bord de la paupière, plus longues vers le coin extérieur.
        if (ROSE) {
            const edgeRow = upper[UR + 1];

            for (let j = 6; j < N - 1; j++) {
                const outer = side * Math.sin((-105 + (210 * j) / N) * deg);
                const length = 0.0015 + 0.0027 * smooth(-0.3, 0.9, outer);
                const p0 = b.positions[edgeRow[j]];
                const p1 = b.positions[edgeRow[j + 1]];
                const mid = mul(add(p0, p1), 0.5);
                const outward = norm(sub(mid, eye));
                const tip = add(mid, add(mul(outward, length), [side * 0.0014 * Math.max(0, outer), 0.0006, 0]));
                const t0 = b.vertex(p0, C.lash, headSkin, 1);
                const t1 = b.vertex(p1, C.lash, headSkin, 1);
                const t2 = b.vertex(tip, C.lash, headSkin, 1);
                b.tri(t0, t1, t2);
                b.tri(t0, t2, t1);
            }
        }

        // Paupière inférieure.
        const lower = [];
        const LR = 6;

        for (let i = 0; i <= LR; i++) {
            const row = [];

            for (let j = 0; j <= N; j++) {
                const a = (-105 + (210 * j) / N) * deg;
                const ca = Math.max(0, Math.cos(a));
                const edge = (-(ROSE ? 29 : 30) * ca ** 0.5 + 15 * squint * ca) * deg - 6 * deg;
                const beta = lerp(-80 * deg, edge, i / LR);
                const d = [Math.sin(a) * Math.cos(beta), Math.sin(beta), Math.cos(a) * Math.cos(beta)];
                const q = add(eye, mul([side * d[0], d[1], d[2]], R - 0.0002));
                const c = i === LR ? C.skinShadow : C.skin;
                row.push(b.vertex(q, c, headSkin, 2));
            }

            lower.push(row);
        }

        b.grid(lower, false, side < 0);
    }

    // Sourcils.
    for (const side of [1, -1]) {
        const inner = p.browInner;
        const down = side > 0 ? p.browDownL : p.browDownR;
        const outer = side > 0 ? p.browOuterL : p.browOuterR;
        const S = 16;
        const K = 8;
        const rings = [];

        for (let i = 0; i <= S; i++) {
            const t = i / S;
            const x = 0.019 + 0.052 * t;
            let y = (ROSE ? 0.052 : 0.046) + (ROSE ? 0.011 : 0.009) * Math.sin(Math.PI * Math.min(1, t * 1.15)) - 0.004 * t;
            y += 0.010 * inner * (1 - t) ** 1.2 - (0.003 + 0.004 * (1 - t)) * down + 0.008 * outer * t ** 1.3;
            const z = surfaceZ(x, y) + 0.0024;
            const halfH = BROW * lerp(0.0052, 0.0026, t) * Math.sin(Math.PI * clamp(t * 6, 0.25, 1) / 2) * (i === 0 || i === S ? 0.35 : 1);
            const halfD = 0.0019;
            const ring = [];

            for (let k = 0; k < K; k++) {
                const u = (2 * Math.PI * k) / K;
                const q = [side * x, y + halfH * Math.sin(u), z + halfD * Math.cos(u) - 0.0006];
                ring.push(b.vertex(at(q), C.brows, headSkin, 3));
            }

            rings.push(ring);
        }

        b.grid(rings, true, side > 0);
    }

    buildMouth(b, p, at);

    return scaleHead(b);
}

function eyeCenter(side) {
    const x = side * EYE_X;

    return add(HEAD, [x, EYE_Y, surfaceZ(EYE_X, EYE_Y) - 0.0105]);
}

/*
 * Bouche : une pièce posée sur le visage (intérieur sombre, dents, langue,
 * lèvres, puis un anneau de peau qui se fond dans la tête). La mâchoire
 * abaisse la lèvre inférieure de la même quantité que la peau du menton.
 */
function buildMouth(b, p, at) {
    const N = 40;
    const A = MOUTH_W; // demi-largeur de la bouche
    const smileAt = (cos) => (cos >= 0 ? p.smileL : p.smileR);
    const W = A * (1 + 0.16 * p.wide - 0.30 * p.round);
    const jaw = JAW_DROP * p.open;
    const openUp = 0.0035 * p.open + 0.0015 * p.th + 0.0012 * p.ff;
    const press = 1 - 0.40 * p.press;
    const bu = LIPS * 0.0078 * press * (1 + 0.15 * p.round);
    const bl = LIPS * 0.0098 * press * (1 + 0.15 * p.round) * (1 - 0.25 * p.ff);
    const lipOut = 1 + 0.9 * p.round + 0.3 * p.press;

    // Position locale (x, y relatifs au centre de la bouche) + hauteur au-dessus de la peau.
    const place = (x, yLocal, lift, jawShare) => {
        const y = MOUTH_Y + yLocal;
        const z = surfaceZ(x, y) + lift;

        return at([x, y - jaw * jawShare, z]);
    };
    // Intérieur, dents et langue : à plat, juste devant la peau reculée.
    const placeInside = (x, yLocal, lift, jawShare) => at([x, MOUTH_Y + yLocal - jaw * jawShare, surfaceZ(x, MOUTH_Y) + lift]);

    const theta = (j) => (2 * Math.PI * j) / N;

    // Coins de la bouche : sourire (haut et dehors) ou moue (bas).
    const corner = (cos) => {
        const s = smileAt(cos) + REST_SMILE;
        const c2 = cos * cos;

        return { dx: Math.sign(cos) * (0.0045 * SMILE_LIFT * Math.max(0, s) - 0.001 * Math.max(0, -s)) * c2, dy: (0.0055 * SMILE_LIFT * s) * c2 * c2 };
    };

    // Bord intérieur des lèvres pour l'angle a.
    const innerEdge = (a) => {
        const cos = Math.cos(a);
        const sin = Math.sin(a);
        const k = corner(cos);
        const x = W * 0.90 * cos * (1 - 0.30 * p.round - 0.10 * p.open) + k.dx;
        const y = sin >= 0 ? openUp * Math.abs(sin) ** 1.2 : 0;

        return { x, y: y + k.dy, jawShare: sin < 0 ? Math.min(1, Math.abs(sin) * 3) : 0 };
    };

    const outerEdge = (a) => {
        const cos = Math.cos(a);
        const sin = Math.sin(a);
        const k = corner(cos);
        const x = W * cos + k.dx * 1.2;
        const y = sin >= 0 ? (bu + openUp * 0.8) * Math.abs(sin) ** 0.9 : -bl * Math.abs(sin) ** 0.9;

        return { x, y: y + k.dy * 0.9, jawShare: sin < 0 ? Math.min(1, Math.abs(sin) * 3) : 0 };
    };

    const skinEdge = (a) => {
        const cos = Math.cos(a);
        const sin = Math.sin(a);
        const k = corner(cos);
        const x = (W + 0.013) * cos + k.dx * 0.6;
        const y = sin >= 0 ? (bu + 0.010 + openUp * 0.4) * sin : -(bl + 0.012) * Math.abs(sin);
        // L'anneau de peau suit la peau de la tête (même poids de mâchoire).
        const share = jawWeight(x, MOUTH_Y + y, surfaceZ(x, MOUTH_Y + y));

        return { x, y: y + k.dy * 0.4, jawShare: share };
    };

    // Intérieur de la bouche (sombre) : centre → bord intérieur.
    const IR = 4;
    const interior = [];
    const centerRow = [];

    for (let j = 0; j < N; j++) {
        centerRow.push(b.vertex(placeInside(0, 0, -0.0030, 0.5), C.mouth, headSkin, 10));
    }

    interior.push(centerRow);

    for (let i = 1; i <= IR; i++) {
        const row = [];

        for (let j = 0; j < N; j++) {
            const e = innerEdge(theta(j));
            const t = i / IR;
            row.push(b.vertex(placeInside(e.x * t, e.y * t, lerp(-0.0030, -0.0005, t ** 3), lerp(0.5, e.jawShare, t)), C.mouth, headSkin, 10));
        }

        interior.push(row);
    }

    b.grid(interior, true, true);

    // Lèvres : bord intérieur → bord extérieur, bombées vers l'avant.
    const LR = 7;
    const lips = [];

    for (let i = 0; i <= LR; i++) {
        const row = [];
        const t = i / LR;

        for (let j = 0; j < N; j++) {
            const a = theta(j);
            const e = innerEdge(a);
            const o = outerEdge(a);
            const upper = Math.sin(a) >= 0;
            const bulge = LIPS * (upper ? 0.0034 : 0.0044) * lipOut * Math.sin(Math.PI * (0.15 + 0.85 * t)) * (1 - 0.6 * Math.cos(a) ** 8);
            // Lèvre inférieure rentrée sous les dents du haut (F, V).
            const tuck = !upper ? p.ff * 0.004 * (1 - t) : 0;
            const x = lerp(e.x, o.x, t);
            const y = lerp(e.y, o.y, t) + (!upper ? p.ff * 0.004 * (1 - t) : 0);
            const c = t < 0.3 ? mix(C.lipsInner, C.lips, t / 0.3) : C.lips;
            row.push(b.vertex(place(x, y, 0.0014 + bulge - tuck, lerp(e.jawShare, o.jawShare, t)), c, headSkin, 11));
        }

        lips.push(row);
    }

    b.grid(lips, true, true);

    // Anneau de peau : bord extérieur des lèvres → peau du visage.
    const SR = 4;
    const ring = [];

    for (let i = 0; i <= SR; i++) {
        const row = [];
        const t = i / SR;

        for (let j = 0; j < N; j++) {
            const a = theta(j);
            const o = outerEdge(a);
            const s = skinEdge(a);
            const x = lerp(o.x, s.x, t);
            const y = lerp(o.y, s.y, t);
            const lift = lerp(0.0014, -0.0012, t);
            const c = i === 0 ? mix(C.lips, C.skin, 0.4) : C.skin;
            row.push(b.vertex(place(x, y, lift, lerp(o.jawShare, s.jawShare, t)), c, headSkin, 12));
        }

        ring.push(row);
    }

    b.grid(ring, true, true);

    // Dents du haut (fixes) et du bas (suivent la mâchoire), langue.
    const teeth = (top, share, height, lift) => {
        const rowsT = [];

        for (let i = 0; i <= 1; i++) {
            const row = [];

            for (let j = 0; j <= 16; j++) {
                const u = -1 + j / 8;
                const x = W * 0.62 * u * (1 - 0.2 * p.round);
                // Dents plus courtes vers les coins, où les lèvres sont fines.
                const y = top - height * i * (1 - 0.75 * u ** 4) - 0.0012 * u * u;
                row.push(b.vertex(placeInside(x, y, lift, share), C.teeth, headSkin, 13));
            }

            rowsT.push(row);
        }

        b.grid(rowsT, false, true);
    };

    teeth(0.0016 + openUp * 0.5, 0, 0.0062, -0.0017);
    teeth(0.0004, 1, 0.0045, -0.0019);

    const tongue = [];

    for (let i = 0; i <= 3; i++) {
        const row = [];

        for (let j = 0; j <= 12; j++) {
            const u = -1 + j / 6;
            const v = i / 3;
            const x = W * 0.62 * u * Math.sqrt(1 - 0.6 * v * v);
            const y = -0.0045 + 0.0035 * v + p.th * 0.0062 - 0.001 * u * u;
            row.push(b.vertex(placeInside(x, y, -0.0022 + p.th * 0.0035, 1), C.tongue, headSkin, 14));
        }

        tongue.push(row);
    }

    b.grid(tongue, false);
}

/* ---------- Yeux ---------- */

function buildEyes() {
    const b = new Builder();

    for (const side of [1, -1]) {
        const center = eyeCenter(side);
        const bone = side > 0 ? 'LeftEye' : 'RightEye';
        const skin = [[bone, 1]];
        // Anneaux autour de l'axe du regard (+Z), avec des bords nets.
        const stops = [0, 5, 10, 10, 17, 24.5, 24.5, 27, 27, 42, 70, 100, 130, 160, 180];
        const colorAt = (g, k) => {
            if (g < 10 || (g === 10 && stops[k + 1] === 10)) {
                return C.pupil;
            }

            if (g < 24.5 || (g === 24.5 && stops[k + 1] === 24.5)) {
                return mix(C.iris, C.irisRim, smooth(16, 24.5, g) * 0.8);
            }

            if (g < 27 || (g === 27 && stops[k + 1] === 27)) {
                return C.irisRim;
            }

            return C.sclera;
        };
        const N = 24;
        const rows = [];

        stops.forEach((g, k) => {
            const row = [];
            const gr = (g * Math.PI) / 180;
            // L'iris est très légèrement bombé.
            const r = EYE_R * (g < 27 ? 1.004 : 1);

            for (let j = 0; j < N; j++) {
                const u = (2 * Math.PI * j) / N;
                const d = [Math.sin(gr) * Math.cos(u), Math.sin(gr) * Math.sin(u), Math.cos(gr)];
                row.push(b.vertex(add(center, mul(d, r)), colorAt(g, k), skin, 20 + k));
            }

            rows.push(row);
        });

        b.grid(rows, true, true);

        // Reflet lumineux.
        const hl = add(center, mul(norm([-0.25 * side - 0.05, 0.28, 1]), EYE_R + 0.0006));
        const c0 = b.vertex(hl, C.catchlight, skin, 40);
        const ring = [];

        for (let j = 0; j < 8; j++) {
            const u = (2 * Math.PI * j) / 8;
            ring.push(b.vertex(add(hl, [(ROSE ? 0.0030 : 0.0021) * Math.cos(u), (ROSE ? 0.0030 : 0.0021) * Math.sin(u), -0.0003]), C.catchlight, skin, 40));
        }

        for (let j = 0; j < 8; j++) {
            b.tri(c0, ring[j], ring[(j + 1) % 8]);
        }
    }

    return scaleHead(b);
}

/* ---------- Cheveux et lunettes ---------- */

// Ligne des cheveux : latitude (dy) en fonction de la longitude (0 = front).
const hairline = (phi) => table([
    [0, 0.53], [0.30, 0.50], [0.52, 0.40], [0.75, 0.33], [1.05, 0.28], [1.30, 0.10], [1.45, 0.05],
    [1.62, 0.16], [1.95, 0.02], [2.4, -0.22], [Math.PI, -0.36],
], Math.abs(phi));

function buildHairAndGlasses() {
    const b = new Builder();
    const LON = 112;
    const ROWS = 16;
    const rows = [];

    for (let i = 0; i <= ROWS; i++) {
        const row = [];

        for (let j = 0; j < LON; j++) {
            const phi = -Math.PI + (2 * Math.PI * j) / LON;
            // Teacher Rose : frange de côté, plus haute d'un côté, bord légèrement ondulé.
            const dyEdge = ROSE
                ? hairline(phi) + 0.20 * smooth(1.1, 0, Math.abs(phi)) - 0.05 * smooth(0.5, 0, Math.abs(phi))
                : hairline(phi);
            const k = i / ROWS;
            const dy = lerp(dyEdge, 1, 1 - (1 - k) ** 1.6);
            const c = Math.sqrt(Math.max(0, 1 - dy * dy));
            const dx = c * Math.sin(phi);
            const dz = c * Math.cos(phi);
            const base = headPoint(dx, dy, dz);
            // Épaisseur : fine au bord, plus de volume sur le dessus.
            const thick = ROSE
                ? lerp(0.0016, 0.007 + 0.006 * smooth(0.3, 1, dy), smooth(0, 0.30, k))
                : lerp(0.0018, 0.010 + 0.006 * smooth(0.3, 1, dy), smooth(0, 0.22, k));
            // Petites bosses pour l'aspect des cheveux crépus coupés courts.
            // Teacher Rose : sillons des tresses, de l'avant vers l'arrière.
            const bump = ROSE
                ? 0.0013 * Math.sin(phi * 40) * smooth(0, 0.3, k)
                : 0.0009 * Math.sin(dx * 260 + dy * 90) * Math.sin(dz * 240 - dy * 70) * smooth(0, 0.3, k);
            const n = norm([dx / AX, dy / AY, dz / AZ]);
            const q = add(base, mul(n, thick + bump));
            // Teacher Rose : mèches en biais sur le devant (frange de côté).
            const strand = ROSE ? phi * 40 : 0;
            const hairColor = ROSE ? mix(C.hair, C.hairDark, 0.5 + 0.5 * Math.sin(strand)) : C.hair;
            row.push(b.vertex(add(HEAD, q), mix(hairColor, C.skin, (1 - smooth(0, 0.12, k)) * 0.35), headSkin, 30));
        }

        rows.push(row);
    }

    b.grid(rows, true);

    // Lunettes : deux montures arrondies, un pont, deux branches.
    const tube = (points, radius, sides = 6, closed = false, paint = () => C.glasses, group = 31) => {
        const rings = [];
        const n = points.length;

        for (let i = 0; i < n; i++) {
            const prev = points[closed ? (i - 1 + n) % n : Math.max(0, i - 1)];
            const next = points[closed ? (i + 1) % n : Math.min(n - 1, i + 1)];
            const t = norm(sub(next, prev));
            let u = cross(t, [0, 1, 0]);
            u = len(u) < 1e-4 ? cross(t, [1, 0, 0]) : u;
            u = norm(u);
            const v = norm(cross(u, t));
            const ring = [];

            for (let k = 0; k < sides; k++) {
                const a = (2 * Math.PI * k) / sides;
                const off = add(mul(u, Math.cos(a) * radius), mul(v, Math.sin(a) * radius));
                ring.push(b.vertex(add(points[i], off), paint(i, k), headSkin, group));
            }

            rings.push(ring);
        }

        if (closed) {
            rings.push(rings[0]);
        }

        b.grid(rings, true, true);
    };

    if (ROSE) {
        buildBraidsAndEarrings(b, tube);

        return scaleHead(b);
    }

    for (const side of [1, -1]) {
        const eye = eyeCenter(side);
        const zf = eye[2] + EYE_R + 0.0105;
        const frame = [];
        const hw = 0.0262;
        const hh = 0.0205;
        const rc = 0.0125;

        for (let k = 0; k < 40; k++) {
            const a = (2 * Math.PI * k) / 40;
            // Rectangle arrondi (superellipse).
            const ex = Math.sign(Math.cos(a)) * Math.abs(Math.cos(a)) ** (2 / 2.7);
            const ey = Math.sign(Math.sin(a)) * Math.abs(Math.sin(a)) ** (2 / 2.7);
            const x = eye[0] + hw * ex;
            const y = eye[1] + 0.001 + hh * ey;
            frame.push([x, y, zf - 0.006 * ((x - side * 0.0) / 0.07) ** 2 * 0 - 0.0035 * Math.abs(ex) * (side * ex > 0 ? 1 : 0.2) + 0 * rc]);
        }

        tube(frame, 0.0018, 6, true);

        // Branche : du bord extérieur jusqu'à l'oreille.
        const start = [eye[0] + side * hw, eye[1] + 0.008, zf - 0.004];
        const end = [side * 0.110, HEAD[1] + 0.004, -0.012];
        tube([start, mix(start, end, 0.5).map((v, i) => (i === 0 ? v + side * 0.004 : v)), end], 0.0016, 5);
    }

    // Pont au-dessus du nez.
    const eyeL = eyeCenter(1);
    const zf = eyeL[2] + EYE_R + 0.0105;
    const bridge = [];

    for (let k = 0; k <= 8; k++) {
        const t = k / 8;
        const x = lerp(-0.017, 0.017, t);
        bridge.push([x, eyeL[1] + 0.006 + 0.004 * Math.sin(Math.PI * t), zf + 0.0005 + 0.002 * Math.sin(Math.PI * t)]);
    }

    tube(bridge, 0.0018, 6);

    return scaleHead(b);
}

// Teacher Rose : tresses fines qui tombent sur les côtés et dans le dos (au-dessus des épaules), boucles d'oreilles.
function buildBraidsAndEarrings(b, tube) {
    const R = 0.0062;

    for (let n = 0; n < 40; n++) {
        const side = n % 2 ? -1 : 1;
        const i = Math.floor(n / 2);
        // Des tempes (1,15) jusqu'à la nuque (π), jamais devant le visage.
        const phi = side * lerp(1.2, Math.PI - 0.03, i / 19);
        const back = smooth(1.6, Math.PI, Math.abs(phi));
        const start = 0.30 - 0.12 * back;
        const end = -0.95 - 0.55 * back + 0.06 * Math.sin(n * 2.3);
        const points = [];

        for (let k = 0; k <= 14; k++) {
            const t = k / 14;
            const dy = lerp(start, end, t);
            const along = Math.max(-0.35, dy);
            const c = Math.sqrt(Math.max(0, 1 - along * along));
            const base = headPoint(c * Math.sin(phi), along, c * Math.cos(phi));
            const out = norm([Math.sin(phi), 0, Math.cos(phi)]);
            // Posée sur les cheveux, puis tombe tout droit en s'écartant un peu.
            const lift = 0.020 + 0.012 * smooth(-0.35, -0.9, dy);
            const q = add(base, add(mul(out, lift), [0, dy < -0.35 ? (dy + 0.35) * AY : 0, 0]));
            points.push(add(HEAD, q));
        }

        // Bandes claires et foncées alternées : l'aspect tressé.
        tube(points, R, 6, false, (k) => (k % 2 ? C.hair : C.hairDark), 30);
    }

    // Chignon haut à l'arrière du crâne : silhouette élégante, jamais un casque.
    const bun = add(HEAD, [0, 0.118, -0.050]);
    ellipsoid(b, bun, [0.046, 0.036, 0.040], C.hair, headSkin, 30, 16, 10);
    ellipsoid(b, add(bun, [0, -0.026, 0.020]), [0.040, 0.008, 0.012], C.hairDark, headSkin, 30, 12, 6);

    for (const side of [1, -1]) {
        const lobe = add(HEAD, [side * 0.111, -0.044, -0.004]);
        ellipsoid(b, lobe, [0.0042, 0.0052, 0.0042], C.tie, headSkin, 32, 10, 6);
    }
}

/* ---------- Corps : buste, gilet, chemise, cravate, cou, bras, mains ---------- */

const torsoProfile = [
    // y, demi-largeur, profondeur avant, profondeur arrière
    [0.00, 0.150, 0.100, 0.095],
    [0.08, 0.148, 0.102, 0.095],
    [0.20, 0.152, 0.108, 0.096],
    [0.31, 0.168, 0.114, 0.098],
    [0.38, 0.177, 0.110, 0.098],
    [0.425, 0.186, 0.100, 0.094],
    [0.455, 0.166, 0.087, 0.084],
    [0.475, 0.106, 0.070, 0.068],
    [0.488, 0.060, 0.052, 0.054],
];

function torsoPoint(s, a, offset = 0) {
    const rows = torsoProfile;
    const f = s * (rows.length - 1);
    const i = Math.min(rows.length - 2, Math.floor(f));
    const t = f - i;
    const st = t * t * (3 - 2 * t);
    const r = rows[i].map((v, k) => lerp(v, rows[i + 1][k], k === 0 ? t : st));
    const n = 2.7;
    const sa = Math.sin(a);
    const ca = Math.cos(a);
    const x = (r[1] + offset) * Math.sign(sa) * Math.abs(sa) ** (2 / n);
    const z = (ca >= 0 ? r[2] + offset : r[3] + offset) * Math.sign(ca) * Math.abs(ca) ** (2 / n);

    return [x, r[0], z];
}

// Largeur de l'encolure en V du gilet (angle de chaque bord).
const V_BOTTOM = 0.27;
const vAngle = (y) => (y <= V_BOTTOM ? 0 : 0.62 * smooth(V_BOTTOM, 0.47, y) ** 0.75);

const bodySkin = (p, side = 0) => skinWeights(p, side);

function buildBody() {
    const b = new Builder();
    const S = 30;

    // Chemise visible dans l'encolure (devant seulement).
    {
        const rows = [];

        for (let i = 0; i <= 14; i++) {
            const s = lerp(0.50, 0.995, i / 14);
            const row = [];

            for (let j = 0; j <= 16; j++) {
                const a = lerp(-0.85, 0.85, j / 16);
                const q = torsoPoint(s, a);
                row.push(b.vertex(q, C.shirt, bodySkin(q), 50));
            }

            rows.push(row);
        }

        b.grid(rows, false);
    }

    // Gilet : enveloppe ouverte devant au-dessus du bas du V.
    {
        const rows = [];

        for (let i = 0; i <= S; i++) {
            const s = i / S;
            const y = torsoPoint(s, 0)[1];
            const av = vAngle(y);
            const row = [];
            const COLS = 64;

            for (let j = 0; j <= COLS; j++) {
                const a = lerp(av, 2 * Math.PI - av, j / COLS);
                const q = torsoPoint(s, a, 0.0035);
                const edge = av > 0 && (j === 0 || j === COLS) ? 1 : 0;
                // Bordure côtelée plus sombre autour de l'encolure.
                const nearEdge = av > 0 && (j <= 1 || j >= COLS - 1);
                row.push(b.vertex(edge ? add(q, [0, 0, -0.002]) : q, nearEdge ? C.vestDark : C.vest, bodySkin(q), 51));
            }

            rows.push(row);
        }

        b.grid(rows, false);

        // Boutons du gilet.
        for (const y of [0.12, 0.19, 0.255]) {
            const s = solveS(y);
            const q = torsoPoint(s, 0, 0.0055);
            disc(b, q, [0, 0, 1], 0.0055, C.vestDark, bodySkin(q), 52);
        }
    }

    // Cou.
    {
        const rows = [];

        for (let i = 0; i <= 10; i++) {
            const y = lerp(0.43, 0.66, i / 10);
            const row = [];

            for (let j = 0; j < 20; j++) {
                const a = (2 * Math.PI * j) / 20;
                const q = [0.054 * Math.sin(a), y, -0.010 + 0.050 * Math.cos(a)];
                row.push(b.vertex(q, C.skin, neckSkin(y), 53));
            }

            rows.push(row);
        }

        b.grid(rows, true);
    }

    // Col de chemise : bande autour du cou + deux pointes.
    {
        const rows = [];

        for (let i = 0; i <= 3; i++) {
            const y = lerp(0.462, 0.512, i / 3);
            const r = lerp(0.066, 0.058, i / 3);
            const row = [];

            for (let j = 0; j <= 24; j++) {
                const a = lerp(0.32, 2 * Math.PI - 0.32, j / 24);
                const q = [r * Math.sin(a), y, -0.008 + r * Math.cos(a)];
                row.push(b.vertex(q, C.shirt, neckSkin(Math.min(y, 0.47)), 54));
            }

            rows.push(row);
        }

        b.grid(rows, false);

        for (const side of [1, -1]) {
            const top = [side * 0.021, 0.506, 0.050];
            const base = [side * 0.048, 0.494, 0.043];
            const tip = [side * 0.040, 0.437, 0.090];
            const mid = [side * 0.015, 0.462, 0.080];
            const ids = [top, base, tip, mid].map((q) => b.vertex(q, C.shirt, bodySkin(q), 55));

            if (side > 0) {
                b.quad(ids[0], ids[3], ids[2], ids[1]);
            } else {
                b.quad(ids[0], ids[1], ids[2], ids[3]);
            }
        }
    }

    // Teacher Rose : foulard orange noué au col (deux pans courts).
    if (ROSE) {
        const zAt = (y) => torsoPoint(solveS(y), 0)[2] + 0.0035;

        for (const side of [1, -1]) {
            const rows = [];

            for (let i = 0; i <= 6; i++) {
                const t = i / 6;
                const y = lerp(0.462, 0.410, t);
                const cx = side * lerp(0.006, 0.030, t);
                const w = lerp(0.010, 0.015, t);
                const row = [];

                for (let j = 0; j <= 2; j++) {
                    const x = cx + lerp(-w, w, j / 2);
                    const q = [x, y, zAt(y) + 0.003 + (j === 1 ? 0.002 : 0)];
                    row.push(b.vertex(q, j === 1 ? C.tie : mix(C.tie, C.tieDark, 0.5), bodySkin(q), 56));
                }

                rows.push(row);
            }

            b.grid(rows, false, side < 0);
        }

        const knot = [0, 0.468, zAt(0.468) + 0.008];
        ellipsoid(b, knot, [0.020, 0.013, 0.009], C.tie, bodySkin(knot), 57, 12, 8);
    } else {
        // Cravate : nœud et pan.
        const zAt = (y) => torsoPoint(solveS(y), 0)[2] + 0.0035;
        const rows = [];

        for (let i = 0; i <= 10; i++) {
            const t = i / 10;
            const y = lerp(0.456, 0.255, t);
            const w = lerp(0.0075, 0.0135, t);
            const row = [];

            for (let j = 0; j <= 2; j++) {
                const x = lerp(-w, w, j / 2);
                const q = [x, y - (i === 10 && j === 1 ? 0.012 : 0), zAt(y) + (j === 1 ? 0.0025 : 0)];
                row.push(b.vertex(q, j === 1 ? C.tie : mix(C.tie, C.tieDark, 0.5), bodySkin(q), 56));
            }

            rows.push(row);
        }

        b.grid(rows, false, true);

        const knot = [0, 0.466, zAt(0.466) + 0.006];
        ellipsoid(b, knot, [0.0115, 0.0105, 0.0065], C.tie, bodySkin(knot), 57, 10, 6);
    }

    // Bras et mains.
    for (const side of [1, -1]) {
        buildArm(b, side);
    }

    return b;
}

function solveS(y) {
    let lo = 0;
    let hi = 1;

    for (let i = 0; i < 30; i++) {
        const m = (lo + hi) / 2;

        if (torsoPoint(m, 0)[1] < y) {
            lo = m;
        } else {
            hi = m;
        }
    }

    return (lo + hi) / 2;
}

function disc(b, center, normal, radius, c, skin, group) {
    const n = norm(normal);
    const u = norm(cross(n, [0, 1, 0]));
    const v = cross(u, n);
    const c0 = b.vertex(add(center, mul(n, 0.0012)), c, skin, group);
    const ring = [];

    for (let k = 0; k < 10; k++) {
        const a = (2 * Math.PI * k) / 10;
        ring.push(b.vertex(add(center, add(mul(u, radius * Math.cos(a)), mul(v, radius * Math.sin(a)))), c, skin, group));
    }

    for (let k = 0; k < 10; k++) {
        b.tri(c0, ring[(k + 1) % 10], ring[k]);
    }
}

// Ellipsoïde (axes alignés ou repère donné).
function ellipsoid(b, center, radii, c, skin, group, N = 12, M = 8, frame = null, deform = null) {
    const rows = [];

    for (let i = 0; i <= M; i++) {
        const v = -Math.PI / 2 + (Math.PI * i) / M;
        const row = [];

        for (let j = 0; j < N; j++) {
            const u = (2 * Math.PI * j) / N;
            let l = [radii[0] * Math.cos(v) * Math.sin(u), radii[1] * Math.sin(v), radii[2] * Math.cos(v) * Math.cos(u)];

            if (deform) {
                l = deform(l);
            }

            const q = frame ? add(center, add(add(mul(frame[0], l[0]), mul(frame[1], l[1])), mul(frame[2], l[2]))) : add(center, l);
            row.push(b.vertex(q, c, typeof skin === 'function' ? skin(q) : skin, group));
        }

        rows.push(row);
    }

    b.grid(rows, true);
}

// Courbe de Catmull-Rom passant par les points.
function catmull(points, steps) {
    const out = [];

    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[Math.max(0, i - 1)];
        const p1 = points[i];
        const p2 = points[i + 1];
        const p3 = points[Math.min(points.length - 1, i + 2)];

        for (let k = 0; k < steps; k++) {
            const t = k / steps;
            const t2 = t * t;
            const t3 = t2 * t;
            out.push([0, 1, 2].map((d) => 0.5 * ((2 * p1[d]) + (-p0[d] + p2[d]) * t + (2 * p0[d] - 5 * p1[d] + 4 * p2[d] - p3[d]) * t2 + (-p0[d] + 3 * p1[d] - 3 * p2[d] + p3[d]) * t3)));
        }
    }

    out.push(points[points.length - 1]);

    return out;
}

function buildArm(b, side) {
    const m = (p) => (side > 0 ? p : mirror(p));
    const ctrl = [
        [0.120, 0.432, -0.006],
        ARM.arm,
        [0.222, 0.312, -0.016],
        ARM.elbow,
        [0.212, 0.201, 0.105],
        ARM.wrist,
    ].map(m);
    const radiusAt = (t) => table([[0, 0.044], [0.18, 0.044], [0.42, 0.040], [0.58, 0.037], [0.78, 0.034], [0.93, 0.031], [0.95, 0.033], [1, 0.032]], t);
    const path = catmull(ctrl, 10);
    const n = path.length;
    const SIDES = 18;
    const rows = [];
    let u = null;

    for (let i = 0; i < n; i++) {
        const t = norm(sub(path[Math.min(n - 1, i + 1)], path[Math.max(0, i - 1)]));

        // Repère transporté le long du bras (évite les torsions).
        if (u === null) {
            u = norm(cross(t, [0, 0, 1]));
        } else {
            u = norm(sub(u, mul(t, dot(u, t))));
        }

        const v = cross(t, u);
        const r = radiusAt(i / (n - 1));
        const row = [];

        for (let k = 0; k < SIDES; k++) {
            const a = (2 * Math.PI * k) / SIDES;
            const q = add(path[i], add(mul(u, r * Math.cos(a)), mul(v, r * Math.sin(a) * 0.92)));
            const cuff = i / (n - 1) > 0.93;
            row.push(b.vertex(q, cuff ? mix(C.shirt, [0.6, 0.62, 0.66], 0.15) : C.shirt, skinWeights(q, side), 60));
        }

        rows.push(row);
    }

    b.grid(rows, true);

    // Fond de la manche.
    const end = path[n - 1];
    const cap = b.vertex(end, C.shirt, skinWeights(end, side), 60);

    for (let k = 0; k < SIDES; k++) {
        b.tri(cap, rows[n - 1][k], rows[n - 1][(k + 1) % SIDES]);
    }

    // Main posée à plat sur le bureau, doigts légèrement repliés.
    const wrist = m(ARM.wrist);
    const dir = norm(sub(m(ARM.handEnd), wrist));
    const flat = norm([dir[0], 0, dir[2]]);
    const across = norm(cross([0, 1, 0], flat)); // vers la gauche du personnage pour la main gauche ? dépend du côté
    const up = [0, 1, 0];
    const handSkin = (q) => skinWeights(q, side);
    const frame = [across, up, flat];

    // Poignet.
    ellipsoid(b, add(wrist, mul(flat, 0.012)), [0.026, 0.020, 0.024], C.skin, handSkin, 61, 12, 6, frame);
    // Paume.
    const palm = add(wrist, add(mul(flat, 0.046), [0, -0.004, 0]));
    ellipsoid(b, palm, [0.036, 0.0145, 0.036], C.skin, handSkin, 62, 14, 8, frame);

    // Doigts.
    const thumbSide = side > 0 ? -1 : 1; // le pouce est du côté du centre du corps
    const acrossToBody = mul(across, dot(across, [thumbSide, 0, 0]) >= 0 ? 1 : -1);

    for (let f = 0; f < 4; f++) {
        const k = (f - 1.5) / 1.5; // -1 … 1, de l'intérieur vers l'extérieur
        const base = add(palm, add(mul(flat, 0.030), mul(acrossToBody, -k * 0.024)));
        const length = [0.040, 0.045, 0.043, 0.035][f];
        const pts = [];

        for (let s = 0; s <= 6; s++) {
            const t = s / 6;
            const curl = 0.55 * t * t;
            pts.push(add(base, add(mul(flat, length * Math.sin(Math.PI / 2 * (1 - curl)) * t + length * 0.25 * t * (1 - curl)), [0, -length * 0.55 * curl * t - 0.002 * t, 0])));
        }

        capsule(b, pts, 0.0082 - 0.0008 * Math.abs(k), C.skin, handSkin, 63);
    }

    // Pouce.
    const tBase = add(palm, add(mul(acrossToBody, 0.030), mul(flat, -0.002)));
    const tPts = [];

    for (let s = 0; s <= 5; s++) {
        const t = s / 5;
        tPts.push(add(tBase, add(mul(flat, 0.036 * t), add(mul(acrossToBody, 0.010 * t - 0.012 * t * t), [0, -0.006 * t, 0]))));
    }

    capsule(b, tPts, 0.0092, C.skin, handSkin, 64);
}

// Tube arrondi aux deux bouts (doigts).
function capsule(b, pts, radius, c, skin, group) {
    const SIDES = 8;
    const rows = [];
    const n = pts.length;
    let u = null;

    // Pointe de départ et d'arrivée arrondies par des anneaux plus petits.
    const profile = [];

    for (let i = 0; i < n; i++) {
        profile.push({ p: pts[i], r: radius * (i === n - 1 ? 0.92 : 1) });
    }

    const t0 = norm(sub(pts[1], pts[0]));
    const t1 = norm(sub(pts[n - 1], pts[n - 2]));
    profile.unshift({ p: add(pts[0], mul(t0, -radius * 0.4)), r: radius * 0.6 });
    profile.push({ p: add(pts[n - 1], mul(t1, radius * 0.55)), r: radius * 0.62 });
    profile.push({ p: add(pts[n - 1], mul(t1, radius * 0.88)), r: radius * 0.05 });

    profile.forEach((ring, i) => {
        const a = profile[Math.max(0, i - 1)].p;
        const z = profile[Math.min(profile.length - 1, i + 1)].p;
        const t = norm(sub(z, a));

        if (u === null) {
            u = norm(cross(t, [0, 1, 0]));
        } else {
            u = norm(sub(u, mul(t, dot(u, t))));
        }

        const v = cross(t, u);
        const row = [];

        for (let k = 0; k < SIDES; k++) {
            const ang = (2 * Math.PI * k) / SIDES;
            const q = add(ring.p, add(mul(u, ring.r * Math.cos(ang)), mul(v, ring.r * Math.sin(ang) * 0.85)));
            row.push(b.vertex(q, c, skin(q), group));
        }

        rows.push(row);
    });

    b.grid(rows, true);
}

/* ---------- Poids de peau ---------- */

function segmentDistance(p, a, b) {
    const ab = sub(b, a);
    const t = clamp(dot(sub(p, a), ab) / dot(ab, ab));

    return len(sub(p, add(a, mul(ab, t))));
}

const bonePos = Object.fromEntries(BONES.map(([name, , pos]) => [name, pos]));
bonePos.LeftShoulder = ARM.shoulder;
bonePos.LeftArm = ARM.arm;
bonePos.LeftForeArm = ARM.elbow;
bonePos.LeftHand = ARM.wrist;
bonePos.LeftHandMiddle1 = ARM.handEnd;

for (const key of ['Shoulder', 'Arm', 'ForeArm', 'Hand', 'HandMiddle1']) {
    bonePos[`Right${key}`] = mirror(bonePos[`Left${key}`]);
}

const SEGMENTS = {
    Hips: ['Hips', 'Spine'],
    Spine: ['Spine', 'Spine1'],
    Spine1: ['Spine1', 'Spine2'],
    Spine2: ['Spine2', 'Neck'],
    Neck: ['Neck', 'Head'],
    LeftShoulder: ['LeftShoulder', 'LeftArm'],
    LeftArm: ['LeftArm', 'LeftForeArm'],
    LeftForeArm: ['LeftForeArm', 'LeftHand'],
    LeftHand: ['LeftHand', 'LeftHandMiddle1'],
    RightShoulder: ['RightShoulder', 'RightArm'],
    RightArm: ['RightArm', 'RightForeArm'],
    RightForeArm: ['RightForeArm', 'RightHand'],
    RightHand: ['RightHand', 'RightHandMiddle1'],
};

// Poids inversement proportionnels à la distance aux os candidats (4 au plus).
function skinWeights(p, side = 0) {
    let candidates = ['Hips', 'Spine', 'Spine1', 'Spine2', 'Neck', 'LeftShoulder', 'RightShoulder'];

    if (side > 0) {
        candidates = ['Spine2', 'LeftShoulder', 'LeftArm', 'LeftForeArm', 'LeftHand'];
    } else if (side < 0) {
        candidates = ['Spine2', 'RightShoulder', 'RightArm', 'RightForeArm', 'RightHand'];
    }

    const scored = candidates.map((name) => {
        const [a, b] = SEGMENTS[name];
        const d = segmentDistance(p, bonePos[a], bonePos[b]);

        return [name, 1 / (d * d + 1e-5) ** 3];
    });

    scored.sort((x, y) => y[1] - x[1]);
    const top = scored.slice(0, 4);
    const total = top.reduce((s, [, w]) => s + w, 0);

    return top.map(([name, w]) => [name, w / total]).filter(([, w]) => w > 0.01);
}

function neckSkin(y) {
    const t = smooth(0.45, 0.62, y);

    if (y < 0.46) {
        const s = smooth(0.43, 0.46, y);

        return [['Spine2', 1 - s], ['Neck', s]];
    }

    return [['Neck', 1 - t], ['Head', t]];
}

/* ---------- Écriture du fichier glTF ---------- */

function normals(builder, positions) {
    const out = new Float32Array(positions.length);
    const idx = builder.indices;

    for (let i = 0; i < idx.length; i += 3) {
        const [a, b, c] = [idx[i], idx[i + 1], idx[i + 2]];
        const pa = positions.subarray(a * 3, a * 3 + 3);
        const pb = positions.subarray(b * 3, b * 3 + 3);
        const pc = positions.subarray(c * 3, c * 3 + 3);
        const n = cross(sub(pb, pa), sub(pc, pa));

        for (const v of [a, b, c]) {
            out[v * 3] += n[0];
            out[v * 3 + 1] += n[1];
            out[v * 3 + 2] += n[2];
        }
    }

    // Lissage à travers les coutures : sommets confondus du même groupe.
    const welded = new Map();

    for (let v = 0; v < positions.length / 3; v++) {
        const key = `${builder.smoothGroups[v]}:${Math.round(positions[v * 3] * 1e5)}:${Math.round(positions[v * 3 + 1] * 1e5)}:${Math.round(positions[v * 3 + 2] * 1e5)}`;

        if (!welded.has(key)) {
            welded.set(key, []);
        }

        welded.get(key).push(v);
    }

    for (const group of welded.values()) {
        if (group.length < 2) {
            continue;
        }

        const s = [0, 0, 0];
        group.forEach((v) => { s[0] += out[v * 3]; s[1] += out[v * 3 + 1]; s[2] += out[v * 3 + 2]; });
        group.forEach((v) => out.set(s, v * 3));
    }

    // Intérieur de la bouche, dents, langue : tournés vers l'avant (au repos ils sont aplatis).
    builder.smoothGroups.forEach((g, v) => {
        if (g === 10 || g === 13 || g === 14) {
            out.set([0, 0.15, 1], v * 3);
        }
    });

    for (let v = 0; v < out.length; v += 3) {
        const l = Math.hypot(out[v], out[v + 1], out[v + 2]) || 1;
        out[v] /= l;
        out[v + 1] /= l;
        out[v + 2] /= l;
    }

    return out;
}

const flat = (list) => Float32Array.from(list.flat());

async function main() {
    const doc = new Document();
    doc.getRoot().getAsset().generator = 'build-teacher-joe.mjs';
    const buffer = doc.createBuffer();
    const scene = doc.createScene(ROSE ? 'TeacherRose' : 'TeacherJoe');
    doc.getRoot().setDefaultScene(scene);
    const root = doc.createNode('Armature');
    scene.addChild(root);

    // Os : translations relatives au parent, sans rotation.
    const allBones = [...BONES];

    for (const prefix of ['Left', 'Right']) {
        const m = prefix === 'Left' ? (p) => p : mirror;
        allBones.push(
            [`${prefix}Shoulder`, 'Spine2', m(ARM.shoulder)],
            [`${prefix}Arm`, `${prefix}Shoulder`, m(ARM.arm)],
            [`${prefix}ForeArm`, `${prefix}Arm`, m(ARM.elbow)],
            [`${prefix}Hand`, `${prefix}ForeArm`, m(ARM.wrist)],
            [`${prefix}HandMiddle1`, `${prefix}Hand`, m(ARM.handEnd)],
        );
    }

    const scaled = (p) => add(HEAD, mul(sub(p, HEAD), HEAD_SCALE));
    allBones.push(['LeftEye', 'Head', scaled(eyeCenter(1))], ['RightEye', 'Head', scaled(eyeCenter(-1))]);

    const nodes = {};
    const world = {};

    for (const [name, parent, pos] of allBones) {
        const node = doc.createNode(name);
        world[name] = pos;
        node.setTranslation(parent ? sub(pos, world[parent]) : pos);
        (parent ? nodes[parent] : root).addChild(node);
        nodes[name] = node;
    }

    const jointNames = allBones.map(([name]) => name);
    const ibm = new Float32Array(jointNames.length * 16);

    jointNames.forEach((name, i) => {
        const [x, y, z] = world[name];
        ibm.set([1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, -x, -y, -z, 1], i * 16);
    });

    const skin = doc.createSkin(ROSE ? 'TeacherRoseSkin' : 'TeacherJoeSkin')
        .setSkeleton(nodes.Hips)
        .setInverseBindMatrices(doc.createAccessor().setType('MAT4').setArray(ibm).setBuffer(buffer));
    jointNames.forEach((name) => skin.addJoint(nodes[name]));

    const material = (name, roughness, doubleSided = false) => doc.createMaterial(name)
        .setBaseColorFactor([1, 1, 1, 1])
        .setRoughnessFactor(roughness)
        .setMetallicFactor(0)
        .setDoubleSided(doubleSided);

    const addMesh = (name, rest, mat, morphs = null) => {
        const positions = flat(rest.positions);
        const vertexCount = rest.positions.length;
        const joints = new Uint8Array(vertexCount * 4);
        const weights = new Float32Array(vertexCount * 4);

        rest.skins.forEach((influences, v) => {
            const sum = influences.reduce((s, [, w]) => s + w, 0);
            influences.slice(0, 4).forEach(([bone, w], k) => {
                joints[v * 4 + k] = jointNames.indexOf(bone);
                weights[v * 4 + k] = w / sum;
            });
        });

        const acc = (type, array) => doc.createAccessor().setType(type).setArray(array).setBuffer(buffer);
        const prim = doc.createPrimitive()
            .setAttribute('POSITION', acc('VEC3', positions))
            .setAttribute('NORMAL', acc('VEC3', normals(rest, positions)))
            .setAttribute('COLOR_0', acc('VEC3', flat(rest.colors)))
            .setAttribute('JOINTS_0', acc('VEC4', joints))
            .setAttribute('WEIGHTS_0', acc('VEC4', weights))
            .setIndices(acc('SCALAR', Uint32Array.from(rest.indices)))
            .setMaterial(mat);

        const mesh = doc.createMesh(name).addPrimitive(prim);

        if (morphs) {
            const names = Object.keys(morphs);

            for (const key of names) {
                const shaped = flat(morphs[key].positions);
                const delta = new Float32Array(shaped.length);

                for (let i = 0; i < shaped.length; i++) {
                    delta[i] = shaped[i] - positions[i];
                }

                prim.addTarget(doc.createPrimitiveTarget(key).setAttribute('POSITION', acc('VEC3', delta)));
            }

            mesh.setWeights(names.map(() => 0)).setExtras({ targetNames: names });
        }

        // Maillage à la racine de la scène (recommandation glTF pour les maillages animés par un squelette).
        scene.addChild(doc.createNode(name).setMesh(mesh).setSkin(skin));
        console.log(`${name}: ${vertexCount} sommets, ${rest.indices.length / 3} triangles${morphs ? `, ${Object.keys(morphs).length} formes` : ''}`);
    };

    const faceRest = buildFace(NEUTRAL);
    const faceMorphs = {};

    for (const [key, params] of Object.entries(SHAPES)) {
        faceMorphs[key] = buildFace({ ...NEUTRAL, ...params });
    }

    addMesh('TeacherFace', faceRest, material('Skin', 0.62), faceMorphs);
    addMesh('TeacherEyes', buildEyes(), material('Eyes', 0.12));
    addMesh('TeacherHair', buildHairAndGlasses(), material('Hair', 0.8));
    addMesh('TeacherBody', buildBody(), material('Clothes', 0.82, true));

    await MeshoptEncoder.ready;
    await doc.transform(
        dedup(),
        prune(),
        reorder({ encoder: MeshoptEncoder, target: 'size' }),
        quantize({ quantizePosition: 14, quantizeNormal: 10, quantizeColor: 8, quantizeWeight: 8 }),
        meshopt({ encoder: MeshoptEncoder, level: 'high' }),
    );

    const io = new NodeIO().registerExtensions(ALL_EXTENSIONS).registerDependencies({ 'meshopt.encoder': MeshoptEncoder });
    mkdirSync(dirname(OUTPUT), { recursive: true });
    await io.write(OUTPUT, doc);
    const { statSync } = await import('node:fs');
    console.log(`Écrit : ${OUTPUT} (${(statSync(OUTPUT).size / 1024).toFixed(0)} Ko)`);
}

await main();
