/*
 * Capture du micro : convertit le son du navigateur (24 kHz si le contexte est
 * réglé sur la voix de Gemini, sinon souvent 44,1 ou 48 kHz, en nombres
 * flottants) en PCM 16 bits mono à 16 kHz, le format attendu par l'API Gemini
 * Live, et l'envoie par paquets d'environ 100 ms.
 *
 * Réduction de fréquence propre : filtre passe-bas (sinus cardinal fenêtré,
 * coupure à 7 kHz, sous la moitié de 16 kHz) PUIS prélèvement avec
 * interpolation linéaire. Sans ce filtre, les sons aigus (« s », « ch »,
 * souffle) se replieraient en grésillement dans le son envoyé.
 * Testé par tests/js/pcm-capture.test.mjs.
 */

const TAPS = 63;
const CUTOFF_HZ = 7000;

export class Downsampler {
    constructor(inRate, outRate) {
        this.step = inRate / outRate;
        this.taps = Downsampler.design(inRate, Math.min(CUTOFF_HZ, outRate * 0.45));
        this.history = new Float32Array(TAPS);
        this.cursor = 0;
        this.previous = 0;
        // Position de la prochaine sortie, en échantillons d'entrée filtrés.
        this.position = 0;
    }

    /**
     * Coefficients du filtre : sinus cardinal × fenêtre de Hamming, gain 1 aux basses fréquences.
     */
    static design(rate, cutoff) {
        const taps = new Float32Array(TAPS);
        const fc = cutoff / rate;
        const middle = (TAPS - 1) / 2;
        let sum = 0;

        for (let i = 0; i < TAPS; i++) {
            const x = i - middle;
            const sinc = x === 0 ? 2 * fc : Math.sin(2 * Math.PI * fc * x) / (Math.PI * x);
            taps[i] = sinc * (0.54 - 0.46 * Math.cos((2 * Math.PI * i) / (TAPS - 1)));
            sum += taps[i];
        }

        return taps.map((value) => value / sum);
    }

    /**
     * @param {Float32Array} input  échantillons du micro
     * @param {(sample: number) => void} emit  reçoit chaque échantillon à la fréquence de sortie
     */
    process(input, emit) {
        const { taps, history } = this;

        for (let n = 0; n < input.length; n++) {
            history[this.cursor] = input[n];
            this.cursor = (this.cursor + 1) % TAPS;

            let filtered = 0;

            for (let k = 0, j = this.cursor; k < TAPS; k++, j = (j + 1) % TAPS) {
                filtered += taps[k] * history[j];
            }

            // Sorties situées entre l'échantillon filtré précédent et celui-ci.
            while (this.position <= 1) {
                emit(this.previous + (filtered - this.previous) * this.position);
                this.position += this.step;
            }

            this.position -= 1;
            this.previous = filtered;
        }
    }
}

/**
 * Échantillon flottant → entier 16 bits, écrêté à [-1, 1].
 */
export function toInt16(sample) {
    const clamped = Math.max(-1, Math.min(1, sample));

    return clamped < 0 ? Math.round(clamped * 0x8000) : Math.round(clamped * 0x7fff);
}

// Hors d'un AudioWorklet (tests), seule la partie de calcul ci-dessus est utilisée.
if (typeof AudioWorkletProcessor !== 'undefined') {
    class PcmCapture extends AudioWorkletProcessor {
        constructor(options) {
            super();
            const targetRate = options.processorOptions.targetRate;
            this.downsampler = new Downsampler(sampleRate, targetRate);
            this.chunkSize = Math.round(targetRate / 10);
            this.chunk = new Int16Array(this.chunkSize);
            this.length = 0;
            this.emit = (sample) => {
                this.chunk[this.length++] = toInt16(sample);

                if (this.length === this.chunkSize) {
                    // Le tampon est transféré (pas copié) : on en crée un nouveau.
                    this.port.postMessage(this.chunk.buffer, [this.chunk.buffer]);
                    this.chunk = new Int16Array(this.chunkSize);
                    this.length = 0;
                }
            };
        }

        process(inputs) {
            const channel = inputs[0] && inputs[0][0];

            if (channel) {
                this.downsampler.process(channel, this.emit);
            }

            return true;
        }
    }

    registerProcessor('pcm-capture', PcmCapture);
}
