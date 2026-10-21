// Message vocal pas encore prêt (résultat de l'évaluation) : le serveur le prépare, la page redemande son état
// toutes les quelques secondes et le joue dès qu'il est prêt, sans nouveau toucher.

/**
 * Surveille l'état du message.
 *  - check()   : promesse de vrai quand le message est prêt (erreur réseau : faux, on réessaie)
 *  - onReady() : prêt (une seule fois)
 *  - onLate()  : toujours pas prêt après « lateMs » (une seule fois)
 *  - onGiveUp(): toujours pas prêt après « giveUpMs » : on arrête de demander
 */
export class ReadyWatch {
    constructor({ check, pollMs = 3000, lateMs = 30000, giveUpMs = 180000, onReady = () => {}, onLate = () => {}, onGiveUp = () => {}, schedule = (fn, ms) => setTimeout(fn, ms), now = () => Date.now() }) {
        // setTimeout et Date.now enveloppés : appelés comme méthodes de l'objet, le navigateur les refuse.
        Object.assign(this, { check, pollMs, lateMs, giveUpMs, onReady, onLate, onGiveUp, schedule, now });
        this.startedAt = null;
        this.lateSaid = false;
        this.done = false;
    }

    start() {
        if (this.startedAt !== null || this.done) {
            return;
        }

        this.startedAt = this.now();
        this.tick();
    }

    stop() {
        this.done = true;
    }

    async tick() {
        if (this.done) {
            return;
        }

        let ready = false;

        try {
            ready = Boolean(await this.check());
        } catch {
            ready = false;
        }

        if (this.done) {
            return;
        }

        if (ready) {
            this.done = true;
            this.onReady();

            return;
        }

        const elapsed = this.now() - this.startedAt;

        if (!this.lateSaid && elapsed >= this.lateMs) {
            this.lateSaid = true;
            this.onLate();
        }

        if (elapsed >= this.giveUpMs) {
            this.done = true;
            this.onGiveUp();

            return;
        }

        this.schedule(() => this.tick(), this.pollMs);
    }
}

/**
 * Un court silence en WAV : joué au toucher, il « déverrouille » le lecteur (iPhone, Android) pour que le vrai
 * message puisse démarrer tout seul plus tard.
 */
export function silentWav(samples = 240) {
    const bytes = new Uint8Array(44 + samples * 2);
    const view = new DataView(bytes.buffer);
    const text = (offset, value) => [...value].forEach((char, i) => view.setUint8(offset + i, char.charCodeAt(0)));

    text(0, 'RIFF');
    view.setUint32(4, 36 + samples * 2, true);
    text(8, 'WAVEfmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, 1, true);
    view.setUint32(24, 24000, true);
    view.setUint32(28, 48000, true);
    view.setUint16(32, 2, true);
    view.setUint16(34, 16, true);
    text(36, 'data');
    view.setUint32(40, samples * 2, true);

    return bytes;
}
