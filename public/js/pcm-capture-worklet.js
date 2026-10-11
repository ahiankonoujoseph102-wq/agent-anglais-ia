/*
 * Capture du micro : convertit le son du navigateur (souvent 44,1 ou 48 kHz,
 * en nombres flottants) en PCM 16 bits mono à 16 kHz, le format attendu par
 * l'API Gemini Live, et l'envoie par paquets d'environ 100 ms.
 */
class PcmCapture extends AudioWorkletProcessor {
    constructor(options) {
        super();
        this.targetRate = options.processorOptions.targetRate;
        this.ratio = sampleRate / this.targetRate;
        this.chunkSize = Math.round(this.targetRate / 10);
        this.chunk = new Int16Array(this.chunkSize);
        this.length = 0;
        this.sum = 0;
        this.count = 0;
        this.phase = 0;
    }

    process(inputs) {
        const channel = inputs[0] && inputs[0][0];

        if (!channel) {
            return true;
        }

        for (let i = 0; i < channel.length; i++) {
            // Sous-échantillonnage par moyenne (filtre passe-bas simple).
            this.sum += channel[i];
            this.count++;
            this.phase++;

            if (this.phase >= this.ratio) {
                this.phase -= this.ratio;
                const sample = Math.max(-1, Math.min(1, this.sum / this.count));
                this.chunk[this.length++] = sample < 0 ? sample * 0x8000 : sample * 0x7fff;
                this.sum = 0;
                this.count = 0;

                if (this.length === this.chunkSize) {
                    // Le tampon est transféré (pas copié) : on en crée un nouveau.
                    this.port.postMessage(this.chunk.buffer, [this.chunk.buffer]);
                    this.chunk = new Int16Array(this.chunkSize);
                    this.length = 0;
                }
            }
        }

        return true;
    }
}

registerProcessor('pcm-capture', PcmCapture);
