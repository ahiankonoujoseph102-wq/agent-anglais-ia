// Garde-fou des lèvres des vidéos de leçon : la bouche de Teacher Joe suit-elle vraiment la voix ? Comparaison, image
// par image, de l'énergie de la voix (fichier audio réel) et de l'ouverture de la bouche (formes réellement appliquées
// au visage). Une vidéo qui échoue n'est jamais déposée. Testé par tests/js/video-lip-check.test.mjs.

/** Part d'ouverture de la bouche de chaque forme (comme les formes du modèle, tools/teacher3d/build-teacher-joe.mjs). */
export const MOUTH_OPEN = {
    jawOpen: 1, viseme_aa: 1, viseme_O: 0.65, viseme_E: 0.55, viseme_kk: 0.36, viseme_DD: 0.3, viseme_I: 0.3,
    viseme_TH: 0.22, viseme_RR: 0.22, viseme_U: 0.22, viseme_CH: 0.2, viseme_nn: 0.16, viseme_FF: 0.12, viseme_SS: 0.1,
};

/** Seuils par défaut (réglables par la configuration de la vidéo : render.lips). */
export const DEFAULTS = {
    min_correlation: 0.5, // accord minimal entre la voix et la bouche
    min_open: 0.08, // ouverture moyenne minimale pendant la parole
    speech_rms: 0.03, // au-dessus : la voix parle
    silence_rms: 0.003, // en dessous : silence
    max_lag: 1, // décalage toléré, en images
    min_speech_frames: 10,
};

/** Ouverture de la bouche d'après les valeurs des formes (nom → valeur entre 0 et 1). */
export function openness(values) {
    let open = 0;

    for (const [name, weight] of Object.entries(MOUTH_OPEN)) {
        open += (values[name] ?? 0) * weight;
    }

    return open;
}

/** Corrélation de Pearson ; 0 si l'une des deux suites est constante. */
export function correlation(a, b) {
    const n = Math.min(a.length, b.length);

    if (n < 2) {
        return 0;
    }

    let ma = 0;
    let mb = 0;

    for (let i = 0; i < n; i++) {
        ma += a[i];
        mb += b[i];
    }

    ma /= n;
    mb /= n;
    let cov = 0;
    let va = 0;
    let vb = 0;

    for (let i = 0; i < n; i++) {
        cov += (a[i] - ma) * (b[i] - mb);
        va += (a[i] - ma) ** 2;
        vb += (b[i] - mb) ** 2;
    }

    return va === 0 || vb === 0 ? 0 : cov / Math.sqrt(va * vb);
}

/**
 * Verdict : accord (meilleure corrélation pour un décalage d'au plus max_lag images, la bouche ne pouvant pas
 * précéder la voix de plus de max_lag), ouvertures moyennes pendant la parole et en silence.
 * @param {number[]} rms énergie de la voix à chaque image
 * @param {number[]} open ouverture de la bouche à chaque image
 */
export function lipCheck(rms, open, options = {}) {
    const o = { ...DEFAULTS, ...options };
    let best = { correlation: -1, lag: 0 };

    for (let lag = -o.max_lag; lag <= o.max_lag; lag++) {
        const a = rms.slice(Math.max(0, -lag), rms.length - Math.max(0, lag));
        const b = open.slice(Math.max(0, lag), open.length - Math.max(0, -lag));
        const c = correlation(a, b);

        if (c > best.correlation) {
            best = { correlation: c, lag };
        }
    }

    const speech = open.filter((_, i) => rms[i] > o.speech_rms);
    const silence = open.filter((_, i) => rms[i] < o.silence_rms);
    const mean = (list) => (list.length ? list.reduce((s, v) => s + v, 0) / list.length : 0);
    const result = {
        correlation: Math.round(best.correlation * 100) / 100,
        lag: best.lag,
        open_speech: Math.round(mean(speech) * 1000) / 1000,
        open_silence: Math.round(mean(silence) * 1000) / 1000,
        speech_frames: speech.length,
        ok: true,
        reason: null,
    };

    if (speech.length < o.min_speech_frames) {
        return { ...result, ok: false, reason: 'aucune parole détectée dans la voix' };
    }

    if (result.open_speech < o.min_open) {
        return { ...result, ok: false, reason: `bouche fermée pendant la parole (ouverture ${result.open_speech})` };
    }

    if (best.correlation < o.min_correlation) {
        return { ...result, ok: false, reason: `lèvres mal synchronisées (accord ${result.correlation})` };
    }

    return result;
}
