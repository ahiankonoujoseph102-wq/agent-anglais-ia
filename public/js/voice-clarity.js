/**
 * Chaîne de lecture de la voix du professeur (séances en direct).
 *
 * « classic » : la chaîne d'avant, inchangée (volume puis limiteur dur).
 * « clear »   : MÊME voix, mêmes paroles ; seul le rendu dans le téléphone change :
 *   - coupe-bas (le grave sourd qu'un haut-parleur de téléphone rend en « boue ») ;
 *   - léger creux dans le bas-médium (voix moins « nasale » et moins étouffée) ;
 *   - légère présence vers 3 000 hertz (consonnes « s », « t », « k » : les mots se détachent) ;
 *   - compression douce puis limiteur rapide et garde-fou : la voix reste forte, jamais écrasée ni saturée.
 * Réglages : config/platform.php → live.voice_clarity (App\Support\Live\VoiceClarity).
 */

export const CLASSIC = 'classic';
export const CLEAR = 'clear';

/** Réglages de la chaîne « clear » quand le serveur n'en donne pas. */
export const CLEAR_DEFAULTS = Object.freeze({
    gain: 1.8,
    highpass_hz: 120,
    mud_hz: 320,
    mud_db: -2,
    presence_hz: 3000,
    presence_db: 3,
    presence_q: 0.9,
    compressor_threshold: -20,
    compressor_knee: 12,
    compressor_ratio: 3,
});

/**
 * Garde-fou final : rien ne dépasse jamais le niveau maximal (pas de grésillement), sans toucher la voix
 * en dessous de 90 % du maximum (courbe linéaire, puis arrondie jusqu'à 1).
 */
export function softClipCurve(size = 2048, knee = 0.9) {
    const curve = new Float32Array(size);
    for (let i = 0; i < size; i++) {
        const x = (i / (size - 1)) * 2 - 1;
        const a = Math.abs(x);
        const y = a <= knee ? a : knee + (1 - knee) * Math.tanh((a - knee) / (1 - knee));
        curve[i] = Math.sign(x) * y;
    }

    return curve;
}

/** Réglages effectifs : valeurs du serveur, sinon celles par défaut ; jamais un nombre absurde. */
export function clearSettings(given = {}) {
    const pick = (key, min, max) => {
        const value = Number(given?.[key]);

        return Number.isFinite(value) ? Math.min(max, Math.max(min, value)) : CLEAR_DEFAULTS[key];
    };

    return {
        gain: pick('gain', 0.5, 4),
        highpass_hz: pick('highpass_hz', 20, 300),
        mud_hz: pick('mud_hz', 150, 800),
        mud_db: pick('mud_db', -6, 0),
        presence_hz: pick('presence_hz', 1500, 6000),
        presence_db: pick('presence_db', 0, 6),
        presence_q: pick('presence_q', 0.3, 3),
        compressor_threshold: pick('compressor_threshold', -40, 0),
        compressor_knee: pick('compressor_knee', 0, 30),
        compressor_ratio: pick('compressor_ratio', 1, 8),
    };
}

/**
 * Construit la chaîne entre la voix (entrée) et la sortie.
 * Renvoie { input, output } : brancher la voix sur input, output sur la suite (retard, haut-parleur).
 */
export function buildVoiceChain(context, { mode = CLASSIC, gain = 2.5, clear = {} } = {}) {
    const input = context.createGain();
    const limiter = context.createDynamicsCompressor();
    limiter.attack.value = 0.002;

    if (mode !== CLEAR) {
        // Chaîne d'avant, valeur pour valeur.
        const boost = context.createGain();
        boost.gain.value = gain;
        limiter.threshold.value = -3;
        limiter.knee.value = 0;
        limiter.ratio.value = 20;
        limiter.release.value = 0.15;
        input.connect(boost).connect(limiter);

        return { input, output: limiter };
    }

    const s = clearSettings(clear);
    const highpass = context.createBiquadFilter();
    highpass.type = 'highpass';
    highpass.frequency.value = s.highpass_hz;
    highpass.Q.value = 0.707;

    const mud = context.createBiquadFilter();
    mud.type = 'peaking';
    mud.frequency.value = s.mud_hz;
    mud.Q.value = 1;
    mud.gain.value = s.mud_db;

    const presence = context.createBiquadFilter();
    presence.type = 'peaking';
    presence.frequency.value = s.presence_hz;
    presence.Q.value = s.presence_q;
    presence.gain.value = s.presence_db;

    const boost = context.createGain();
    boost.gain.value = s.gain;

    // Compression douce : les syllabes faibles remontent, les fortes ne sont pas écrasées.
    const compressor = context.createDynamicsCompressor();
    compressor.threshold.value = s.compressor_threshold;
    compressor.knee.value = s.compressor_knee;
    compressor.ratio.value = s.compressor_ratio;
    compressor.attack.value = 0.005;
    compressor.release.value = 0.15;

    // Limiteur rapide, puis garde-fou arrondi : jamais au-dessus du maximum.
    limiter.threshold.value = -2;
    limiter.knee.value = 0;
    limiter.ratio.value = 20;
    limiter.attack.value = 0.001;
    limiter.release.value = 0.08;
    const guard = context.createWaveShaper();
    guard.curve = softClipCurve();
    guard.oversample = '2x';

    input.connect(highpass).connect(mud).connect(presence).connect(boost).connect(compressor).connect(limiter).connect(guard);

    return { input, output: guard };
}
