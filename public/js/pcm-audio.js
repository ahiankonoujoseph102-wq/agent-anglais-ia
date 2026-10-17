/*
 * Lecture de la voix du professeur, sans dépendance au navigateur : testée par
 * tests/js/pcm-audio.test.mjs.
 *
 * Gemini envoie la voix en PCM 16 bits petit-boutiste, mono, 24 kHz, par
 * petits morceaux. Pour une lecture propre (sans grésillement) :
 *   - décodage exact des échantillons, sans dépassement de [-1, 1] ;
 *   - morceaux programmés bout à bout, à l'échantillon près (compteur entier) ;
 *   - petite réserve au début de chaque prise de parole, agrandie après un trou ;
 *   - rééchantillonnage CONTINU d'un morceau à l'autre si le contexte audio
 *     n'est pas à 24 kHz (le navigateur rééchantillonnerait chaque morceau à
 *     part, avec un raccord à chaque fois) ;
 *   - fondu très court au début d'une prise de parole.
 */

export const VOICE_RATE = 24000;

/**
 * Chaîne binaire (résultat de atob) → échantillons entre -1 et 1.
 */
export function decodePcm16(binary) {
    const samples = new Float32Array(binary.length >> 1);

    for (let i = 0; i < samples.length; i++) {
        // Petit-boutiste : octet de poids faible d'abord ; puis signe sur 16 bits.
        let value = binary.charCodeAt(2 * i) | (binary.charCodeAt(2 * i + 1) << 8);

        if (value >= 0x8000) {
            value -= 0x10000;
        }

        samples[i] = value / 0x8000;
    }

    return samples;
}

/**
 * Rééchantillonnage linéaire qui garde son état d'un morceau à l'autre :
 * découper le son en morceaux ne change pas le résultat.
 */
export class StreamResampler {
    constructor(inRate, outRate) {
        this.step = inRate / outRate;
        this.reset();
    }

    /** Nouvelle prise de parole : on repart du silence. */
    reset() {
        this.last = 0;
        // Position de la prochaine sortie, en échantillons d'entrée ; -1 = dernier échantillon du morceau précédent.
        this.position = 0;
    }

    process(input) {
        const output = [];
        let position = this.position;

        while (position < input.length - 1 + 1e-9) {
            const index = Math.floor(position);
            const fraction = position - index;
            const a = index < 0 ? this.last : input[index];
            const b = input[Math.min(index + 1, input.length - 1)];
            output.push(index + 1 < input.length ? a + (b - a) * fraction : a);
            position += this.step;
        }

        if (input.length > 0) {
            this.last = input[input.length - 1];
            this.position = position - input.length;
        }

        return Float32Array.from(output);
    }
}

/**
 * Fondu d'entrée sur les premiers échantillons (début d'une prise de parole).
 */
export function fadeIn(samples, length) {
    const n = Math.min(length, samples.length);

    for (let i = 0; i < n; i++) {
        samples[i] *= i / n;
    }

    return samples;
}

/**
 * Programmation des morceaux de voix, comptée en échantillons entiers.
 */
export class VoiceScheduler {
    /**
     * @param {number} sampleRate  fréquence du contexte audio
     * @param {number} reserve     réserve au début d'une prise de parole, en secondes
     * @param {number} maxReserve  réserve maximale après des trous répétés
     */
    constructor({ sampleRate, reserve = 0.12, maxReserve = 0.3 }) {
        this.sampleRate = sampleRate;
        this.reserve = reserve;
        this.maxReserve = maxReserve;
        this.endFrame = 0;
    }

    /** Fin programmée de la voix, en secondes (horloge audio). */
    get endTime() {
        return this.endFrame / this.sampleRate;
    }

    /**
     * Place un morceau de `frames` échantillons. Bout à bout avec le précédent
     * s'il reste au moins 10 millisecondes à jouer ; sinon (début de prise de
     * parole, ou trou du réseau) après la réserve.
     *
     * @param {boolean} continuing  le professeur était déjà en train de parler (un trou = réseau trop lent)
     * @return {{ time: number, gap: boolean }}
     */
    schedule(now, frames, continuing = false) {
        const nowFrame = Math.ceil(now * this.sampleRate);
        const gap = this.isGap(now);

        if (gap && continuing) {
            // Le réseau n'a pas suivi : la réserve grandit pour la suite de la conversation.
            this.reserve = Math.min(this.maxReserve, this.reserve + 0.06);
        }

        const startFrame = gap ? nowFrame + Math.round(this.reserve * this.sampleRate) : this.endFrame;
        this.endFrame = startFrame + frames;

        return { time: startFrame / this.sampleRate, gap };
    }

    /** Vrai si un nouveau morceau ne peut pas suivre le précédent bout à bout (moins de 10 millisecondes à jouer). */
    isGap(now) {
        return this.endFrame < Math.ceil(now * this.sampleRate) + Math.round(0.01 * this.sampleRate);
    }

    /** Voix arrêtée (interruption, pause) : plus rien de programmé après `time`. */
    stop(time) {
        this.endFrame = Math.ceil(time * this.sampleRate);
    }
}
